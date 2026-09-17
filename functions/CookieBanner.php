<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\OptionService;
use WpAddon\Traits\HookTrait;

class CookieBanner implements ModuleInterface
{
    use HookTrait;

    private OptionService $optionService;

    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
    }

    public function init(): void
    {
        if (! $this->isEnabled() || (function_exists('is_admin') && is_admin())) {
            return;
        }

        $this->addHook('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        $this->addHook('wp_footer', [$this, 'renderBanner'], 5);
    }

    public function isEnabled(): bool
    {
        $value = $this->optionService->getSetting('cookie_banner_enabled', true);

        if ($value === '' || $value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_style(
            'wp-addon-cookie-banner',
            RW_PLUGIN_URL.'assets/css/cookie-banner.css',
            [],
            WP_ADDON_VERSION
        );

        wp_enqueue_script(
            'wp-addon-cookie-banner',
            RW_PLUGIN_URL.'assets/js/cookie-banner.js',
            [],
            WP_ADDON_VERSION,
            true
        );

        wp_localize_script('wp-addon-cookie-banner', 'wpAddonCookieBanner', [
            'buttonMode' => $this->getButtonMode(),
            'analyticsCode' => $this->getAnalyticsCode(),
            'storageKey' => 'cookie_consent',
            'dateKey' => 'cookie_consent_date',
        ]);
    }

    public function renderBanner(): void
    {
        $position = $this->getPosition();
        $buttonMode = $this->getButtonMode();
        $button1Text = $this->getButtonText(1);
        $button2Text = $this->getButtonText(2);
        $text = $this->renderText(
            (string) $this->optionService->getSetting('cookie_banner_text', __('🍪 Сайт использует cookie для работы и аналитики. {link1}', 'wp-addon')),
            $this->getLinks()
        );

        ?>
        <div
            id="wp-addon-cookie-banner"
            class="wp-addon-cookie-banner wp-addon-cookie-banner--<?php echo esc_attr($position); ?>"
            role="dialog"
            aria-live="polite"
            aria-label="<?php esc_attr_e('Cookie consent', 'wp-addon'); ?>"
        >
            <div class="wp-addon-cookie-banner__inner">
                <div class="wp-addon-cookie-banner__text">
                    <?php echo $text; ?>
                </div>
                <div class="wp-addon-cookie-banner__actions">
                    <?php if ($buttonMode === 'two') { ?>
                        <button
                            type="button"
                            class="wp-addon-cookie-banner__button wp-addon-cookie-banner__button--secondary"
                            data-cookie-consent="essential"
                        >
                            <?php echo esc_html($button1Text); ?>
                        </button>
                        <button
                            type="button"
                            class="wp-addon-cookie-banner__button wp-addon-cookie-banner__button--primary"
                            data-cookie-consent="all"
                        >
                            <?php echo esc_html($button2Text); ?>
                        </button>
                    <?php } else { ?>
                        <button
                            type="button"
                            class="wp-addon-cookie-banner__button wp-addon-cookie-banner__button--primary"
                            data-cookie-consent="accepted"
                        >
                            <?php echo esc_html($button1Text); ?>
                        </button>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderText(string $template, array $links): string
    {
        $text = $template;

        foreach ($links as $index => $link) {
            $placeholder = '{link'.($index + 1).'}';
            $replacement = '';

            if (! empty($link['url']) && ! empty($link['text'])) {
                $replacement = sprintf(
                    '<a href="%s" class="wp-addon-cookie-banner__link">%s</a>',
                    esc_url($link['url']),
                    esc_html($link['text'])
                );
            }

            $text = str_replace($placeholder, $replacement, $text);
        }

        $text = preg_replace('/\{link[12]\}/', '', $text) ?? $text;

        return function_exists('wp_kses')
            ? wp_kses($text, [
                'a' => [
                    'href' => true,
                    'class' => true,
                ],
            ])
            : $text;
    }

    private function getPosition(): string
    {
        $position = (string) $this->optionService->getSetting('cookie_banner_position', 'bottom-center');

        return in_array($position, ['bottom-center', 'bottom-left', 'bottom-right'], true)
            ? $position
            : 'bottom-center';
    }

    private function getButtonMode(): string
    {
        $mode = (string) $this->optionService->getSetting('cookie_banner_button_mode', 'two');

        return $mode === 'one' ? 'one' : 'two';
    }

    private function getButtonText(int $number): string
    {
        if ($number === 1) {
            $default = $this->getButtonMode() === 'one'
                ? __('Принять', 'wp-addon')
                : __('Обязательные', 'wp-addon');

            return (string) $this->optionService->getSetting('cookie_banner_button_1_text', $default);
        }

        return (string) $this->optionService->getSetting('cookie_banner_button_2_text', __('Принять все', 'wp-addon'));
    }

    private function getLinks(): array
    {
        return [
            [
                'text' => (string) $this->optionService->getSetting('cookie_banner_link_1_text', __('Подробнее', 'wp-addon')),
                'url' => (string) $this->optionService->getSetting('cookie_banner_link_1_url', '/privacy-policy/'),
            ],
            [
                'text' => (string) $this->optionService->getSetting('cookie_banner_link_2_text', ''),
                'url' => (string) $this->optionService->getSetting('cookie_banner_link_2_url', ''),
            ],
        ];
    }

    private function getAnalyticsCode(): string
    {
        $default = "(function(m,e,t,r,i,k,a){\n"
            ."  m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};\n"
            ."  m[i].l=1*new Date();\n"
            ."  for(var j=0;j<document.scripts.length;j++){\n"
            ."    if(document.scripts[j].src===r){return;}\n"
            ."  }\n"
            ."  k=e.createElement(t),a=e.getElementsByTagName(t)[0];\n"
            ."  k.async=1;k.src=r;a.parentNode.insertBefore(k,a)\n"
            .'})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");\n\n'
            .'ym(21441994,"init",{\n'
            .'  clickmap:true,\n'
            .'  trackLinks:true,\n'
            .'  accurateTrackBounce:true,\n'
            .'  webvisor:true,\n'
            .'  trackHash:true\n'
            .'});';

        return trim((string) $this->optionService->getSetting('cookie_banner_analytics_code', $default));
    }
}
