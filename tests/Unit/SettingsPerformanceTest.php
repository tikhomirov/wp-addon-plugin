<?php

use WpAddon\WP_Addon_Settings;

require_once dirname(__DIR__, 2).'/src/Config/wp-addon-settings.php';

it('does not build admin settings on frontend requests', function () {
    $GLOBALS['mock_is_admin'] = false;

    try {
        $settings = (new ReflectionClass(WP_Addon_Settings::class))->newInstanceWithoutConstructor();
        expect($settings->after_setup_theme())->toBeNull();
    } finally {
        unset($GLOBALS['mock_is_admin']);
    }
});

it('does not fetch the plugin catalog on unrelated admin pages', function () {
    $previous = $_GET;
    $_GET['page'] = 'other-plugin';

    try {
        $settings = (new ReflectionClass(WP_Addon_Settings::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($settings, 'get_plugins_tab_content');
        expect($method->invoke($settings))->toBe('');
    } finally {
        $_GET = $previous;
    }
});
