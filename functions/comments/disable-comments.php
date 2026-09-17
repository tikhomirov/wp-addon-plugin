<?php

require_once dirname(__DIR__).'/main-settings-helpers.php';

/**
 * Disable comments globally or for selected post types.
 */
function disable_comments()
{
    add_action('admin_init', static function () {
        $disabledPostTypes = wp_addon_comments_disabled_post_types();

        if ($disabledPostTypes === []) {
            return;
        }

        global $pagenow;

        if ($disabledPostTypes === null && $pagenow === 'edit-comments.php') {
            wp_safe_redirect(admin_url());
            exit;
        }

        if ($disabledPostTypes === null) {
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        }

        foreach (get_post_types() as $postType) {
            if (! wp_addon_should_disable_comments_for_post_type($postType)) {
                continue;
            }

            if (post_type_supports($postType, 'comments')) {
                remove_post_type_support($postType, 'comments');
                remove_post_type_support($postType, 'trackbacks');
            }
        }
    });

    add_filter('comments_open', 'wp_addon_filter_comments_open', 20, 2);
    add_filter('pings_open', 'wp_addon_filter_comments_open', 20, 2);
    add_filter('comments_array', 'wp_addon_filter_comments_array', 10, 2);

    add_action('admin_menu', static function () {
        if (wp_addon_comments_disabled_post_types() !== null) {
            return;
        }

        remove_menu_page('edit-comments.php');
    });

    add_action('init', static function () {
        if (wp_addon_comments_disabled_post_types() !== null || ! is_admin_bar_showing()) {
            return;
        }

        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    });
}

function wp_addon_filter_comments_open($open, $postId)
{
    $post = get_post($postId);

    if (! $post instanceof WP_Post) {
        return $open;
    }

    if (wp_addon_should_disable_comments_for_post_type($post->post_type)) {
        return false;
    }

    return $open;
}

function wp_addon_filter_comments_array($comments, $postId)
{
    $post = get_post($postId);

    if (! $post instanceof WP_Post) {
        return $comments;
    }

    if (wp_addon_should_disable_comments_for_post_type($post->post_type)) {
        return [];
    }

    return $comments;
}
