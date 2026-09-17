<?php

use WpAddon\Autoloader;
use WpAddon\Core\Plugin;

/**
 * Plugin Name:  # WP Excellence Addon
 * Plugin URL:   https://rwsite.ru
 * Description:  Performance, security, and admin enhancements for WordPress 6.6+: 48 WP Tweaker toggles, redirects, cookie banner, Markdown editor, and plugin catalog.
 * Version:      1.4.0
 * Text Domain:  wp-addon
 * Domain Path: /languages/
 * Author:       Aleksey Tikhomirov
 * Author URI:   https://rwsite.ru
 *
 * Tags: wordpress, wp-addon,
 *
 * Requires at least: 6.6
 * Tested up to:      6.8
 * Requires PHP:      8.2
 */
defined('ABSPATH') || exit;

// Register autoloader
require_once __DIR__.'/src/Autoloader.php';
Autoloader::register();

// Initialize plugin
$plugin = new Plugin(__FILE__);
$plugin->init();
