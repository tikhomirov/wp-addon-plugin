<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\CacheService;
use WpAddon\Services\OptionService;
use WpAddon\Services\PageCacheNginxService;
use WpAddon\Services\PageCacheRewriteService;

class PageCache implements ModuleInterface
{
    private CacheService $cache;

    private OptionService $optionService;

    private PageCacheRewriteService $rewriteService;

    private PageCacheNginxService $nginxService;

    private array $config;

    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
        $this->loadConfig();
        $this->cache = new CacheService($this->config['cache_dir'], $this->config['ttl']);
        $this->rewriteService = new PageCacheRewriteService;
        $this->nginxService = new PageCacheNginxService;
    }

    public function init(): void
    {
        if (! $this->config['enabled']) {
            return;
        }

        add_action('init', [$this, 'syncRewriteRules'], 2);
        add_action('template_redirect', [$this, 'startCache'], PHP_INT_MIN);
        add_action('wp_loaded', [$this, 'scheduleMaintenance']);
        add_action('page_cache_preload', [$this, 'doPreload']);
        add_action('page_cache_cleanup', [$this, 'cleanupExpiredEntries']);

        if ($this->config['clear_on_post_save']) {
            $this->registerInvalidationHooks();
        }
    }

    public function syncRewriteRules(): void
    {
        $version = hash('sha256', $this->config['cache_dir'].'|'.$this->config['ttl'].'|2');
        if (get_option('wp_addon_page_cache_rules') === $version) {
            return;
        }

        $cacheUrlPath = $this->getCacheUrlPath();
        $nginxSynced = $this->nginxService->sync($this->config['cache_dir'], $cacheUrlPath, $this->config['ttl']);
        $apacheSynced = $this->isNginx() || $this->rewriteService->sync($this->config['cache_dir'], $this->config['ttl']);

        if ($nginxSynced && $apacheSynced) {
            update_option('wp_addon_page_cache_rules', $version, false);
        }
    }

    public function startCache(): void
    {
        if (! $this->shouldCacheRequest()) {
            return;
        }

        $request = $this->getRequest();
        if ($request === null) {
            return;
        }

        $cached = $this->cache->getCachedPage($request['host'], $request['path']);
        if ($cached !== null) {
            header('X-Page-Cache: HIT');
            header('Cache-Control: public, max-age='.$this->config['ttl']);
            echo $cached;
            exit;
        }

        header('X-Page-Cache: MISS');
        ob_start([$this, 'cacheOutput']);
    }

    public function cacheOutput(string $content): string
    {
        $request = $this->getRequest();
        if ($request !== null && $this->shouldCacheResponse($content)) {
            $this->cache->savePage($request['host'], $request['path'], $content);
        }

        return $content;
    }

    public function shouldCache(): bool
    {
        return $this->shouldCacheRequest();
    }

    public function clearCache($ignored = null): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (is_numeric($ignored) && function_exists('wp_is_post_revision') && wp_is_post_revision((int) $ignored))) {
            return;
        }

        $this->cache->clearCache();
    }

    public function doPreload(): void
    {
        foreach ($this->getPreloadPages() as $url) {
            $path = $this->normalizePath($url);
            if ($path === null || $this->isExcludedPath($path)) {
                continue;
            }

            wp_remote_get(home_url($path), [
                'timeout' => 10,
                'redirection' => 0,
                'headers' => ['X-WP-Addon-Preload' => '1'],
            ]);
        }
    }

    public function scheduleMaintenance(): void
    {
        if (! wp_next_scheduled('page_cache_preload')) {
            wp_schedule_event(time(), 'hourly', 'page_cache_preload');
        }

        if (! wp_next_scheduled('page_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'page_cache_cleanup');
        }
    }

    public function preloadPages(): void
    {
        $this->scheduleMaintenance();
    }

    public function cleanupExpiredEntries(): void
    {
        $this->cache->cleanup($this->config['max_files'], $this->config['ttl'], $this->config['cleanup_batch_size']);
    }

    public function getExcludeRules(): array
    {
        return $this->config['exclude_urls'];
    }

    private function loadConfig(): void
    {
        $defaults = require RW_PLUGIN_DIR.'src/Config/cache.php';
        $preloadSetting = $this->optionService->getSetting('cache_preload_pages', '');

        $this->config = [
            'enabled' => $this->toBool($this->optionService->getSetting('cache_enabled', $defaults['enabled'])),
            'ttl' => max(1, (int) $this->optionService->getSetting('cache_ttl', $defaults['ttl'])),
            'exclude_logged_in' => $this->toBool($this->optionService->getSetting('cache_exclude_logged_in', $defaults['exclude_logged_in'])),
            'exclude_urls' => $this->normalizeLines($this->optionService->getSetting('cache_exclude_urls', implode("\n", $defaults['exclude_urls']))),
            'preload_pages' => $preloadSetting === '' ? $defaults['preload_pages'] : $this->normalizeLines($preloadSetting),
            'auto_preload' => $preloadSetting === '' && $defaults['preload_pages'] === [],
            'clear_on_post_save' => $this->toBool($this->optionService->getSetting('cache_clear_on_post_save', true)),
            'cache_dir' => $defaults['cache_dir'],
            'max_files' => (int) $defaults['max_files'],
            'cleanup_batch_size' => (int) $defaults['cleanup_batch_size'],
        ];
    }

    private function shouldCacheRequest(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        if (! in_array($method, ['GET', 'HEAD'], true) || ! empty($_SERVER['QUERY_STRING'])) {
            return false;
        }

        if ($this->config['exclude_logged_in'] && (is_user_logged_in() || $this->hasPrivateCookie())) {
            return false;
        }

        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }

        if (is_feed() || is_search() || is_preview() || is_404() || is_robots() || is_trackback()) {
            return false;
        }

        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return false;
        }

        $request = $this->getRequest();

        return $request !== null && ! $this->isExcludedPath($request['path']);
    }

    private function shouldCacheResponse(string $content): bool
    {
        if ($content === '' || http_response_code() !== 200 || is_404()) {
            return false;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0 || stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) {
                return false;
            }
        }

        return stripos(ltrim($content), '<!doctype html') === 0 || stripos(ltrim($content), '<html') === 0;
    }

    private function getRequest(): ?array
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        $path = $this->normalizePath((string) ($_SERVER['REQUEST_URI'] ?? ''));

        if (! preg_match('/\A[a-z0-9.-]+\z/', $host) || $path === null) {
            return null;
        }

        return ['host' => $host, 'path' => $path];
    }

    private function normalizePath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '/';
        }

        $path = '/'.ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');

        return str_ends_with($path, '/') || pathinfo($path, PATHINFO_EXTENSION) !== '' ? $path : $path.'/';
    }

    private function isExcludedPath(string $path): bool
    {
        $customLoginSlug = trim((string) get_option('whl_page'), '/');
        if ($customLoginSlug !== '' && $this->pathMatches($path, '/'.$customLoginSlug.'/')) {
            return true;
        }

        foreach ($this->config['exclude_urls'] as $exclude) {
            if ($this->pathMatches($path, $exclude)) {
                return true;
            }
        }

        return false;
    }

    private function pathMatches(string $path, string $excluded): bool
    {
        $excluded = '/'.trim($excluded, '/');

        return $excluded !== '/' && ($path === $excluded || $path === $excluded.'/' || str_starts_with($path, $excluded.'/'));
    }

    private function hasPrivateCookie(): bool
    {
        $privatePrefixes = [
            'wordpress_logged_in_',
            'wp-postpass_',
            'comment_author_',
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
        ];

        foreach (array_keys($_COOKIE) as $name) {
            foreach ($privatePrefixes as $prefix) {
                if (str_starts_with((string) $name, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function registerInvalidationHooks(): void
    {
        foreach (['save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'set_object_terms', 'created_term', 'edited_term', 'delete_term', 'comment_post', 'edit_comment', 'deleted_comment', 'switch_theme'] as $hook) {
            add_action($hook, [$this, 'clearCache']);
        }

        add_action('transition_comment_status', [$this, 'clearCache']);
        add_action('wp_update_nav_menu', [$this, 'clearCache']);
        add_action('update_option_sidebars_widgets', [$this, 'clearCache']);
        add_action('customize_save_after', [$this, 'clearCache']);
    }

    private function getPreloadPages(): array
    {
        if (! $this->config['auto_preload']) {
            return $this->config['preload_pages'];
        }

        $pages = ['/'];
        $locations = get_nav_menu_locations();
        $menuId = $locations['primary'] ?? $locations['main'] ?? null;
        if ($menuId !== null) {
            foreach (wp_get_nav_menu_items($menuId) ?: [] as $item) {
                if ($item->type === 'post_type' && $item->object === 'page') {
                    $path = wp_parse_url($item->url, PHP_URL_PATH);
                    if (is_string($path)) {
                        $pages[] = $path;
                    }
                }
            }
        }

        return array_slice(array_values(array_unique($pages)), 0, 10);
    }

    private function getCacheUrlPath(): string
    {
        $contentDirectory = trailingslashit(wp_normalize_path(WP_CONTENT_DIR));
        $cacheDirectory = trailingslashit(wp_normalize_path($this->config['cache_dir']));
        $relativeDirectory = str_starts_with($cacheDirectory, $contentDirectory)
            ? trim(substr($cacheDirectory, strlen($contentDirectory)), '/')
            : 'cache/pages';
        $contentPath = trim((string) wp_parse_url(content_url(), PHP_URL_PATH), '/');

        return '/'.trim($contentPath.'/'.$relativeDirectory, '/');
    }

    private function isNginx(): bool
    {
        return stripos((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx') !== false;
    }

    private function normalizeLines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $value) ?: []), static fn (string $line): bool => $line !== ''));
    }

    private function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
