<?php

/**
 * @group problematic
 */
use WpAddon\Services\MediaCleanupService;

describe('MediaCleanupService', function () {
    // Пропускаем эти тесты в CI из-за проблем с mock'ами
    if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
        test('skipped in CI', function () {})->skip('Mock issues in CI');

        return;
    }
    beforeEach(function () {
        global $mock_functions;
        $mock_functions = [];

        // Mock get_option
        $mock_functions['get_option'] = function ($key, $default = '') {
            $options = [
                'thumbnail_size_w' => 150,
                'thumbnail_size_h' => 150,
                'medium_size_w' => 300,
                'medium_size_h' => 300,
                'large_size_w' => 1024,
                'large_size_h' => 1024,
            ];

            return $options[$key] ?? $default;
        };

        $this->service = new MediaCleanupService;
    });

    it('returns registered sizes', function () {
        $sizes = MediaCleanupService::getRegisteredSizesStatic();
        expect($sizes)->toBeArray();
        expect($sizes)->toContain('150x150');
        expect($sizes)->toContain('300x300');
        expect($sizes)->toContain('1024x1024');
    });

    it('checks if file should be deleted', function () {
        expect($this->service->isFileToDelete('image-400x400.jpg'))->toBeTrue();
        expect($this->service->isFileToDelete('image-scaled.jpg'))->toBeFalse();
        expect($this->service->isFileToDelete('image.jpg'))->toBeFalse();
        expect($this->service->isFileToDelete('image-150x150.jpg'))->toBeFalse();
    });
});
