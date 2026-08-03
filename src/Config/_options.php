<?php

return [

    // framework title
    'framework_title' => $this->wp_plugin_name.' <small>'.__('by Aleksey Tihomirov', 'wp-addon').'</small>',
    'framework_class' => 'wp-addon',

    // menu settings
    'menu_title' => $this->wp_plugin_name,
    'menu_slug' => $this->wp_plugin_slug,
    'menu_type' => 'menu',
    'menu_capability' => 'manage_options',
    'menu_icon' => 'dashicons-heart',
    'menu_position' => null,
    'menu_hidden' => false,
    'menu_parent' => '',

    // menu extras
    'show_bar_menu' => false,
    'show_sub_menu' => true,
    'show_network_menu' => true,
    'show_in_customizer' => false,

    'show_search' => true,
    'show_reset_all' => true,
    'show_reset_section' => true,
    'show_footer' => true,
    'show_all_options' => true,
    'sticky_header' => true,
    'save_defaults' => true,
    'ajax_save' => true,

    // admin bar menu settings
    'admin_bar_menu_icon' => 'dashicons-heart',
    'admin_bar_menu_priority' => 80,

    // footer
    'footer_text' => __(
        'With love by <span style="color: #ED4301;">A</span> Tikhomirov',
        'wp-addon'
    ),
    'footer_after' => '',
    'footer_credit' => '',

    'theme' => 'light',

    // database model
    'database' => '', // options, transient, theme_mod, network
    'transient_time' => 0,

    // contextual help
    'contextual_help' => [],
    'contextual_help_sidebar' => '',
    //
    'enqueue_webfont' => true,
    'async_webfont' => false,
    // others
    'output_css' => true,
    'class' => '',
];
