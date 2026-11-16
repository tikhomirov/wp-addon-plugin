<?php

use WpAddon\ControllerWP;
use WpAddon\Services\OptionService;

// Запуск: make wp c='eval-file wp-content/plugins/wp-addon-plugin/tests/Integration/tweaks.php'
// Меняет только хуки текущего процесса, не настройки и не данные сайта.

$check = static function (string $name, bool $condition): void {
    if (! $condition) {
        throw new RuntimeException($name);
    }

    echo "PASS: {$name}\n";
};

$check('Trash retention', EMPTY_TRASH_DAYS === 14);
$check('Emoji styles', has_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles') === false);
$check('Taxonomy order', apply_filters('wp_terms_checklist_args', ['checked_ontop' => true])['checked_ontop'] === false);

$host = wp_parse_url(home_url(), PHP_URL_HOST);
$links = [home_url('/test'), "https://example.org/?next={$host}", "https://{$host}.example.org/test"];
wptweaker_remove_self_pingbacks($links);
$check('Self pingbacks only', array_keys($links) === [1, 2]);

require_once ABSPATH.WPINC.'/class-IXR.php';
require_once ABSPATH.WPINC.'/class-wp-xmlrpc-server.php';
$server = new wp_xmlrpc_server;
$check('XML-RPC publishing and pingbacks', ! isset($server->methods['pingback.ping']) && ! isset($server->methods['wp.getPosts']));

$_SERVER['HTTP_USER_AGENT'] = 'WpAddonRegressionTest';
wptweaker_setting_36();
require_once ABSPATH.'wp-admin/includes/dashboard.php';
$check('Browser check short circuit', wp_check_browser_version() === []);

add_action('login_footer', 'wp_shake_js', 12);
wpt_login_shake();
$check('Login shake', has_action('login_footer', 'wp_shake_js') === false);

wptweaker_setting_24();
unregister_basic_widgets();
$check('Widget removal callback', ! isset($GLOBALS['wp_widget_factory']->widgets['WP_Widget_Pages']));

$controller = new ControllerWP(new OptionService('wp-addon'));
$run = new ReflectionMethod($controller, 'run');
$run->invoke($controller, 'wptweaker_setting_24');
$check('Widget removal registered before core init', has_action('init', 'wptweaker_setting_24') === 0);
