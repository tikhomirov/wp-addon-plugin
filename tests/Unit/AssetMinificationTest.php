<?php

beforeAll(function () {
    if (! function_exists('site_url')) {
        function site_url()
        {
            return 'http://localhost';
        }
    }
});

/**
 * Test AssetMinification
 *
 * @group problematic
 */
describe('AssetMinification', function () {
    // Пропускаем эти тесты в CI из-за сложных зависимостей
    if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
        test('skipped in CI', function () {})->skip('Complex dependencies in CI');

        return;
    }

    it('creates cache files for CSS assets', function () {
        $cacheDir = sys_get_temp_dir().'/wp_addon_cache/';
        $optionService = Mockery::mock('\\WpAddon\\Services\\OptionService');
        $assetMinification = new AssetMinification($optionService);

        // Mock config
        $optionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default) {
                $config = [
                    'asset_minification_enabled' => true,
                    'asset_minify_css' => true,
                    'asset_minify_js' => false,
                    'asset_combine_css' => false,
                    'asset_combine_js' => false,
                    'asset_critical_css_enabled' => false,
                    'asset_defer_non_critical_css' => false,
                    'asset_exclude_css' => '',
                    'asset_exclude_js' => '',
                    'cache_dir' => sys_get_temp_dir().'/wp_addon_cache',
                    'version_salt' => 'wp-addon-v1',
                ];

                return $config[$key] ?? $default;
            });

        // Mock WordPress globals
        global $wp_styles;
        $wp_styles = new stdClass;
        $wp_styles->queue = ['test-style'];

        // Create test CSS file
        $cssContent = str_repeat(".test {\n    color: red;\n    font-size: 14px;\n    margin: 10px;\n    padding: 5px;\n}\n", 20); // CSS with newlines, > 1KB
        $testCssPath = sys_get_temp_dir().'/test.css';
        file_put_contents($testCssPath, $cssContent);

        $wp_styles->registered = [
            'test-style' => (object) [
                'handle' => 'test-style',
                'src' => 'http://localhost/test.css',
                'deps' => [],
                'ver' => '1.0.0',
                'args' => 'all',
            ],
        ];

        // Mock ABSPATH for urlToPath
        define('ABSPATH', sys_get_temp_dir().'/');

        // Ensure cache dir exists
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Initialize
        $assetMinification->init();

        // Process assets
        $assetMinification->processAssets();

        // Check if cache file was created
        $files = glob($cacheDir.'*.gz');

        expect(count($files))->toBeGreaterThan(0, 'Cache file should be created');

        // Clean up
        foreach ($files as $file) {
            unlink($file);
        }
        unlink($testCssPath);
    });

    it('creates cache files for JS assets', function () {
        $cacheDir = sys_get_temp_dir().'/wp_addon_cache/';
        $optionService = Mockery::mock('\\WpAddon\\Services\\OptionService');
        $assetMinification = new AssetMinification($optionService);

        // Mock config for JS
        $optionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default) {
                $config = [
                    'asset_minification_enabled' => true,
                    'asset_minify_css' => false,
                    'asset_minify_js' => true,
                    'asset_combine_css' => false,
                    'asset_combine_js' => false,
                    'asset_critical_css_enabled' => false,
                    'asset_defer_non_critical_css' => false,
                    'asset_exclude_css' => '',
                    'asset_exclude_js' => '',
                    'cache_dir' => sys_get_temp_dir().'/wp_addon_cache',
                    'version_salt' => 'wp-addon-v1',
                ];

                return $config[$key] ?? $default;
            });

        // Mock WordPress globals
        global $wp_scripts;
        $wp_scripts = new stdClass;
        $wp_scripts->queue = ['test-script'];

        // Create test JS file
        $jsContent = str_repeat("function test() {\n    console.log('test');\n    return true;\n}\n", 20); // JS with newlines
        $testJsPath = sys_get_temp_dir().'/test.js';
        file_put_contents($testJsPath, $jsContent);

        $wp_scripts->registered = [
            'test-script' => (object) [
                'handle' => 'test-script',
                'src' => 'http://localhost/test.js',
                'deps' => [],
                'ver' => '1.0.0',
                'args' => false,
            ],
        ];

        // Mock ABSPATH for urlToPath
        define('ABSPATH', sys_get_temp_dir().'/');

        // Ensure cache dir exists
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Initialize
        $assetMinification->init();

        // Process assets
        $assetMinification->processAssets();

        // Check if cache file was created
        $files = glob($cacheDir.'*.gz');

        expect(count($files))->toBeGreaterThan(0, 'Cache file should be created');

        // Clean up
        foreach ($files as $file) {
            unlink($file);
        }
        unlink($testJsPath);
    });
});
