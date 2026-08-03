<?php

/**
 * Виджет в Консоли для связи с разработчиком
 */
function dashboard_server_info()
{
    add_action('wp_dashboard_setup', 'rw_custom_dashboard_widgets');
    function rw_custom_dashboard_widgets()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget('custom_help_widget', __('Server info', 'wp-addon'), 'dash_server_info');
    }

    function dash_server_info()
    {
        $indicesServer = [
            'SERVER_ADDR',
            'SERVER_NAME',
            'SERVER_SOFTWARE',
            'SERVER_PROTOCOL',
            'DOCUMENT_ROOT',
            'HTTP_HOST',
            'HTTPS',
            'REMOTE_ADDR',
        ];
        $theme = wp_get_theme();
        if (! empty($theme->name)) {
            $tname = $theme->name;
        } else {
            $tname = '';
        }
        if (! defined('WP_DEBUG') or WP_DEBUG == 0) {
            $debug = __('Off', 'wp-addon');
        } else {
            $debug = '<strong class="text-danger">'.__('On', 'wp-addon').'</strong>';
        }
        if (! defined('WP_DEBUG_LOG') or WP_DEBUG_LOG == 0) {
            $debug_log = __('Off', 'wp-addon');
        } else {
            $debug_log = '<strong class="text-success">'.__('On', 'wp-addon').'</strong>';
        }

        $html = '<div class="bg-lighht"><p>'.__('Welcome to ', 'wp-addon')."<strong> $tname</strong> </p></div>";
        $html .= '<table cellpadding="10" class="wp-list-table widefat fixed striped table-view-list">';
        foreach ($indicesServer as $arg) {
            if (isset($_SERVER[$arg])) {
                $html .= '<tr><td>'.$arg.'</td><td>'.$_SERVER[$arg].'</td></tr>';
            } else {
                $html .= '<tr><td>'.$arg.'</td><td>-</td></tr>';
            }
        }
        $html .= '<tr><td>WP_DEBUG</td><td>'.$debug.'</td></tr>';
        $html .= '<tr><td>WP_DEBUG_LOG</td><td>'.$debug_log.'</td></tr>';
        $html .= '</table>';
        echo $html;
    }
}
