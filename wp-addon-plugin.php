<?php
/**
 * Plugin Name:  # WP Excellence Addon
 * Plugin URL:   https://rwsite.ru
 * Description:  Transforms your standard WordPress installation into an excellent, optimized website with comprehensive performance, security, and usability enhancements.
 * Version:      1.3.6
 * Text Domain:  wp-addon
 * Domain Path: /languages/
 * Author:       Aleksey Tikhomirov
 * Author URI:   https://rwsite.ru
 *
 * Tags: wordpress, wp-addon,
 *
 * Requires at least: 6.6
 * Tested up to: 6.8.3
 * Requires PHP: 7.4+
 */

defined('ABSPATH') || exit;

// Register autoloader
require_once __DIR__ . '/src/Autoloader.php';
\WpAddon\Autoloader::register();

// Initialize plugin
$plugin = new \WpAddon\Core\Plugin(__FILE__);
$plugin->init();