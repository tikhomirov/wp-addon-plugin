<?php

require_once __DIR__ . '/main-settings-helpers.php';

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\HookTrait;

class SiteStatusNotice implements ModuleInterface
{
    use HookTrait;

    public function init(): void
    {
        if (! wp_addon_is_root_setting_enabled('site_status_notice_enabled')) {
            return;
        }

        $this->addHook('rwsite_site_status_notice', [$this, 'render']);
        $this->addHook('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'wp-addon-site-status-notice',
            RW_PLUGIN_URL . 'assets/css/site-status-notice.css',
            [],
            WP_ADDON_VERSION,
        );
    }

    public function render(): void
    {
        if (is_admin()) {
            return;
        }

        $text = mb_trim((string) (wp_addon_main_settings()['site_status_notice_text'] ?? ''));

        if ($text === '') {
            $text = __('The site is in recovery mode.', 'wp-addon');
        }

        ?>
        <aside class="wp-addon-site-status-notice" aria-label="<?php esc_attr_e('Site status', 'wp-addon'); ?>">
            <svg
                class="wp-addon-site-status-notice__icon"
                aria-hidden="true"
                focusable="false"
                width="20"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M9 3h6M10 3v7L4.5 19a1.3 1.3 0 0 0 1.1 2h12.8a1.3 1.3 0 0 0 1.1-2L14 10V3M8 15h8"/>
            </svg>
            <span><?php echo esc_html($text); ?></span>
        </aside>
        <?php
    }
}
