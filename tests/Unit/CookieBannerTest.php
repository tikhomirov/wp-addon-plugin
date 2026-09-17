<?php

describe('CookieBanner Unit Tests', function () {
    beforeEach(function () {
        $this->mockOptionService = Mockery::mock('WpAddon\Services\OptionService');
        $this->mockOptionService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default = null) {
                $config = [
                    'cookie_banner_enabled' => true,
                    'cookie_banner_position' => 'bottom-center',
                    'cookie_banner_text' => 'Cookie text {link1} and {link2}',
                    'cookie_banner_button_mode' => 'two',
                ];

                return $config[$key] ?? $default;
            });
    });

    afterEach(function () {
        Mockery::close();
    });

    it('initializes when enabled', function () {
        $banner = new CookieBanner($this->mockOptionService);
        $banner->init();

        expect($banner->isEnabled())->toBeTrue();
    });

    it('does not initialize when disabled', function () {
        $mockService = Mockery::mock('WpAddon\Services\OptionService');
        $mockService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default = null) {
                if ($key === 'cookie_banner_enabled') {
                    return false;
                }

                return $default;
            });

        $banner = new CookieBanner($mockService);
        $banner->init();

        expect($banner->isEnabled())->toBeFalse();
    });

    it('renders text template with up to two links', function () {
        $banner = new CookieBanner($this->mockOptionService);

        $result = $banner->renderText('Text {link1} and {link2}', [
            ['text' => 'More', 'url' => '/privacy-policy/'],
            ['text' => 'Terms', 'url' => '/terms/'],
        ]);

        expect($result)->toContain('href="/privacy-policy/"');
        expect($result)->toContain('More');
        expect($result)->toContain('href="/terms/"');
        expect($result)->toContain('Terms');
        expect($result)->not->toContain('{link1}');
        expect($result)->not->toContain('{link2}');
    });
});
