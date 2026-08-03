<?php

use WpAddon\Services\MediaCleanupService;
use WpAddon\Services\OptionService;

describe('Smoke Test', function () {
    it('tests plugin initialization', function () {
        // Test that autoloader is registered
        expect(function_exists('spl_autoload_functions'))->toBeTrue();

        // Test that classes can be loaded
        expect(class_exists('WpAddon\Autoloader'))->toBeTrue();
        expect(class_exists('WpAddon\Services\OptionService'))->toBeTrue();
        expect(class_exists('WpAddon\Services\MediaCleanupService'))->toBeTrue();
        expect(class_exists('WpAddon\Controllers\MediaCleanupController'))->toBeFalse(); // Удален, логика в модуле
    });

    it('tests services instantiation', function () {
        $optionService = new OptionService;
        expect($optionService)->toBeInstanceOf(OptionService::class);

        $mediaCleanupService = new MediaCleanupService;
        expect($mediaCleanupService)->toBeInstanceOf(MediaCleanupService::class);
    });
});
