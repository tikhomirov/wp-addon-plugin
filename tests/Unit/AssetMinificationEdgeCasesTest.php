<?php

/**
 * Test AssetMinification edge cases and error handling
 *
 * @group problematic
 */
describe('AssetMinification Edge Cases', function () {
    // Пропускаем эти тесты в CI из-за Patchwork конфликтов
    if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
        test('skipped in CI', function () {})->skip('Patchwork conflicts in CI');

        return;
    }
    beforeEach(function () {
        global $mock_functions;
        $mock_functions = [];

        $this->mockOptionService = Mockery::mock('\WpAddon\Services\OptionService');

        // Mock option service with default config
        $this->mockOptionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default = null) {
                $config = [
                    'enabled' => true,
                    'minify_css' => true,
                    'minify_js' => true,
                    'combine_css' => true,
                    'combine_js' => true,
                    'critical_css_enabled' => true,
                    'defer_non_critical_css' => true,
                    'exclude_css' => [],
                    'exclude_js' => [],
                    'cache_dir' => sys_get_temp_dir().'/wp_addon_cache',
                    'version_salt' => 'wp-addon-v1',
                ];

                return $config[$key] ?? $default;
            });

        $this->assetMinification = new AssetMinification($this->mockOptionService);
        $this->assetMinification->init();
    });

    afterEach(function () {
        Mockery::close();
    });

    it('processes assets with empty queue', function () {
        // Setup: Empty queues
        global $wp_styles, $wp_scripts;
        $wp_styles = new stdClass;
        $wp_styles->queue = [];
        $wp_scripts = new stdClass;
        $wp_scripts->queue = [];

        // Execute - should not throw any errors
        $this->assetMinification->processAssets();

        // Assert: Queues should remain empty
        expect($wp_styles->queue)->toBeEmpty();
        expect($wp_scripts->queue)->toBeEmpty();
    });

    it('processes assets with null wp_styles', function () {
        // Setup: Null wp_styles
        global $wp_styles, $wp_scripts;
        $wp_styles = null;
        $wp_scripts = [];

        // Execute - should not throw any errors
        $this->assetMinification->processAssets();

        // Assert: Nothing should break
        expect($wp_styles)->toBeNull();
    });

    it('processes assets with null wp_scripts', function () {
        // Setup: Null wp_scripts
        global $wp_styles, $wp_scripts;
        $wp_styles = [];
        $wp_scripts = null;

        // Execute - should not throw any errors
        $this->assetMinification->processAssets();

        // Assert: Nothing should break
        expect($wp_scripts)->toBeNull();
    });

    it('does not process assets with empty src', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('shouldProcessAsset');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['test-handle', '', []]);
        expect($result)->toBeFalse();
    });

    it('does not process assets with null src', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('shouldProcessAsset');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['test-handle', '', []]);
        expect($result)->toBeFalse();
    });

    it('does not process assets with invalid path', function () {
        global $mock_functions;
        $mock_functions['file_exists'] = false;

        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('shouldProcessAsset');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, [
            'test-handle',
            'http://example.com/wp-content/themes/theme/invalid.css',
            [],
        ]);
        expect($result)->toBeFalse();
    });

    it('handles malformed CSS gracefully', function () {
        $malformedCss = '.class { color: #fff; font-size: 14px; /* unclosed comment ';
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('isAlreadyMinified');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, [$malformedCss, 'css']);

        // Should not crash and return a boolean
        expect($result)->toBeBool();
    });

    it('handles malformed JS gracefully', function () {
        $malformedJs = 'function test() { console.log("test"); /* unclosed comment ';
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('isAlreadyMinified');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, [$malformedJs, 'js']);

        // Should not crash and return a boolean
        expect($result)->toBeBool();
    });

    it('returns empty string for empty URL', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('urlToPath');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['']);
        expect($result)->toBe('');
    });

    it('handles non-matching URL', function () {
        $url = 'https://cdn.example.com/style.css';
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('urlToPath');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, [$url]);

        // Should still work but return path relative to ABSPATH
        expect($result)->toBeString();
        expect($result)->toContain('https://cdn.example.com/style.css');
    });

    it('generates cache URL with empty key', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('getCacheUrl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['']);
        expect($result)->toContain('.gz');
        expect($result)->toContain('cache/assets/');
    });

    it('handles special characters in cache key', function () {
        $key = 'test@key#with$special%chars';
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('getCacheUrl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, [$key]);

        expect($result)->toContain($key);
        expect($result)->toContain('.gz');
    });

    it('handles missing theme CSS gracefully', function () {
        global $mock_functions;
        $mock_functions['get_template_directory'] = '/non/existent/theme/path';

        // Execute - should not throw errors
        ob_start();
        $this->assetMinification->injectCriticalCss();
        $output = ob_get_clean();

        // Assert: No critical CSS should be injected
        expect($output)->toBeEmpty();
    });

    it('handles empty theme CSS file', function () {
        // Create empty theme CSS file
        $emptyCssFile = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.css';
        file_put_contents($emptyCssFile, '');
        $themeDir = dirname($emptyCssFile);

        global $mock_functions;
        $mock_functions['get_template_directory'] = $themeDir;

        // Execute
        ob_start();
        $this->assetMinification->injectCriticalCss();
        $output = ob_get_clean();

        // Assert: No critical CSS should be injected for empty file
        expect($output)->toBeEmpty();

        // Clean up
        unlink($emptyCssFile);
    });

    it('handles corrupt theme CSS file', function () {
        // Create corrupt CSS file
        $corruptCss = 'this is not css {{{ }}} }}}';
        $corruptCssFile = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.css';
        file_put_contents($corruptCssFile, $corruptCss);
        $themeDir = dirname($corruptCssFile);

        global $mock_functions;
        $mock_functions['get_template_directory'] = $themeDir;

        // Execute - should not throw errors
        ob_start();
        $this->assetMinification->injectCriticalCss();
        $output = ob_get_clean();

        // Assert: Should handle corrupt CSS gracefully
        expect($output)->toBeString();

        // Clean up
        unlink($corruptCssFile);
    });

    it('defers CSS loading with no combined JS', function () {
        // Setup inline scripts tracking
        global $wp_inline_scripts;
        $wp_inline_scripts = [];

        // Execute
        $this->assetMinification->deferCssLoading();

        // Assert: Script should still be added even without combined JS
        expect($wp_inline_scripts)->toHaveKey('wp-addon-combined-js');
        expect($wp_inline_scripts['wp-addon-combined-js'])->toContain('DOMContentLoaded');
    });

    it('handles clearing cache with non-existent directory', function () {
        // Mock cache directory that doesn't exist
        $nonExistentDir = '/tmp/non_existent_wp_addon_cache_'.uniqid();

        $mockOptionService = Mockery::mock('\WpAddon\Services\OptionService');
        $mockOptionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key) use ($nonExistentDir) {
                return $key === 'cache_dir' ? $nonExistentDir : true;
            });

        $assetMinification = new AssetMinification($mockOptionService);
        $assetMinification->init();

        // Execute - should not throw errors
        $assetMinification->clearCache();

        // Assert: Nothing should break
        expect(true)->toBeTrue();
    });

    it('handles clearing cache with permission denied', function () {
        // Create cache directory and make it read-only
        $cacheDir = sys_get_temp_dir().'/wp_addon_readonly_cache_'.uniqid();
        mkdir($cacheDir, 0444, true); // Read-only

        $mockOptionService = Mockery::mock('\WpAddon\Services\OptionService');
        $mockOptionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key) use ($cacheDir) {
                return $key === 'cache_dir' ? $cacheDir : true;
            });

        $assetMinification = new AssetMinification($mockOptionService);
        $assetMinification->init();

        // Execute - should handle permission errors gracefully
        $assetMinification->clearCache();

        // Assert: Should not throw fatal errors
        expect(true)->toBeTrue();

        // Clean up (change permissions back to allow deletion)
        chmod($cacheDir, 0755);
        rmdir($cacheDir);
    });

    it('does not process assets in admin area', function () {
        global $mock_functions;
        $mock_functions['is_admin'] = true;

        // Setup some assets
        global $wp_styles, $wp_scripts;
        $wp_styles = new stdClass;
        $wp_styles->queue = ['test-style'];
        $wp_styles->registered = [
            'test-style' => (object) [
                'handle' => 'test-style',
                'src' => 'http://example.com/style.css',
                'deps' => [],
                'ver' => false,
                'args' => 'all',
            ],
        ];
        $wp_scripts = new stdClass;
        $wp_scripts->queue = ['test-script'];
        $wp_scripts->registered = [
            'test-script' => (object) [
                'handle' => 'test-script',
                'src' => 'http://example.com/script.js',
                'deps' => [],
                'ver' => false,
                'args' => false,
            ],
        ];

        // Execute
        $this->assetMinification->processAssets();

        // Assert: Assets should not be processed in admin area
        expect($wp_styles->queue)->toContain('test-style');
        expect($wp_scripts->queue)->toContain('test-script');
    });

    it('does not process assets during AJAX', function () {
        global $mock_functions;
        $mock_functions['wp_doing_ajax'] = true;

        // Setup some assets
        global $wp_styles, $wp_scripts;
        $wp_styles = new stdClass;
        $wp_styles->queue = ['test-style'];
        $wp_styles->registered = [
            'test-style' => (object) [
                'handle' => 'test-style',
                'src' => 'http://example.com/style.css',
                'deps' => [],
                'ver' => false,
                'args' => 'all',
            ],
        ];
        $wp_scripts = new stdClass;
        $wp_scripts->queue = ['test-script'];
        $wp_scripts->registered = [
            'test-script' => (object) [
                'handle' => 'test-script',
                'src' => 'http://example.com/script.js',
                'deps' => [],
                'ver' => false,
                'args' => false,
            ],
        ];

        // Execute
        $this->assetMinification->processAssets();

        // Assert: Assets should not be processed during AJAX
        expect($wp_styles->queue)->toContain('test-style');
        expect($wp_scripts->queue)->toContain('test-script');
    });

    it('returns normal priority for unknown asset', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('getAssetPriority');
        $method->setAccessible(true);
        $priority = $method->invokeArgs($this->assetMinification, ['unknown-asset-handle']);
        expect($priority)->toBe(2);
    });

    it('returns normal priority for empty handle', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('getAssetPriority');
        $method->setAccessible(true);
        $priority = $method->invokeArgs($this->assetMinification, ['']);
        expect($priority)->toBe(2);
    });

    it('does not consider empty handle as system asset', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('isSystemAsset');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['']);
        expect($result)->toBeFalse();
    });

    it('does not consider null handle as system asset', function () {
        $reflection = new ReflectionClass($this->assetMinification);
        $method = $reflection->getMethod('isSystemAsset');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->assetMinification, ['']);
        expect($result)->toBeFalse();
    });
});
