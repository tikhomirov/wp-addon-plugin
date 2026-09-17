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

        \add_action('restrict_manage_users', [$this, 'filter_by_role'], 10, 1);
        \add_filter('pre_get_users', [$this, 'filter_users_by_role_section']);
        \add_filter("manage_{$this->screen}_sortable_columns", [$this, 'columns_sortable']);
        \add_filter('user_row_actions', [$this, 'quick_edit'], 10, 2);

        if (! \shortcode_exists('role_list')) {
            \add_shortcode('role_list', [$this, 'add_shortcode']);
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
        if (\shortcode_exists('role_list')) {
            echo \do_shortcode('[role_list style="filter" args="'.\esc_attr((string) $which).'"]');
            echo '<input type="submit" name="role_filter" id="role_filter" class="button action" value="'.\esc_attr__('Filter by role', 'wp-addon').'">';
        }

        \add_action('admin_footer', function () {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $('[name="role_filter"]').click(function () {
                        let value = $('select[name="role"]').val();
                        $(this).val(value);
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

        if (! \is_admin() || $pagenow !== 'users.php') {

            return $query;
        }

        if (! isset($_GET['role_filter']) || $_GET['role_filter'] === '') {
            return $query;
        }

        $role = isset($_GET['role']) ? \sanitize_key((string) \wp_unslash($_GET['role'])) : '';

        if ($role === '') {
            return $query;
        }

        $availableRoles = array_keys(\wp_roles()->get_names());

        if (! in_array($role, $availableRoles, true)) {
            return $query;
        }

        $query->set('role', $role);
        $query->set('role__in', [$role]);

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
        return $actions;
    }

    public function add_shortcode($atts = [])
    {
        if (! \is_admin() || ! \current_user_can('manage_options')) {
            return false;
        }

        $role_names = \wp_roles()->get_names();
        $selectedRole = isset($_GET['role']) ? \sanitize_key((string) \wp_unslash($_GET['role'])) : '';

        if (isset($atts['style']) && $atts['style'] === 'filter') {
            $select = '<select name="role" style="float:none;margin-left:10px;">';
            $select .= '<option value="">'.\esc_html__('Filter by role', 'wp-addon').'</option>';

            foreach ($role_names as $role => $name) {
                $selected = $role === $selectedRole ? ' selected="selected"' : '';
                $select .= '<option value="'.\esc_attr($role).'"'.$selected.'>'.\esc_html($name).'</option>';
            }

            $select .= '</select>';

            return $select;
        }

        $html = '<ol>';

        foreach ($role_names as $role => $name) {
            $html .= '<li>'.\esc_html($role).' - '.\esc_html($name).'</li>';
        }

        $html .= '</ol>';

        return $html;
    }
}

return new UserFilter;
