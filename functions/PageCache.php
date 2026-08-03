<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\CacheService;
use WpAddon\Services\OptionService;

class PageCache implements ModuleInterface
{
    private CacheService $cache;

    private OptionService $optionService;

    private array $config;

    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
        $this->loadConfig();
        $this->cache = new CacheService($this->config['cache_dir'], $this->config['ttl']);
    }

    private function loadConfig(): void
    {
        $defaultConfig = require RW_PLUGIN_DIR.'src/Config/cache.php';

        $preloadPagesSetting = $this->optionService->getSetting('cache_preload_pages', '');
        $preloadPages = $defaultConfig['preload_pages'];
        if (! empty($preloadPagesSetting)) {
            $preloadPages = array_filter(explode("\n", $preloadPagesSetting));
        }

        $this->config = [
            'enabled' => $this->toBool($this->optionService->getSetting('cache_enabled', $defaultConfig['enabled'])),
            'ttl' => max(1, (int) $this->optionService->getSetting('cache_ttl', $defaultConfig['ttl'])),
            'exclude_logged_in' => $this->toBool($this->optionService->getSetting('cache_exclude_logged_in', $defaultConfig['exclude_logged_in'])),
            'exclude_urls' => array_filter(explode("\n", $this->optionService->getSetting('cache_exclude_urls', implode("\n", $defaultConfig['exclude_urls'])))),
            'preload_pages' => $preloadPages,
            'auto_preload' => empty($preloadPagesSetting) && empty($defaultConfig['preload_pages']),
            'clear_on_post_save' => $this->toBool($this->optionService->getSetting('cache_clear_on_post_save', true)),
            'cache_dir' => $defaultConfig['cache_dir'],
            'max_files' => (int) $defaultConfig['max_files'],
            'cleanup_batch_size' => (int) $defaultConfig['cleanup_batch_size'],
        ];
    }

    private function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function init(): void
    {
        if (! $this->config['enabled']) {
            return;
        }

        add_action('wp', [$this, 'startCache']);
        if ($this->config['clear_on_post_save']) {
            add_action('save_post', [$this, 'clearCache']);
        }
        add_action('wp_loaded', [$this, 'preloadPages']);

        // Preload and cleanup hooks.
        add_action('page_cache_preload', [$this, 'doPreload']);
        add_action('page_cache_cleanup', [$this, 'cleanupExpiredEntries']);
    }

    public function doPreload(): void
    {
        $preloadPagesSetting = $this->optionService->getSetting('cache_preload_pages', '');
        $pages = [];

        if (! empty($preloadPagesSetting)) {
            $pages = array_filter(explode("\n", $preloadPagesSetting));
        } else {
            // Auto preload mode
            $pages = $this->getAutoPreloadPages();
        }

        if (! empty($pages)) {
            foreach ($pages as $url) {
                $path = $this->normalizePath($url);
                if ($path === null) {
                    continue;
                }

                $response = wp_remote_get(home_url($path), ['timeout' => 10]);
                $body = ! is_wp_error($response) ? wp_remote_retrieve_body($response) : '';
                if (! is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300 && $body !== '') {
                    $key = $this->cache->generateCacheKey($path);
                    $this->cache->saveCachedContent($key, $body);
                }
            }
        }
    }

    private function getAutoPreloadPages(): array
    {
        $pages = [];

        // Получаем главную страницу
        $frontPageId = get_option('page_on_front');
        if ($frontPageId) {
            $frontPageUrl = get_permalink($frontPageId);
            if ($frontPageUrl) {
                $pages[] = parse_url($frontPageUrl, PHP_URL_PATH) ?: '/';
            }
        } else {
            $pages[] = '/';
        }

        // Получаем страницы из главного меню
        $locations = get_nav_menu_locations();
        if (isset($locations['primary']) || isset($locations['main'])) {
            $menuId = $locations['primary'] ?? $locations['main'];
            $menuItems = wp_get_nav_menu_items($menuId);

            if ($menuItems) {
                foreach ($menuItems as $item) {
                    if ($item->type === 'post_type' && $item->object === 'page') {
                        $pageUrl = parse_url($item->url, PHP_URL_PATH);
                        if ($pageUrl && $pageUrl !== '/' && ! in_array($pageUrl, $pages)) {
                            $pages[] = $pageUrl;
                        }
                    }
                }
            }
        }

        // Добавляем страницу блога, если она есть
        $blogPageId = get_option('page_for_posts');
        if ($blogPageId && $blogPageId !== $frontPageId) {
            $blogUrl = get_permalink($blogPageId);
            if ($blogUrl) {
                $blogPath = parse_url($blogUrl, PHP_URL_PATH);
                if ($blogPath && ! in_array($blogPath, $pages)) {
                    $pages[] = $blogPath;
                }
            }
        }

        return array_slice($pages, 0, 10); // Ограничиваем до 10 страниц
    }

    public function preloadPages(): void
    {
        if (! wp_next_scheduled('page_cache_preload')) {
            wp_schedule_event(time(), 'hourly', 'page_cache_preload');
        }

        if (! wp_next_scheduled('page_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'page_cache_cleanup');
        }
    }

    public function cleanupExpiredEntries(): void
    {
        $this->cache->cleanup($this->config['max_files'], $this->config['ttl'], $this->config['cleanup_batch_size']);
    }

    public function startCache(): void
    {
        if (! $this->shouldCache()) {
            return;
        }

        $path = $this->getCachePath();
        if ($path === null) {
            return;
        }

        $key = $this->cache->generateCacheKey($path);
        $cached = $this->cache->getCachedContent($key);

        if ($cached) {
            echo $cached;
            exit;
        }

        ob_start([$this, 'cacheOutput']);
    }

    public function shouldCache(): bool
    {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET') {
            return false;
        }

        if ($this->config['exclude_logged_in'] && is_user_logged_in()) {
            return false;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? null;
        if (! is_string($requestUri) || strpos($requestUri, '?') !== false) {
            return false;
        }

        $url = $this->getCachePath();
        if ($url === null) {
            return false;
        }
        if (defined('REST_REQUEST') && REST_REQUEST || wp_doing_cron() || defined('XMLRPC_REQUEST') && XMLRPC_REQUEST || is_feed() || is_search() || is_preview()) {
            return false;
        }

        $custom_login_slug = get_option('whl_page');
        if (! empty($custom_login_slug)) {
            $custom_login_path = '/'.ltrim($custom_login_slug, '/');
            if (strpos($url, $custom_login_path) === 0 || isset($_GET[$custom_login_slug])) {
                return false;
            }
        }
        foreach ($this->config['exclude_urls'] as $exclude) {
            if (strpos($url, trim($exclude)) === 0) {
                return false;
            }
        }

        return true;
    }

    private function getCachePath(): ?string
    {
        return isset($_SERVER['REQUEST_URI']) ? $this->normalizePath($_SERVER['REQUEST_URI']) : null;
    }

    private function normalizePath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        return '/'.ltrim($path, '/');
    }

    public function cacheOutput(string $content): string
    {
        if ($this->shouldCache()) {
            $path = $this->getCachePath();
            if ($path !== null) {
                $key = $this->cache->generateCacheKey($path);
                $this->cache->saveCachedContent($key, $content);
            }
        }

        return $content;
    }

    public function clearCache($postId = 0): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (function_exists('wp_is_post_revision') && wp_is_post_revision($postId))) {
            return;
        }

        $this->cache->clearCache();
    }

    public function getExcludeRules(): array
    {
        return $this->config['exclude_urls'];
    }
}
