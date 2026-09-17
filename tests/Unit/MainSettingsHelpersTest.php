<?php

require_once dirname(__DIR__, 2).'/functions/main-settings-helpers.php';

describe('main settings helper extensions', function () {
    beforeEach(function () {
        global $mock_functions, $mock_wp_current_user;
        $mock_functions['get_option'] = fn ($key, $default = []) => $default;
        $mock_wp_current_user = (object) [
            'ID' => 1,
            'roles' => ['editor'],
        ];
    });

    afterEach(function () {
        unset($GLOBALS['mock_user_capabilities'], $GLOBALS['mock_wp_current_user']);
        unset($_SERVER['REMOTE_ADDR'], $_GET['role_filter'], $_GET['role']);
    });

    it('normalizes category exclude ids from checkbox arrays', function () {
        expect(wp_addon_normalize_category_exclude_ids(['6137', 24990, '0']))->toBe([6137, 24990]);
        expect(wp_addon_normalize_category_exclude_ids('-6137, 24990'))->toBe([6137, 24990]);
    });

    it('parses maintenance ip whitelist', function () {
        expect(wp_addon_parse_ip_whitelist("127.0.0.1\n192.168.0.10\n\n"))->toBe(['127.0.0.1', '192.168.0.10']);
        expect(wp_addon_parse_ip_whitelist(''))->toBe([]);
    });

    it('allows maintenance bypass for administrators', function () {
        global $mock_user_capabilities;
        $mock_user_capabilities = ['manage_options'];

        expect(wp_addon_should_bypass_maintenance([
            'maintenance_ip_whitelist' => '',
            'maintenance_role_whitelist' => [],
        ]))->toBeTrue();
    });

    it('allows maintenance bypass for whitelisted ip', function () {
        global $mock_user_capabilities;
        $mock_user_capabilities = ['edit_posts'];

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        expect(wp_addon_should_bypass_maintenance([
            'maintenance_ip_whitelist' => "203.0.113.10\n",
            'maintenance_role_whitelist' => [],
        ]))->toBeTrue();
    });

    it('allows maintenance bypass for whitelisted role', function () {
        global $mock_user_capabilities, $mock_wp_current_user;
        $mock_user_capabilities = ['edit_posts'];
        $mock_wp_current_user = (object) [
            'ID' => 2,
            'roles' => ['editor'],
        ];

        expect(wp_addon_should_bypass_maintenance([
            'maintenance_ip_whitelist' => '',
            'maintenance_role_whitelist' => ['editor'],
        ]))->toBeTrue();
    });

    it('detects rewrite flush when seo permalink options change', function () {
        $old = ['posts' => ['remove_category_url' => '0', 'hierarchical_tags_rewrite' => '1']];
        $new = ['posts' => ['remove_category_url' => '1', 'hierarchical_tags_rewrite' => '1']];

        expect(wp_addon_should_flush_rewrite_rules_on_settings_change($new, $old))->toBeTrue();
        expect(wp_addon_should_flush_rewrite_rules_on_settings_change($old, $old))->toBeFalse();
    });
});

describe('UserFilter sanitization', function () {
    beforeEach(function () {
        global $mock_wp_roles, $mock_is_admin, $pagenow;
        $mock_wp_roles = [
            'administrator' => 'Administrator',
            'editor' => 'Editor',
        ];
        $mock_is_admin = true;
        $pagenow = 'users.php';

        if (! class_exists('WP_User_Query')) {
            eval('class WP_User_Query { public array $query_vars = []; public function set($key, $value) { $this->query_vars[$key] = $value; } }');
        }

        if (! class_exists('functions\\users\\UserFilter')) {
            require_once dirname(__DIR__, 2).'/functions/users/UserFilter.php';
        }
    });

    it('filters users only by valid role slug', function () {
        $query = new WP_User_Query;
        $_GET['role_filter'] = '1';
        $_GET['role'] = 'editor';

        $filter = new functions\users\UserFilter;
        $filter->filter_users_by_role_section($query);

        expect($query->query_vars['role'] ?? null)->toBe('editor');
    });

    it('ignores invalid role slug in filter', function () {
        $query = new WP_User_Query;
        $_GET['role_filter'] = '1';
        $_GET['role'] = '<script>';

        $filter = new functions\users\UserFilter;
        $filter->filter_users_by_role_section($query);

        expect($query->query_vars['role'] ?? null)->toBeNull();
    });
});
