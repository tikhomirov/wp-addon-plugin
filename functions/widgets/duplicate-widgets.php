<?php

function add_clone_widget()
{

    add_filter('admin_head', 'ag_enqueue_duplicate_widgets_script');
    if (! function_exists('ag_enqueue_duplicate_widgets_script')) {
        function ag_enqueue_duplicate_widgets_script()
        {
            global $pagenow;

            if ($pagenow !== 'widgets.php') {
                return;
            }

            wp_enqueue_script(
                'duplicate_widgets_script',
                RW_PLUGIN_URL.'/assets/js/clone-widgets.js',
                ['jquery'],
                false,
                true
            );

            wp_localize_script('duplicate_widgets_script', 'duplicate_widgets', [
                'text' => __('Clone', 'wp-addon'),
                'title' => __('Clone this Widget', 'wp-addon'),
            ]);
        }
    }
}
