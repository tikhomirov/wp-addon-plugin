<?php

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
        $options = get_option('wp-addon', []);
        if (! empty($options['enable_maintenance'])) {
            $this->addHook('template_redirect', [$this, 'checkMaintenance']);
        }
    }

    public function checkMaintenance()
    {
        // Allow admin access
        if (is_admin()) {
            return;
        }

        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Allow REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        // Allow login page
        if ($GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }

        if (! current_user_can('manage_options')) {
            $this->showMaintenancePage();
        }
    }

    public function showMaintenancePage()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style('dashicons');
        });

        if (empty($template = $this->getTemplate())) {
            do_action('wp_head');
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php _e('Maintenance Mode', 'wp-addon'); ?> - <?php bloginfo('name'); ?></title>
                <?php wp_head(); ?>
            </head>
            <body>
                <div style="text-align: center; padding: 50px;">
                    <h1>
                        <span class="dashicons dashicons-admin-tools" style="font-size: 100px; width: 100%; height: 120px;"></span>
                        <span><?php _e('Технические работы.', 'wp-addon'); ?></span>
                    </h1>
                    <p>
                        <?php _e('Извините, в настоящий момент на сайте проводятся технические работы.', 'wp-addon'); ?><br>
                        <?php _e('Попробуйте зайти позднее.', 'wp-addon'); ?>
                    </p>
                </div>
            </body>
            </html>
            <?php
        } else {
            echo $template;
        }

        exit;
    }

    public function getTemplate()
    {
        return $html ?? '';
    }
}
