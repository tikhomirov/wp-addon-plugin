<?php

require_once __DIR__.'/main-settings-helpers.php';

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\HookTrait;

class MaintenanceMode implements ModuleInterface
{
    use HookTrait;

    public function __construct()
    {
        // Constructor
    }

    public function init(): void
    {
        $options = wp_addon_main_settings();

        if (! wp_addon_is_root_setting_enabled('enable_maintenance')) {
            return;
        }

        $this->addHook('template_redirect', [$this, 'checkMaintenance']);
    }

    public function checkMaintenance(): void
    {
        if (is_admin()) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (($GLOBALS['pagenow'] ?? '') === 'wp-login.php') {
            return;
        }

        if (wp_addon_should_bypass_maintenance(wp_addon_main_settings())) {
            return;
        }

        $this->showMaintenancePage();
    }

    public function showMaintenancePage(): void
    {
        add_action('wp_enqueue_scripts', static function () {
            wp_enqueue_style('dashicons');
        });

        $template = $this->getTemplate();

        if ($template === '') {
            $message = trim((string) (wp_addon_main_settings()['maintenance_message'] ?? ''));

            if ($message === '') {
                $message = __('Sorry, the site is temporarily unavailable due to maintenance. Please try again later.', 'wp-addon');
            }

            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php esc_html_e('Maintenance Mode', 'wp-addon'); ?> - <?php bloginfo('name'); ?></title>
                <?php wp_head(); ?>
            </head>
            <body>
                <div style="text-align: center; padding: 50px;">
                    <h1>
                        <span class="dashicons dashicons-admin-tools" style="font-size: 100px; width: 100%; height: 120px;"></span>
                        <span><?php esc_html_e('Maintenance', 'wp-addon'); ?></span>
                    </h1>
                    <p><?php echo wp_kses_post(wpautop($message)); ?></p>
                </div>
            </body>
            </html>
            <?php
        } else {
            echo $template;
        }

        exit;
    }

    public function getTemplate(): string
    {
        return '';
    }
}
