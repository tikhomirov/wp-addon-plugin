<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use WpAddon\Services\AssetOptimizationService;
use WpAddon\Services\OptionService;

describe('AssetMinification Real World Integration', function () {
    // Skip tests if WordPress is not loaded (integration environment)
    if (! function_exists('wp_die')) {
        test('skipped - requires WordPress environment', function () {})->skip('WordPress environment not available');

        return;
    }
    beforeEach(function () {
        Monkey\setUp();

        // Minimal mocks for WordPress functions
        Functions\stubs([
            'is_admin'             => false,
            'wp_doing_ajax'        => false,
            'wp_enqueue_style'     => null,
            'wp_enqueue_script'    => null,
            'wp_dequeue_style'     => null,
            'wp_dequeue_script'    => null,
            'wp_add_inline_script' => null,
            'add_action'           => null,
            'site_url'             => function () {
                return 'http://localhost';
            },
            'content_url' => function () {
                return 'http://localhost/wp-content';
            },
            'plugin_dir_path' => function () {
                return '/var/www/no-borders.ru/wp-content/plugins/wp-addon-plugin/';
            },
            'get_template_directory' => function () {
                return '/var/www/no-borders.ru/wp-content/themes/yootheme_child';
            },
        ]);
    });

    afterEach(function () {
        Monkey\tearDown();
    });

    it('activates plugin correctly', function () {
        // Checkpoint 1: Plugin active
        expect(get_option('active_plugins', []))->toContain('wp-addon-plugin/wp-addon-plugin.php');
    });

    it('defines plugin constants', function () {
        // Checkpoint 2: Constants defined
        expect(defined('RW_PLUGIN_DIR'))->toBeTrue();
        expect(defined('RW_PLUGIN_URL'))->toBeTrue();
        expect(defined('RW_FILE'))->toBeTrue();
    });

    it('loads AssetMinification module', function () {
        // Checkpoint 3: Class loads
        expect(class_exists('AssetMinification'))->toBeTrue();
        expect(is_subclass_of('AssetMinification', 'WpAddon\\Interfaces\\ModuleInterface'))->toBeTrue();
    });

    it('loads asset minification config', function () {
        // Checkpoint 4: Config loads
        $optionService = new OptionService;
        $assetMinification = new AssetMinification($optionService);

        Functions\stubs([
            'get_option' => function ($key) {
                return ['wp-addon' => [
                    'asset_minification_enabled'   => true,
                    'asset_minify_css'             => true,
                    'asset_minify_js'              => true,
                    'asset_combine_css'            => true,
                    'asset_combine_js'             => true,
                    'asset_critical_css_enabled'   => true,
                    'asset_defer_non_critical_css' => true,
                    'asset_exclude_css'            => 'admin-bar,dashicons',
                    'asset_exclude_js'             => 'jquery,jquery-core',
                ]];
            },
        ]);

        $assetMinification->init();

        $reflection = new ReflectionClass($assetMinification);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($assetMinification);

        expect($config)->toBeArray();
        expect($config['enabled'])->toBeTrue();
        expect($config['minify_css'])->toBeTrue();
    });

    it('creates cache directory', function () {
        // Checkpoint 5: Cache directory exists
        $cacheDir = WP_CONTENT_DIR . '/cache/assets/';
        expect(is_dir($cacheDir))->toBeTrue();
        expect(is_writable($cacheDir))->toBeTrue();
    });

    it('works with asset optimization service', function () {
        // Checkpoint 6: Optimization service works
        $config = [
            'cache_dir'            => WP_CONTENT_DIR . '/cache/assets/',
            'version_salt'         => 'test',
            'minify_css'           => true,
            'minify_js'            => true,
            'combine_css'          => true,
            'combine_js'           => true,
            'critical_css_enabled' => true,
            'exclude_css'          => [],
            'exclude_js'           => [],
        ];

        $service = new AssetOptimizationService($config);
        expect($service)->toBeInstanceOf('WpAddon\\Services\\AssetOptimizationService');

        // CSS minification test
        $css = '.test { color: red; font-size: 14px; }';
        $minified = $service->minifyCss($css);
        expect($minified)->not->toContain("\n");
        expect($minified)->not->toContain('  ');

        // JS minification test
        $js = 'function test() { return true; }';
        $minifiedJs = $service->minifyJs($js);
        expect($minifiedJs)->not->toContain("\n");

        // Version generation test
        $version1 = $service->generateVersion('content1');
        $version2 = $service->generateVersion('content2');
        expect($version1)->not->toBe($version2);
        expect($version1)->toBeString();
    });

    it('creates cache file for CSS minification', function () {
        // Checkpoint 7: CSS minification creates cache file
        $config = [
            'cache_dir'            => WP_CONTENT_DIR . '/cache/assets/',
            'version_salt'         => 'test',
            'minify_css'           => true,
            'minify_js'            => true,
            'combine_css'          => true,
            'combine_js'           => true,
            'critical_css_enabled' => true,
            'exclude_css'          => [],
            'exclude_js'           => [],
        ];

        $service = new AssetOptimizationService($config);
        $css = '.test { color: red; font-size: 14px; }';
        $version = $service->generateVersion($css);
        $cacheKey = 'test-css-' . $version;
        $service->saveToCache($cacheKey, $css);

        $cacheFile = $config['cache_dir'] . $cacheKey . '.gz';
        expect(file_exists($cacheFile))->toBeTrue();

        // Assert contents
        $cachedContent = $service->getFromCache($cacheKey);
        expect($cachedContent)->toBe($css);

        // Cleanup
        unlink($cacheFile);
    });

    it('creates cache file for JS minification', function () {
        // Checkpoint 8: JS minification creates cache file
        $config = [
            'cache_dir'            => WP_CONTENT_DIR . '/cache/assets/',
            'version_salt'         => 'test',
            'minify_css'           => true,
            'minify_js'            => true,
            'combine_css'          => true,
            'combine_js'           => true,
            'critical_css_enabled' => true,
            'exclude_css'          => [],
            'exclude_js'           => [],
        ];

        $service = new AssetOptimizationService($config);
        $js = 'function test() { return true; }';
        $version = $service->generateVersion($js);
        $cacheKey = 'test-js-' . $version;
        $service->saveToCache($cacheKey, $js);

        $cacheFile = $config['cache_dir'] . $cacheKey . '.gz';
        expect(file_exists($cacheFile))->toBeTrue();
        $cachedContent = $service->getFromCache($cacheKey);
        expect($cachedContent)->toBe($js);
        unlink($cacheFile);
    });

    it('extracts critical CSS', function () {
        // Checkpoint 9: Critical CSS extraction works
        $optionService = new OptionService;
        $assetMinification = new AssetMinification($optionService);

        Functions\stubs([
            'get_option' => function ($key) {
                return ['wp-addon' => ['asset_critical_css_enabled' => true]];
            },
        ]);

        $assetMinification->init();

        ob_start();
        $assetMinification->injectCriticalCss();
        $output = ob_get_clean();

        expect($output)->toContain('<style id="wp-addon-critical-css">');
        expect($output)->toContain('.header');
    });

    it('registers asset processing hooks', function () {
        // Checkpoint 10: Hooks registered
        $optionService = new OptionService;
        $assetMinification = new AssetMinification($optionService);
        $assetMinification->init();

        global $wp_filter;
        expect($wp_filter)->toHaveKey('wp_enqueue_scripts');
        expect($wp_filter)->toHaveKey('wp_head');
    });

    it('shows optimization in HTTP request', function () {
        // Checkpoint 11: HTTP request shows optimization
        $url = site_url('/');

        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get($url);
            if (! is_wp_error($response) && $response['response']['code'] === 200) {
                $html = wp_remote_retrieve_body($response);

                $hasCriticalCss = mb_strpos($html, 'wp-addon-critical-css') !== false;
                $hasOptimizedAssets = mb_strpos($html, 'cache/assets/') !== false;

                expect($hasCriticalCss || $hasOptimizedAssets)->toBeTrue('Page should show signs of AssetMinification optimization');
            }
        }
    });
});
