<?php

use WpAddon\Autoloader;
use WpAddon\Core\Plugin;

/**
 * Plugin Name:  # WP Excellence Addon
 * Plugin URL:   https://rwsite.ru
 * Description:  Performance, security, and admin enhancements for WordPress 6.6+: 48 WP Tweaker toggles, redirects, cookie banner, Markdown editor, plugin catalog, and comment spam protection.
 * Version:      1.5.1
 * Text Domain:  wp-addon
 * Domain Path: /languages/
 * Author:       Aleksey Tikhomirov
 * Author URI:   https://rwsite.ru
 *
 * Tags: wordpress, wp-addon,
 *
 * Requires at least: 6.6
 * Tested up to:      7.2
 * Requires PHP:      8.2
 */
defined('ABSPATH') || exit;

// Register autoloader
require_once __DIR__.'/src/Autoloader.php';
Autoloader::register();

// Initialize plugin
$plugin = new Plugin(__FILE__);
$plugin->init();
