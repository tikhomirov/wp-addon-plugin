<?php

/**
 * @year: 2019-04-12
 */
function widget_shortcode($atts)
{
    global $wp_widget_factory;

    if (empty($GLOBALS['wp_widget_factory']) || empty($wp_widget_factory)) {
        return false;
    }

    $widget_name = $instance = $args = null;

    extract(shortcode_atts([
        'widget_name' => false,
        'instance' => [],
        'args' => [],
    ], $atts)
    );

    $widget_name = esc_html($widget_name);

    if (class_exists($widget_name)) {
        register_widget($widget_name);
        ob_start();
        the_widget($widget_name, $instance, $args);

        return ob_get_clean();
    }

    return __('Widget not found!', 'wp-addon');
}

function widget_short_code_init()
{
    add_shortcode('widget', 'widget_shortcode');
}

add_action('widgets_init', 'widget_short_code_init');
