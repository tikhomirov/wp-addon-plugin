<?php

use WpAddon\WP_Addon_Settings;

if (! defined('ABSPATH')) {
    define('ABSPATH', '/var/www/');
}

if (! defined('RW_PLUGIN_URL')) {
    define('RW_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-addon-plugin/');
}

if (! defined('RW_PLUGIN_DIR')) {
    define('RW_PLUGIN_DIR', dirname(__DIR__, 2).'/');
}

if (! defined('RW_FILE')) {
    define('RW_FILE', dirname(__DIR__, 2).'/wp-addon-plugin.php');
}

require_once dirname(__DIR__, 2).'/src/Config/wp-addon-settings.php';

describe('WP_Addon_Settings assets enqueue', function () {
    it('initializes slug and name correctly', function () {
        $settings = WP_Addon_Settings::getInstance();

        expect($settings->wp_plugin_slug)->toBe('wp-addon');
        expect($settings->wp_plugin_name)->not->toBeEmpty();
    });

    it('safely handles null or unrelated admin pages without deprecation or error', function () {
        $settings = WP_Addon_Settings::getInstance();

        // Should return early and not raise errors
        $settings->admin_assets(null);
        $settings->admin_assets('');
        $settings->admin_assets('index.php');
        $settings->admin_assets('plugins.php');

        expect(true)->toBeTrue();
    });
});
