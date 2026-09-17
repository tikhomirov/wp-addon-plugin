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
                    return '0';
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

    it('keeps pasted analytics markup unchanged in template output', function () {
        $metrikaCode = <<<'HTML'
<!-- Yandex.Metrika counter --> <script type="text/javascript">     (function(m,e,t,r,i,k,a){         m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};         m[i].l=1*new Date();         for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}         k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)     })(window, document,'script','https://mc.yandex.ru/metrika/tag.js', 'ym');      ym(21441994, 'init', {webvisor:true, trackHash:true, referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true}); </script> <noscript><div><img src="https://mc.yandex.ru/watch/21441994" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->
HTML;

        $mockService = Mockery::mock('WpAddon\Services\OptionService');
        $mockService->shouldReceive('getSetting')
            ->andReturnUsing(function ($key, $default = null) use ($metrikaCode) {
                if ($key === 'cookie_banner_analytics_code') {
                    return $metrikaCode;
                }

                return $default;
            });

        $banner = new CookieBanner($mockService);

        ob_start();
        $banner->renderAnalyticsTemplate();
        $output = ob_get_clean();

        expect($banner->getAnalyticsCode())->toBe($metrikaCode);
        expect($output)->toContain('<template id="wp-addon-cookie-analytics-code" hidden>');
        expect($output)->toContain($metrikaCode);
        expect($output)->toContain("ym(21441994, 'init', {webvisor:true, trackHash:true, referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});");
        expect($output)->toContain('<noscript><div><img src="https://mc.yandex.ru/watch/21441994"');
    });

    it('does not render analytics template when code is empty', function () {
        $banner = new CookieBanner($this->mockOptionService);

        ob_start();
        $banner->renderAnalyticsTemplate();
        $output = ob_get_clean();

        expect($output)->toBe('');
    });
});
