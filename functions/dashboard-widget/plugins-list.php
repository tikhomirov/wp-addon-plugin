<?php

/**
 * Plugin Name: OT Plugin list
 * Description: Плагин записывает в лог ошибок данные обо всех плагинах сайта.
 * Author: Aleksey Tikhomirov
 * Version: 1.0.0
 * Network: True
 * Text Domain: plugins-list
 * Domain Path: /languages/
 */
function dashboard_plugin_list()
{
    add_shortcode('plugin_list', 'get_plugin_list');
    function get_plugin_list($atts)
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return false;
        }
        if (! function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php'; // подключим файл с функцией get_plugins()
        }
        // получим данные плагинов
        $all_plugins = get_plugins();
        $html = '<ol>';
        foreach ($all_plugins as $plugin) {
            if (! empty($plugin['PluginURI'])) {
                $html .= '<li><a href="'.$plugin['PluginURI'].'">'.$plugin['Name'].'</a> - '.$plugin['Version'].'</li>';
            } else {
                $html .= '<li>'.$plugin['Name'].' - '.$plugin['Version'].'</li>';
            }
        }
        $html .= '<ol>';

        return $html;
    }

    // # Произвольный виджет в консоли в админ-панели
    add_action('wp_dashboard_setup', 'plugin_list_widget');
    function plugin_list_widget()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget('plugin_list_widget', __('Plugin list of ', 'wp-addon').' '.get_bloginfo('name'),
            'echo_plugin_list');
    }

    function echo_plugin_list()
    {
        echo do_shortcode('[plugin_list]');
    }
}
