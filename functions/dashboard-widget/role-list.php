<?php

/**
 * Dashboard widget with WordPress user roles.
 */
function dashboard_role_list()
{
    add_action('wp_dashboard_setup', static function () {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'wp_addon_role_list_widget',
            __('Users role info', 'wp-addon'),
            'wp_addon_render_role_list_widget'
        );
    });
}

function wp_addon_render_role_list_widget(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    if (shortcode_exists('role_list')) {
        echo do_shortcode('[role_list]');

        return;
    }

    $roles = wp_roles()->get_names();
    echo '<ol>';

    foreach ($roles as $role => $label) {
        echo '<li>'.esc_html($role).' - '.esc_html(translate_user_role($label)).'</li>';
    }

    echo '</ol>';
}
