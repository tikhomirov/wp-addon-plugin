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
        $defaults = $this->getDefaults();

        return filter_var(
            $this->optionService->getSetting('cookie_banner_enabled', $defaults['cookie_banner_enabled']),
            FILTER_VALIDATE_BOOLEAN
        );
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
        $defaults = $this->getDefaults();
        $text = $this->renderText(
            (string) $this->optionService->getSetting('cookie_banner_text', $defaults['cookie_banner_text']),
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
                    <?php echo $this->formatBannerText($text); ?>
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
        <?php $this->renderAnalyticsTemplate(); ?>
        <?php
    }

    public function renderAnalyticsTemplate(): void
    {
        $analyticsCode = $this->getAnalyticsCode();

        if ($analyticsCode === '') {
            return;
        }

        echo '<template id="wp-addon-cookie-analytics-code" hidden>';
        echo $analyticsCode;
        echo '</template>';
    }

    public function getAnalyticsCode(): string
    {
        return trim((string) $this->optionService->getSetting('cookie_banner_analytics_code', ''));
    }

    public function formatBannerText(string $text): string
    {
        if (strpos($text, '🍪') === false) {
            $text = '🍪 '.$text;
        }

        return $text;
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

    private function getDefaults(): array
    {
        if (! function_exists('wp_addon_get_cookie_banner_defaults')) {
            require_once dirname(__DIR__).'/src/Config/cookie-banner-defaults.php';
        }

        return wp_addon_get_cookie_banner_defaults();
    }

    private function getPosition(): string
    {
        $defaults = $this->getDefaults();
        $position = (string) $this->optionService->getSetting('cookie_banner_position', $defaults['cookie_banner_position']);

        return in_array($position, ['bottom-center', 'bottom-left', 'bottom-right'], true)
            ? $position
            : 'bottom-center';
    }

    private function getButtonMode(): string
    {
        $defaults = $this->getDefaults();
        $mode = (string) $this->optionService->getSetting('cookie_banner_button_mode', $defaults['cookie_banner_button_mode']);

        return $mode === 'one' ? 'one' : 'two';
    }

    private function getButtonText(int $number): string
    {
        $defaults = $this->getDefaults();

        if ($number === 1) {
            return (string) $this->optionService->getSetting('cookie_banner_button_1_text', $defaults['cookie_banner_button_1_text']);
        }

        return (string) $this->optionService->getSetting('cookie_banner_button_2_text', $defaults['cookie_banner_button_2_text']);
    }

    private function getLinks(): array
    {
        $defaults = $this->getDefaults();

        return [
            [
                'text' => (string) $this->optionService->getSetting('cookie_banner_link_1_text', $defaults['cookie_banner_link_1_text']),
                'url' => (string) $this->optionService->getSetting('cookie_banner_link_1_url', $defaults['cookie_banner_link_1_url']),
            ],
            [
                'text' => (string) $this->optionService->getSetting('cookie_banner_link_2_text', $defaults['cookie_banner_link_2_text']),
                'url' => (string) $this->optionService->getSetting('cookie_banner_link_2_url', $defaults['cookie_banner_link_2_url']),
            ],
        ];
    }
}
