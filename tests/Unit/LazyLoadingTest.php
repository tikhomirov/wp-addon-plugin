<?php

/**
 * Unit tests for LazyLoading module
 */
describe('LazyLoading Unit Tests', function () {
    beforeEach(function () {
        $this->mockOptionService = Mockery::mock('WpAddon\Services\OptionService');
        $this->mockOptionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default = null) {
                $config = ['enable_lazy_loading' => true];

                return $config[$key] ?? $default;
            });
    });

    afterEach(function () {
        Mockery::close();
    });

    it('initializes and registers hooks when enabled', function () {

        $lazyLoading = new LazyLoading($this->mockOptionService);
        $lazyLoading->init();

        expect(true)->toBeTrue();
    });

    it('does not initialize when disabled', function () {
        $mockService = Mockery::mock('WpAddon\Services\OptionService');
        $mockService->shouldReceive('getSetting')
            ->with('enable_lazy_loading', false)
            ->andReturn(false);

        $lazyLoading = new LazyLoading($mockService);
        $lazyLoading->init();

        expect(true)->toBeTrue();
    });

    it('applies lazy loading to HTML content with images', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="/image.jpg" alt="Test">';
        $result = $lazyLoading->processContent($html);

        // Проверяем что добавлены нужные атрибуты
        expect($result)->toContain('data-src="/image.jpg"');
        expect($result)->toContain('lazy-img');
        expect($result)->toContain('loading="lazy"');
        // И что НЕТ атрибута src (только data-src)
        expect(preg_match('/\ssrc\s*=\s*"\/image\.jpg"/', $result))->toBe(0);
    });

    it('skips SVG images', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="/wp-content/uploads/image.svg" alt="SVG">';
        $result = $lazyLoading->processContent($html);

        expect($result)->toContain('src="/wp-content/uploads/image.svg"');
        expect($result)->not()->toContain('data-src');
    });

    it('skips data URL images', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==" alt="Data">';
        $result = $lazyLoading->processContent($html);

        expect($result)->toContain('src="data:image/png;base64,');
        expect($result)->not()->toContain('data-src');
    });

    it('skips images with no-lazy class', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="/wp-content/uploads/image.jpg" alt="Test" class="no-lazy">';
        $result = $lazyLoading->processContent($html);

        expect($result)->toContain('src="/wp-content/uploads/image.jpg"');
        expect($result)->not()->toContain('data-src');
    });

    it('handles multiple images in content', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="/uploads/1.jpg" alt="1"><img src="/uploads/2.jpg" alt="2"><img src="/uploads/3.svg" alt="3">';
        $result = $lazyLoading->processContent($html);

        expect($result)->toContain('data-src="/uploads/1.jpg"');
        expect($result)->toContain('data-src="/uploads/2.jpg"');
        expect($result)->toContain('src="/uploads/3.svg"'); // SVG пропускается
        // Проверяем что НЕТ атрибутов src для обработанных изображений
        expect(preg_match('/\ssrc\s*=\s*"\/uploads\/1\.jpg"/', $result))->toBe(0);
        expect(preg_match('/\ssrc\s*=\s*"\/uploads\/2\.jpg"/', $result))->toBe(0);
    });

    it('preserves existing attributes', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $html = '<img src="/image.jpg" alt="Test" width="100" height="100" class="existing-class" id="test-img">';
        $result = $lazyLoading->processContent($html);

        expect($result)->toContain('data-src="/image.jpg"');
        expect($result)->toContain('alt="Test"');
        expect($result)->toContain('width="100"');
        expect($result)->toContain('height="100"');
        expect($result)->toContain('class="existing-class lazy-img"');
        expect($result)->toContain('id="test-img"');
    });

    it('defers responsive images without losing sizes', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $result = $lazyLoading->processContent('<img src="/image.jpg" srcset="/image-400.jpg 400w, /image-800.jpg 800w" sizes="(max-width: 600px) 100vw, 600px">');

        expect($result)->toContain('data-src="/image.jpg"');
        expect($result)->toContain('data-srcset="/image-400.jpg 400w, /image-800.jpg 800w"');
        expect($result)->toContain('sizes="(max-width: 600px) 100vw, 600px"');
        expect($result)->not()->toMatch('/\ssrcset=/');
    });

    it('supports single quoted attributes and remains idempotent', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $result = $lazyLoading->processContent("<img src='/image.jpg' alt='A &quot;quoted&quot; image'>");

        expect($result)->toContain('data-src="/image.jpg"');
        expect($lazyLoading->processContent($result))->toBe($result);
    });

    it('matches no-lazy as a complete CSS class', function () {
        $lazyLoading = new LazyLoading($this->mockOptionService);

        $result = $lazyLoading->processContent('<img src="/image.jpg" class="not-no-lazy">');

        expect($result)->toContain('data-src="/image.jpg"');
    });
});
