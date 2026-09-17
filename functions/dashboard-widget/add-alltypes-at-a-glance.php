<?php

/**
 * Extend the "At a Glance" dashboard widget with custom post types and taxonomies.
 */
function change_glance_widget()
{
    add_action('dashboard_glance_items', 'wp_addon_add_right_now_info', 20);
}

function wp_addon_add_right_now_info($items)
{
    if (! current_user_can('edit_posts')) {
        return $items;
    }

    $args = ['public' => true, '_builtin' => false];
    $postTypes = get_post_types($args, 'object', 'and');

    foreach ($postTypes as $postType) {
        $numPosts = wp_count_posts($postType->name);
        $num = number_format_i18n($numPosts->publish);
        $text = _n($postType->labels->singular_name, $postType->labels->name, (int) $numPosts->publish);
        $items[] = '<a href="edit.php?post_type='.esc_attr($postType->name).'">'.esc_html($num.' '.$text).'</a>';
    }

    $taxonomies = get_taxonomies($args, 'object', 'and');

    foreach ($taxonomies as $taxonomy) {
        $numTerms = wp_count_terms(['taxonomy' => $taxonomy->name]);
        $num = number_format_i18n($numTerms);
        $text = _n($taxonomy->labels->singular_name, $taxonomy->labels->name, (int) $numTerms);
        $items[] = '<a href="edit-tags.php?taxonomy='.esc_attr($taxonomy->name).'">'.esc_html($num.' '.$text).'</a>';
    }

    global $wpdb;
    $num = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users");
    $text = _n('User', 'Users', (int) $num);
    $items[] = '<a href="users.php">'.esc_html($num.' '.$text).'</a>';

    return $items;
}
