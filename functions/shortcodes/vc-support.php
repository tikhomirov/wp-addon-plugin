<?php

/**
 * @year: 2019-04-12
 */
add_action('vc_before_init', 'widgets_support_vc');
function widgets_support_vc()
{
    vc_map([
        'name' => __('Any widgets', 'wp-addon'),
        'base' => 'widget',
        'class' => '',
        'category' => __('Elements', 'wp-addon'),
        'params' => [
            [
                'type' => 'textfield',
                'holder' => 'div',
                'class' => '',
                'heading' => __('Widget Name', 'wp-addon'),
                'param_name' => 'widget_name',
                'value' => __('', 'wp-addon'),
                'description' => __('Paste Original Widget Class Name. See: https://codex.wordpress.org/Template_Tags/the_widget', 'wp-addon'),
            ],
            [
                'type' => 'textarea',
                // 'holder' => 'div',
                'class' => '',
                'heading' => __('Instance', 'wp-addon'),
                'param_name' => 'instance',
                'value' => __('', 'wp-addon'),
                'description' => __('Instance Params', 'wp-addon'),
            ],
            [
                'type' => 'textarea',
                'class' => '',
                'heading' => __('Args', 'wp-addon'),
                'param_name' => 'args',
                'value' => __('', 'wp-addon'),
                'description' => __('Enter description.', 'wp-addon'),
            ],

        ],
    ]);
}
