<?php

return [
    [
        'type' => 'content',
        'content' => '',
    ],

    [
        'id' => 'disable_guttenberg_widget',
        'type' => 'switcher',
        'title' => __('Disable guttenberg for widgets', 'wp-addon'),
        'default' => true,
    ],
    [
        'id' => 'add_clone_widget',
        'type' => 'switcher',
        'title' => __('Enable Duplicate widgets', 'wp-addon'),
        'default' => true,
    ],
    /*[
        'id'      => 'custom_sidebars',
        'type'    => 'switcher',
        'title'   => __( 'Enable custom Dynamic sidebars', 'wp-addon' ),
        'default' => true,
    ],*/

    [   // Shortcodes
        'id' => 'components',
        'type' => 'tabbed',
        'title' => __('Components', 'wp-addon'),
        'subtitle' => '',
        'tabs' => [
            [
                'title' => __('Shortcodes', 'rw-addon'),
                'icon' => '',
                'fields' => [
                    [
                        'id' => 'faq_shortcode',
                        'type' => 'switcher',
                        'title' => __('Enable FAQ shortcode', 'wp-addon'),
                        'desc' => __('WPBakery Page Builder support ', 'wp-addon'),
                        'default' => true,
                    ],
                    [
                        'id' => 'table_of_contents',
                        'type' => 'switcher',
                        'title' => __('Enable Table of Content shortcode', 'wp-addon'),
                        'desc' => __('WPBakery Page Builder support ', 'wp-addon'),
                        'default' => true,
                    ],
                ],
            ],
            [
                'title' => __('Widgets', 'rw-addon'),
                'icon' => 'fa fa-connectdevelop',
                'fields' => [
                    [
                        'id' => 'archive_widget',
                        'type' => 'switcher',
                        'title' => __('Yearly archive widget', 'wp-addon'),
                        'default' => true,
                    ],
                ],
            ],
        ],
    ],
];
