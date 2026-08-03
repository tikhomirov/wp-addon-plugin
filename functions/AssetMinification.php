<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\AssetOptimizationService;
use WpAddon\Services\OptionService;

class AssetMinification implements ModuleInterface
{
    private AssetOptimizationService $optimizationService;

    private OptionService $optionService;

    private array $config;

    private array $cssQueue = [];

    private array $jsQueue = [];

    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
        // Конфигурация будет загружена в init()
    }

    private function loadConfig(): void
    {
        $configPath = plugin_dir_path(dirname(__FILE__)).'src/Config/optimization.php';
        if (! file_exists($configPath)) {
            throw new Exception("Config file not found: {$configPath}");
        }
        $defaultConfig = require $configPath;

        $this->config = [
            'enabled' => $this->optionService->getSetting('asset_minification_enabled', $defaultConfig['enabled']),
            'minify_css' => $this->optionService->getSetting('asset_minify_css', $defaultConfig['minify_css']),
            'minify_js' => $this->optionService->getSetting('asset_minify_js', $defaultConfig['minify_js']),
            'combine_css' => $this->optionService->getSetting('asset_combine_css', $defaultConfig['combine_css']),
            'combine_js' => $this->optionService->getSetting('asset_combine_js', $defaultConfig['combine_js']),
            'critical_css_enabled' => $this->optionService->getSetting('asset_critical_css_enabled', $defaultConfig['critical_css_enabled']),
            'defer_non_critical_css' => $this->optionService->getSetting('asset_defer_non_critical_css', $defaultConfig['defer_non_critical_css']),
            'exclude_css' => array_merge($defaultConfig['exclude_css'], explode(',', $this->optionService->getSetting('asset_exclude_css', ''))),
            'exclude_js' => array_merge($defaultConfig['exclude_js'], explode(',', $this->optionService->getSetting('asset_exclude_js', ''))),
            'cache_dir' => $this->optionService->getSetting('cache_dir', $defaultConfig['cache_dir']),
            'version_salt' => $this->optionService->getSetting('version_salt', $defaultConfig['version_salt']),
        ];
    }

    public function init(): void
    {
        $this->loadConfig();
        $this->optimizationService = new AssetOptimizationService($this->config);

        if (! $this->config['enabled']) {
            return;
        }

        add_action('wp_print_styles', [$this, 'processCssAssets'], 10);
        add_action('wp_print_scripts', [$this, 'processJsAssets'], 10);
        add_action('wp_head', [$this, 'injectCriticalCss'], 1);

        if ($this->config['defer_non_critical_css']) {
            add_action('wp_enqueue_scripts', [$this, 'deferCssLoading'], 1000);
        }

        // Clear cache on theme/plugin updates and retain a bounded cache between deploys.
        add_action('upgrader_process_complete', [$this, 'clearCache']);
        add_action('wp_loaded', [$this, 'scheduleCacheCleanup']);
        add_action('wp_addon_asset_cache_cleanup', [$this, 'cleanupCache']);
    }

    public function processCssAssets(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // Отладка: добавить HTML комментарий
        // add_action('wp_head', function() {
        //     echo '<!-- AssetMinification: processCssAssets called -->';
        // });

        // Отладка: записать в лог
        // error_log('AssetMinification: processCssAssets called');

        $this->processCss();
    }

    public function processJsAssets(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // Отладка: записать в лог
        // error_log('AssetMinification: processJsAssets called');

        $this->processJs();
    }

    public function processCss(): void
    {
        global $wp_styles;

        if (! $wp_styles || ! $this->config['minify_css'] && ! $this->config['combine_css']) {
            return;
        }

        $cssFiles = [];
        $handlesToRemove = [];

        foreach ($wp_styles->queue as $handle) {
            if (! isset($wp_styles->registered[$handle])) {
                continue;
            }

            $style = $wp_styles->registered[$handle];

            // Использовать умную проверку
            if (! $this->shouldProcessAsset($handle, $style->src, $this->config['exclude_css'])) {
                continue;
            }

            $filePath = $this->urlToPath($style->src);
            $content = file_get_contents($filePath);

            // Проверить, не минифицирован ли уже файл
            if ($this->isAlreadyMinified($content, 'css')) {
                continue;
            }

            $cssFiles[$handle] = $filePath;
            $handlesToRemove[] = $handle;
        }

        if (! empty($cssFiles) && $this->config['combine_css']) {
            $combinedCss = $this->optimizationService->combineCss(array_values($cssFiles));
            if (! empty($combinedCss)) {
                $version = $this->optimizationService->generateVersion($combinedCss);
                $cacheKey = 'css-'.$version;
                $this->optimizationService->saveToCache($cacheKey, $combinedCss);

                wp_enqueue_style(
                    'wp-addon-combined-css',
                    $this->getCacheUrl($cacheKey),
                    [],
                    $version
                );

                // Remove original styles
                foreach ($handlesToRemove as $handle) {
                    wp_dequeue_style($handle);
                }
            }
        } elseif ($this->config['minify_css']) {
            // Minify individual files
            foreach ($handlesToRemove as $handle) {
                $style = $wp_styles->registered[$handle];
                $filePath = $this->urlToPath($style->src);
                $content = file_get_contents($filePath);

                // Пропустить, если уже минифицирован
                if ($this->isAlreadyMinified($content, 'css')) {
                    continue;
                }

                $minified = $this->optimizationService->minifyCss($content);

                $version = $this->optimizationService->generateVersion($minified);
                $cacheKey = 'css-'.$handle.'-'.$version;
                $this->optimizationService->saveToCache($cacheKey, $minified);

                // Replace src
                $style->src = $this->getCacheUrl($cacheKey);
                $style->ver = $version;
            }
        }
    }

    private function processJs(): void
    {
        global $wp_scripts;

        if (! $wp_scripts || ! $this->config['minify_js'] && ! $this->config['combine_js']) {
            return;
        }

        // Отладка
        // error_log('AssetMinification: processJs started, scripts count: ' . count($wp_scripts->queue));

        $jsFiles = [];
        $handlesToRemove = [];

        foreach ($wp_scripts->queue as $handle) {
            if (! isset($wp_scripts->registered[$handle])) {
                continue;
            }

            $script = $wp_scripts->registered[$handle];

            // Использовать умную проверку
            if (! $this->shouldProcessAsset($handle, $script->src, $this->config['exclude_js'])) {
                continue;
            }

            $filePath = $this->urlToPath($script->src);
            $content = file_get_contents($filePath);

            // Проверить, не минифицирован ли уже файл
            if ($this->isAlreadyMinified($content, 'js')) {
                continue;
            }

            $jsFiles[$handle] = $filePath;
            $handlesToRemove[] = $handle;
        }

        if (! empty($jsFiles) && $this->config['combine_js']) {
            $combinedJs = $this->optimizationService->combineJs(array_values($jsFiles));
            if (! empty($combinedJs)) {
                $version = $this->optimizationService->generateVersion($combinedJs);
                $cacheKey = 'js-'.$version;
                $this->optimizationService->saveToCache($cacheKey, $combinedJs);

                wp_enqueue_script(
                    'wp-addon-combined-js',
                    $this->getCacheUrl($cacheKey),
                    [],
                    $version,
                    true
                );

                // Remove original scripts
                foreach ($handlesToRemove as $handle) {
                    wp_dequeue_script($handle);
                }
            }
        } elseif ($this->config['minify_js']) {
            // Minify individual files
            foreach ($handlesToRemove as $handle) {
                $script = $wp_scripts->registered[$handle];
                $filePath = $this->urlToPath($script->src);
                $content = file_get_contents($filePath);

                // Пропустить, если уже минифицирован
                if ($this->isAlreadyMinified($content, 'js')) {
                    continue;
                }

                $minified = $this->optimizationService->minifyJs($content);

                $version = $this->optimizationService->generateVersion($minified);
                $cacheKey = 'js-'.$handle.'-'.$version;
                $this->optimizationService->saveToCache($cacheKey, $minified);

                // Replace src
                $script->src = $this->getCacheUrl($cacheKey);
                $script->ver = $version;
            }
        }
    }

    public function injectCriticalCss(): void
    {
        if (! $this->config['critical_css_enabled']) {
            return;
        }

        // Get theme stylesheet
        $themeCss = get_template_directory().'/style.css';
        if (file_exists($themeCss)) {
            $content = file_get_contents($themeCss);
            $criticalCss = $this->optimizationService->extractCriticalCss($content);

            if (! empty($criticalCss)) {
                echo '<style id="wp-addon-critical-css">'.$criticalCss.'</style>';
            }
        }
    }

    public function deferCssLoading(): void
    {
        if (! $this->config['defer_non_critical_css']) {
            return;
        }

        // Add script to defer non-critical CSS
        wp_add_inline_script('wp-addon-combined-js', '
            document.addEventListener("DOMContentLoaded", function() {
                var links = document.querySelectorAll("link[rel=stylesheet]");
                links.forEach(function(link) {
                    if (!link.id || link.id !== "wp-addon-critical-css") {
                        link.media = "print";
                        link.onload = function() {
                            link.media = "all";
                        };
                    }
                });
            });
        ');
    }

    private function isSystemAsset(string $handle): bool
    {
        $systemAssets = [
            'jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui',
            'underscore', 'backbone', 'wp-util', 'wp-api',
            'admin-bar', 'dashicons', 'thickbox', 'mediaelement',
            'wp-mediaelement', 'wp-playlist', 'zxcvbn-async',
            'set-post-thumbnail', 'heartbeat', 'autosave',
            'wp-ajax-response', 'wp-lists', 'quicktags',
            'colorpicker', 'editor', 'word-count', 'wp-jquery-ui-dialog',
            'schedule', 'jquery-ui-datepicker', 'jquery-ui-sortable',
            'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-tabs',
            'jquery-ui-accordion', 'jquery-ui-autocomplete', 'jquery-ui-slider',
            'jquery-ui-progressbar', 'jquery-ui-tooltip', 'jquery-ui-button',
            'wp-color-picker', 'iris', 'wp-pointer', 'customize-base',
            'customize-loader', 'customize-preview', 'customize-models',
            'customize-views', 'customize-controls', 'customize-widgets',
            'customize-preview-widgets', 'customize-nav-menus',
            'customize-preview-nav-menus', 'wp-custom-header', 'wp-custom-background',
            'accordion', 'shortcode', 'media-upload', 'hoverIntent',
            'common', 'wp-a11y', 'sack', 'quickpress', 'editor-expand',
            'editor-functions', 'language-chooser', 'mce-view', 'imgareaselect',
            'plupload', 'plupload-all', 'resize', 'swfupload', 'swfupload-all',
            'swfupload-queue', 'swfupload-handlers', 'comment', 'comment-reply',
            'suggest', 'admin-comments', 'password-strength-meter',
            'user-profile', 'user-groups', 'admin-gallery', 'admin-widgets',
            'media-widgets', 'media-audio-widget', 'media-image-widget',
            'media-gallery-widget', 'media-video-widget', 'text-widgets',
            'custom-html-widgets', 'theme', 'inline-edit-post',
            'inline-edit-tax', 'plugin-install', 'farbtastic', 'jcrop',
            'colors', 'ie', 'import', 'export', 'install', 'widgets',
            'site-icon', 'svg-painter', 'wp-auth-check', 'wp-jquery-ui-dialog',
            'wp-embed', 'wp-emoji', 'wp-emoji-loader',
        ];

        return in_array($handle, $systemAssets);
    }

    private function shouldProcessAsset(string $handle, string $src, array $excludes): bool
    {
        // Исключить явно указанные
        if (in_array($handle, $excludes)) {
            return false;
        }

        // Исключить системные ресурсы WordPress
        if ($this->isSystemAsset($handle)) {
            return false;
        }

        // Только локальные файлы
        if (! $src || strpos($src, site_url()) !== 0) {
            return false;
        }

        // Проверить существование файла
        $filePath = $this->urlToPath($src);
        if (! file_exists($filePath)) {
            return false;
        }

        // Проверить размер файла (минифицировать только файлы > 1KB)
        if (filesize($filePath) < 1024) {
            return false;
        }

        return true;
    }

    private function isAlreadyMinified(string $content, string $type): bool
    {
        if ($type === 'css') {
            // Проверить на признаки минификации CSS
            return preg_match('/^[^{}]+{[^}]+}/', trim($content)) &&
                   ! preg_match('/\\n/', $content);
        } elseif ($type === 'js') {
            // Проверить на признаки минификации JS
            return ! preg_match('/\\n\\s*\\n/', $content) &&
                   preg_match('/[a-zA-Z_$][a-zA-Z0-9_$]*\\s*[=:]\\s*function/', $content);
        }

        return false;
    }

    private function getAssetPriority(string $handle): int
    {
        $priorities = [
            'critical' => ['theme-styles', 'style', 'main-style', 'custom-css'],
            'high' => ['bootstrap', 'foundation', 'font-awesome', 'icons'],
            'normal' => [],
            'low' => ['social-share', 'comments', 'related-posts'],
        ];

        foreach ($priorities as $level => $assets) {
            if (in_array($handle, $assets)) {
                return array_search($level, ['critical', 'high', 'normal', 'low']);
            }
        }

        return 2; // normal
    }

    private function urlToPath(string $url): string
    {
        if (! $url) {
            return '';
        }
        $path = str_replace(site_url(), '', $url);

        return rtrim(ABSPATH, '/').'/'.ltrim($path, '/');
    }

    private function getCacheUrl(string $key): string
    {
        return content_url('/cache/assets/'.$key.'.gz');
    }

    public function scheduleCacheCleanup(): void
    {
        if (! wp_next_scheduled('wp_addon_asset_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_addon_asset_cache_cleanup');
        }
    }

    public function cleanupCache(): void
    {
        $this->optimizationService->cleanupCache();
    }

    public function clearCache(): void
    {
        // Clear asset cache directory
        $files = glob($this->config['cache_dir'].'*.gz');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    // Public method for testing
    public function processAssets(): void
    {
        $this->processCss();
        $this->processJs();
    }
}
