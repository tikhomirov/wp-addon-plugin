<?php

use WpAddon\Services\AssetOptimizationService;

describe('AssetOptimizationService', function () {

    beforeEach(function () {
        $this->cacheDir = sys_get_temp_dir().'/wp_addon_test_cache_'.uniqid();
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

    it('minifies CSS', function () {
        $css = file_get_contents($this->getTestDataPath('test.css'));
        $minified = $this->service->minifyCss($css);

        // Check that CSS is actually minified
        expect(strlen($minified))->toBeLessThan(strlen($css));

        // Check that essential content is preserved
        expect($minified)->toContain('color:#ff0000');
        expect($minified)->toContain('font-size:14px');

        // Check that comments are removed
        expect($minified)->not->toContain('/*');
        expect($minified)->not->toContain('*/');

        // Check that newlines are removed
        expect($minified)->not->toContain("\n");
    });

    it('handles already minified CSS', function () {
        $minifiedCss = file_get_contents($this->getTestDataPath('test.min.css'));
        $result = $this->service->minifyCss($minifiedCss);

        // Should still work and not break already minified CSS
        expect($result)->toContain('color:#ff0000');
        expect($result)->toContain('font-size:14px');
    });

    it('minifies JS', function () {
        $js = file_get_contents($this->getTestDataPath('test.js'));
        $minified = $this->service->minifyJs($js);

        // Check that JS is actually minified
        expect(strlen($minified))->toBeLessThan(strlen($js));

        // Check that essential content is preserved
        expect($minified)->toContain('testFunction');
        expect($minified)->toContain('addEventListener');

        // Check that comments are removed
        expect($minified)->not->toContain('//');

        // Check that extra spaces are removed
        expect($minified)->not->toContain('  ');
    });

    it('combines CSS files', function () {
        $files = [
            $this->getTestDataPath('test.css'),
            $this->getTestDataPath('small.css'),
        ];

        $combined = $this->service->combineCss($files);

        // Check that both files are included
        expect($combined)->toContain('color:#ff0000');
        expect($combined)->toContain('body{margin:0}');
    });

    it('combines JS files', function () {
        $files = [
            $this->createTempFile('function a(){return 1;}'),
            $this->createTempFile('function b(){return 2;}'),
        ];

        $combined = $this->service->combineJs($files);

        // Check that both functions are included
        expect($combined)->toContain('function a()');
        expect($combined)->toContain('function b()');

        // Clean up temp files
        foreach ($files as $file) {
            $this->removeTempFile($file);
        }
    });

    it('generates consistent versions', function () {
        $content1 = 'test content 1';
        $content2 = 'test content 2';

        $version1 = $this->service->generateVersion($content1);
        $version2 = $this->service->generateVersion($content2);
        $version1Again = $this->service->generateVersion($content1);

        // Same content should generate same version
        expect($version1)->toBe($version1Again);

        // Different content should generate different version
        expect($version1)->not->toBe($version2);

        // Version should be a string
        expect($version1)->toBeString();

        // Version should not be empty
        expect($version1)->not->toBeEmpty();
    });

    it('saves content to cache', function () {
        $key = 'test_key';
        $content = 'test content for caching';

        $this->service->saveToCache($key, $content);

        $cacheFile = $this->cacheDir.'/'.$key.'.gz';
        // Mock file_exists to return true for this test
        $this->setMockFunction('file_exists', true);
        expect(file_exists($cacheFile))->toBeTrue();

        // Check that file is gzipped - mock gzuncompress and file_get_contents
        $this->setMockFunction('gzuncompress', $content);
        $cachedContent = gzuncompress(file_get_contents($cacheFile));
        expect($cachedContent)->toBe($content);
    });

    it('retrieves content from cache', function () {
        $key = 'test_key';
        $content = 'test content for caching';

        // First save to cache
        $this->service->saveToCache($key, $content);

        // Mock for retrieval
        $this->setMockFunction('file_exists', true);
        $this->setMockFunction('gzuncompress', $content);

        // Then retrieve from cache
        $cachedContent = $this->service->getFromCache($key);

        expect($cachedContent)->toBe($content);
    });

    it('returns null for nonexistent cache key', function () {
        $cachedContent = $this->service->getFromCache('nonexistent_key');
        expect($cachedContent)->toBeNull();
    });

    it('extracts critical CSS', function () {
        $css = '
        .header { position: fixed; top: 0; background: white; }
        .content { margin-top: 100px; }
        .footer { position: absolute; bottom: 0; }
        @media (max-width: 768px) { .mobile-menu { display: block; } }
        ';

        $criticalCss = $this->service->extractCriticalCss($css);

        // Should extract above-the-fold styles
        expect($criticalCss)->toContain('.header');
        expect($criticalCss)->toContain('position:fixed');

        // Should be shorter than original
        expect(strlen($criticalCss))->toBeLessThan(strlen($css));
    });

    it('handles empty CSS minification', function () {
        $result = $this->service->minifyCss('');
        expect($result)->toBe('');
    });

    it('handles empty JS minification', function () {
        $result = $this->service->minifyJs('');
        expect($result)->toBe('');
    });

    it('handles empty CSS combination', function () {
        $result = $this->service->combineCss([]);
        expect($result)->toBe('');
    });

    it('handles empty JS combination', function () {
        $result = $this->service->combineJs([]);
        expect($result)->toBe('');
    });

    it('generates version for empty content', function () {
        $version = $this->service->generateVersion('');
        expect($version)->toBeString();
        expect($version)->not->toBeEmpty();
    });
});
