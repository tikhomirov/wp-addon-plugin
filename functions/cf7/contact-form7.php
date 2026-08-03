<?php

/*
 *  Code Editor for Contact Form 7 (WordPress / CodeMirror)
*/

function cf7_show_shortcode()
{
    if (! defined('WPCF7_PLUGIN')) {
        return;
    }
    /* Notice */
    add_action('wpcf7_admin_notices', 'cf7_notice');
    function cf7_notice()
    {
        if (get_current_screen()->base === 'toplevel_page_wpcf7') {
            $html = '<div class="notice updated">';
            $html .= __('Additional shortcodes <b>email:</b>', 'wp-addon');
            $html .= '<code>[_date] [_time] [_url] [_remote_ip] [_user_agent]</code></div>';
            echo $html;
        }
    }
}
