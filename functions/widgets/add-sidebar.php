<?php

/**
 * Add additional sidebar
 */
function add_sidebar_1()
{
    add_action('widgets_init', function () {
        register_sidebar(
            [
                'name' => __('Additional sidebar 1', 'wp-addon'),
                'id' => 'additional_sidebar_1',
                'description' => __('Additional sidebar 1', 'wp-addon'),
                'before_widget' => '<div class="widget-content">',
                'after_widget' => '</div>',
                'before_title' => '<h3 class="widget-title">',
                'after_title' => '</h3>',
            ]
        );
    });
}

function add_sidebar_2()
{
    add_action('widgets_init', function () {
        register_sidebar(
            [
                'name' => __('Additional sidebar 2', 'wp-addon'),
                'id' => 'additional_sidebar_2',
                'description' => __('Additional sidebar 2', 'wp-addon'),
                'before_widget' => '<div class="widget-content">',
                'after_widget' => '</div>',
                'before_title' => '<h3 class="widget-title">',
                'after_title' => '</h3>',
            ]
        );
    });
}

function add_sidebar_3()
{
    add_action('widgets_init', function () {
        register_sidebar(
            [
                'name' => __('Additional sidebar 3', 'wp-addon'),
                'id' => 'additional_sidebar_3',
                'description' => __('Additional sidebar 3', 'wp-addon'),
                'before_widget' => '<div class="widget-content">',
                'after_widget' => '</div>',
                'before_title' => '<h3 class="widget-title">',
                'after_title' => '</h3>',
            ]
        );
    });
}
