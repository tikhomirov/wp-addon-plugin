<?php

use WpAddon\Services\AssetOptimizationService;

describe('SimpleAssetOptimizationService', function () {

    beforeEach(function () {
        $this->cacheDir = sys_get_temp_dir().'/wp_addon_simple_test_cache_'.uniqid();
        mkdir($this->cacheDir, 0755, true);

        $config = [
            'cache_dir' => $this->cacheDir,
            'version_salt' => 'test-salt',
            'minify_css' => true,
            'minify_js' => true,
            'combine_css' => true,
            'combine_js' => true,
            'critical_css_enabled' => true,
            'exclude_css' => [],
            'exclude_js' => [],
        ];

        $this->service = new AssetOptimizationService($config);
    });

    afterEach(function () {
        // Clean up cache directory
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir.'/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->cacheDir);
        }
    });

    it('minifies basic CSS', function () {
        $css = '
        .test-class {
            color: #ff0000;
            font-size: 14px;
        }
        .another { margin: 0; }
        ';

        $minified = $this->service->minifyCss($css);

        // Check that CSS is minified
        expect(strpos($minified, "\n"))->toBeFalse();
        expect(strpos($minified, '  '))->toBeFalse();
        expect(strpos($minified, '.test-class{color:#ff0000;font-size:14px}.another{margin:0}'))->not->toBeFalse();
    });

    it('removes comments from CSS', function () {
        $css = '/* This is a comment */ .test { color: red; } /* Another comment */';
        $minified = $this->service->minifyCss($css);

        expect(strpos($minified, '/*'))->toBeFalse();
        expect(strpos($minified, '*/'))->toBeFalse();
        expect(strpos($minified, '.test{color:red}'))->not->toBeFalse();
    });

    it('minifies basic JS', function () {
        $js = '
        function test() {
            var a = 1;
            return a + 2;
        }
        ';

        $minified = $this->service->minifyJs($js);

        // Check that JS is minified
        expect(strpos($minified, "\n"))->toBeFalse();
        expect(strpos($minified, '  '))->toBeFalse();
        expect(strpos($minified, 'function test(){var a=1;return a+2;}'))->not->toBeFalse();
    });

    it('removes comments from JS', function () {
        $js = '/* Block comment */ function test() { return 1; } // Line comment';
        $minified = $this->service->minifyJs($js);

        expect(strpos($minified, '/*'))->toBeFalse();
        expect(strpos($minified, '*/'))->toBeFalse();
        expect(strpos($minified, '//'))->toBeFalse();
        expect(strpos($minified, 'function test(){return 1;}'))->not->toBeFalse();
    });

    it('generates version', function () {
        $content1 = 'test content';
        $content2 = 'different content';

        $version1 = $this->service->generateVersion($content1);
        $version2 = $this->service->generateVersion($content2);

        expect(strlen($version1))->toBe(8); // Should be 8 characters
        expect($version1)->toBeString();
        expect($version1)->not->toBe($version2); // Different content = different version
    });

    it('generates same version for same content', function () {
        $content = 'test content';

        $version1 = $this->service->generateVersion($content);
        $version2 = $this->service->generateVersion($content);

        expect($version1)->toBe($version2); // Same content = same version
    });

    it('saves and gets from cache', function () {
        $key = 'test_key';
        $content = 'test content for caching';

        // Save to cache
        $this->service->saveToCache($key, $content);

        // Get from cache
        $cachedContent = $this->service->getFromCache($key);

        expect($cachedContent)->toBe($content);
    });

    it('returns null for nonexistent cache key', function () {
        $cachedContent = $this->service->getFromCache('nonexistent_key');
        expect($cachedContent)->toBeNull();
    });

    it('extracts critical CSS', function () {
        $css = '
        .header { position: fixed; background: white; }
        .content { margin-top: 100px; }
        .footer { position: absolute; bottom: 0; }
        .nav { display: block; }
        ';

        $criticalCss = $this->service->extractCriticalCss($css);

        // Should extract header, nav and footer as critical
        expect(strpos($criticalCss, '.header'))->not->toBeFalse();
        expect(strpos($criticalCss, '.nav'))->not->toBeFalse();
        expect(strpos($criticalCss, '.footer'))->not->toBeFalse();

        // Should not contain content
        expect(strpos($criticalCss, '.content'))->toBeFalse();
    });

    it('combines CSS files', function () {
        // Create temporary CSS files
        $css1 = $this->createTempFile('.class1 { color: red; }');
        $css2 = $this->createTempFile('.class2 { color: blue; }');

        $combined = $this->service->combineCss([$css1, $css2]);

        // Should contain both classes
        expect(strpos($combined, '.class1{color:red}'))->not->toBeFalse();
        expect(strpos($combined, '.class2{color:blue}'))->not->toBeFalse();

        // Clean up
        unlink($css1);
        unlink($css2);
    });

    it('combines JS files', function () {
        // Create temporary JS files
        $js1 = $this->createTempFile('function a() { return 1; }');
        $js2 = $this->createTempFile('function b() { return 2; }');

        $combined = $this->service->combineJs([$js1, $js2]);

        // Should contain both functions
        expect(strpos($combined, 'function a(){return 1;};'))->not->toBeFalse();
        expect(strpos($combined, 'function b(){return 2;};'))->not->toBeFalse();

        // Clean up
        unlink($js1);
        unlink($js2);
    });
});
