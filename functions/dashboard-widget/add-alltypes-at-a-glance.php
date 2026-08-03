<?php

/**
 * @author: Aleksey Tikhomirov
 *
 * @year: 2019-03-27
 */
function change_glance_widget()
{
    // # Добавляем все типы записей в виджет "Прямо сейчас" в консоли
    // add_action('dashboard_glance_items', 'add_right_now_info');
    function add_right_now_info($items)
    {
        if (! current_user_can('edit_posts')) {
            return $items;
        }

        $args = ['public' => true, '_builtin' => false];

        $post_types = get_post_types($args, 'object', 'and');

        foreach ($post_types as $post_type) {
            $num_posts = wp_count_posts($post_type->name);
            $num = number_format_i18n($num_posts->publish);
            $text = _n($post_type->labels->singular_name, $post_type->labels->name, intval($num_posts->publish));

            $items[] = "<a href=\"edit.php?post_type=$post_type->name\">$num $text</a>";
        }

        // таксономии
        $taxonomies = get_taxonomies($args, 'object', 'and');

        foreach ($taxonomies as $taxonomy) {
            $num_terms = wp_count_terms($taxonomy->name);
            $num = number_format_i18n($num_terms);
            $text = _n($taxonomy->labels->singular_name, $taxonomy->labels->name, intval($num_terms));
            $items[] = "<a href='edit-tags.php?taxonomy=$taxonomy->name'>$num $text</a>";
        }

        // пользователи
        global $wpdb;
        $num = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users");
        $text = _n('User', 'Users', $num);
        $items[] = "<a href='users.php'>$num $text</a>";

        return $items;
    }
}
