<?php

namespace functions\users;

use WP_User_Query;

/**
 * Plugin Name:     Users filter by role
 * Plugin URL:      https://rwsite.ru
 * Description:     Users filter by role
 * Version:         1.0.8
 * Text Domain:     wp-addon
 * Domain Path:     /languages
 * Author:          Aleksey Tikhomirov <alex@rwite.ru>
 * Author URI:      https://rwsite.ru
 *
 * Tags: avatar, default avatar, user, user avatar
 * Requires at least: 4.6
 * Tested up to: 5.6.0
 * Requires PHP: 7.2+
 */
class UserFilter
{
    public $screen;

    public function __construct()
    {
        $this->screen = 'users';

        add_action('restrict_manage_users', [$this, 'filter_by_role'], 10, 1);
        add_filter('pre_get_users', [$this, 'filter_users_by_role_section']);
        add_filter("manage_{$this->screen}_sortable_columns", [$this, 'columns_sortable']);
        add_filter('user_row_actions', [$this, 'quick_edit'], 10, 2);

        if (! shortcode_exists('role_list')) {
            add_shortcode('role_list', [$this, 'add_shortcode']);
        }
    }

    /**
     ** Sort and Filter Users **
     * render html form filter
     *
     *
     * @return null
     */
    public function filter_by_role($which)
    {
        if (shortcode_exists('role_list')) {
            echo do_shortcode('[role_list style="filter" args="'.$which.'""]');
            echo '<input type="submit" name="role_filter" id="role_filter" class="button action" value="Filter by role">';
        }

        add_action('admin_footer', function () {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {

                    $('[name="role_filter"]').click(function () {
                        let value = $('select[name="role"]').val();
                        console.log($(this).val(value));
                    });

                });
            </script>
			<?php
        });
    }

    /**
     * @return WP_User_Query $query
     */
    public function filter_users_by_role_section(WP_User_Query $query): WP_User_Query
    {
        global $pagenow;

        if (is_admin() && isset($_GET['role_filter']) && $pagenow === 'users.php') {
            if (! empty($_GET['role_filter'])) {
                $query->set('role', $_GET['role_filter']);
                $query->set('role__in', [$_GET['role_filter']]);
            }
        }

        return $query;
    }

    /**
     * Add sortable to columns
     *
     *
     * @return mixed
     */
    public function columns_sortable($sortable_columns)
    {
        $sortable_columns['role'] = 'role';
        $sortable_columns['name'] = 'name';
        $sortable_columns['posts'] = 'posts';

        return $sortable_columns;
    }

    public function quick_edit($actions, $user_object)
    {
        // TODO : quick edit here ...
        return $actions;
    }

    public function add_shortcode($atts = [])
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return false;
        }

        $role_names = wp_roles()->get_names();
        // filter by user role
        if (isset($atts['style']) && $atts['style'] === 'filter') {
            // template for filtering
            $select = '<select name="role" style="float:none;margin-left:10px;">';
            $select .= '<option value="">'.__('Filter by role').'</option>';
            foreach ($role_names as $role => $name) {
                if (isset($_GET['role']) && ! empty($_GET['role']) && $role === $_GET['role']) {
                    $select .= '<option value="'.$role.'" selected="selected">'.$name.'</option>';
                } else {
                    $select .= '<option value="'.$role.'">'.$name.'</option>';
                }
            }
            $select .= '</select>';

            return $select;
        }

        $html = '<ol>';
        foreach ($role_names as $role => $name) {
            $html .= '<li>'.$role.' - '.$name.'</li>';
        }
        $html .= '<ol>';

        return $html;
    }
}

return new UserFilter;
