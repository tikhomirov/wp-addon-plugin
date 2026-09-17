<?php

if (! defined('RW_PLUGIN_URL')) {
    define('RW_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-addon-plugin/');
}

require_once dirname(__DIR__, 2).'/functions/main-settings-helpers.php';

describe('Main settings config', function () {
    it('provides beginner description for every main setting field', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';

        $walk = function (array $items) use (&$walk): array {
            $missing = [];

            foreach ($items as $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['type'] ?? '') === 'fieldset') {
                    $missing = array_merge($missing, $walk($field['fields'] ?? []));

                    continue;
                }

                if (($field['desc'] ?? '') === '') {
                    $missing[] = $field['id'] ?? 'unknown';
                }
            }

            return $missing;
        };

        expect($walk($fields))->toBe([]);
    });

    it('uses category checkbox picker for exclusion', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';
        $posts = null;

        foreach ($fields as $field) {
            if (($field['id'] ?? '') === 'posts') {
                $posts = $field;
                break;
            }
        }

        $excludeValue = null;

        foreach ($posts['fields'] ?? [] as $field) {
            if (($field['id'] ?? '') === 'exclude_cat_val') {
                $excludeValue = $field;
                break;
            }
        }

        expect($excludeValue['type'] ?? null)->toBe('checkbox');
        expect($excludeValue['default'] ?? null)->toBe([]);
    });

    it('includes maintenance mode whitelist fields', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';
        $ids = wp_addon_collect_main_setting_ids($fields);

        expect($ids)->toContain('maintenance_message');
        expect($ids)->toContain('maintenance_ip_whitelist');
        expect($ids)->toContain('maintenance_role_whitelist');
    });

    it('marks cautious main settings with warning emoji', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';

        $titlesById = [];
        $walk = function (array $items) use (&$walk, &$titlesById): void {
            foreach ($items as $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['type'] ?? '') === 'fieldset') {
                    $walk($field['fields'] ?? []);

                    continue;
                }

                $titlesById[$field['id']] = $field['title'];
            }
        };
        $walk($fields);

        foreach (['disable_auto_update', 'exclude_cat', 'disable_guttenberg', 'index_disable', 'fix_guid'] as $id) {
            expect($titlesById[$id])->toContain('⚠️');
        }
    });

    it('includes partial comment disable field', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';
        $comments = null;

        foreach ($fields as $field) {
            if (($field['id'] ?? '') === 'comment') {
                $comments = $field;
                break;
            }
        }

        $ids = wp_addon_collect_main_setting_ids($comments['fields'] ?? []);

        expect($ids)->toContain('disable_comments_post_types');
    });

    it('includes css framework selector for tinymce', function () {
        $fields = require dirname(__DIR__, 2).'/src/Config/main.php';
        $tiny = null;

        foreach ($fields as $field) {
            if (($field['id'] ?? '') === 'tiny-mce') {
                $tiny = $field;
                break;
            }
        }

        $ids = wp_addon_collect_main_setting_ids($tiny['fields'] ?? []);

        expect($ids)->toContain('tinymce_css_framework');
    });
});

describe('main settings helpers', function () {
    beforeEach(function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn ($key, $default = []) => $default;
    });

    it('parses category exclude ids from comma separated values', function () {
        expect(wp_addon_parse_category_exclude_ids('-6137, 24990, -24992'))->toBe([6137, 24990, 24992]);
        expect(wp_addon_parse_category_exclude_ids(''))->toBe([]);
    });

    it('treats empty comment post types as global disable', function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn () => [
            'comment' => [
                'disable_comments' => '1',
                'disable_comments_post_types' => [],
            ],
        ];

        expect(wp_addon_comments_disabled_post_types())->toBeNull();
        expect(wp_addon_should_disable_comments_for_post_type('post'))->toBeTrue();
        expect(wp_addon_should_disable_comments_for_post_type('product'))->toBeTrue();
    });

    it('supports partial comment disable by post type', function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn () => [
            'comment' => [
                'disable_comments' => '1',
                'disable_comments_post_types' => ['post'],
            ],
        ];

        expect(wp_addon_comments_disabled_post_types())->toBe(['post']);
        expect(wp_addon_should_disable_comments_for_post_type('post'))->toBeTrue();
        expect(wp_addon_should_disable_comments_for_post_type('page'))->toBeFalse();
        expect(wp_addon_comments_disabled_post_types())->not->toBeNull();
    });

    it('returns disabled comment mode when switch is off', function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn () => [
            'comment' => [
                'disable_comments' => '0',
            ],
        ];

        expect(wp_addon_comments_disabled_post_types())->toBe([]);
        expect(wp_addon_should_disable_comments_for_post_type('post'))->toBeFalse();
    });

    it('resolves tinymce css framework stylesheets', function () {
        expect(wp_addon_get_tinymce_framework_stylesheet('bootstrap3'))
            ->toBe(RW_PLUGIN_URL.'assets/css/min/bootstrap.min.css');
        expect(wp_addon_get_tinymce_framework_stylesheet('bootstrap5'))
            ->toContain('bootstrap@5.3.3');
        expect(wp_addon_get_tinymce_framework_stylesheet('tailwind'))
            ->toContain('tailwindcss');
    });

    it('reads selected tinymce framework from settings', function () {
        global $mock_functions;
        $mock_functions['get_option'] = fn () => [
            'tiny-mce' => [
                'add_bootstrap_3' => '1',
                'tinymce_css_framework' => 'bootstrap5',
            ],
        ];

        expect(wp_addon_get_tinymce_framework())->toBe('bootstrap5');
    });
});

describe('main settings callbacks', function () {
    it('has callback function for core main settings', function () {
        $callbacks = [
            'show_all_custom_fields',
            'disable_auto_update',
            'show_id',
            'show_thumbnail',
            'duplicate_post',
            'remove_category_url',
            'hierarchical_tags_rewrite',
            'change_excerpt',
            'exclude_cat',
            'disable_comments',
            'disable_comment_nofollow',
            'remove_site_field_in_comment',
            'disable_guttenberg',
            'tiny_custom_colors',
            'tiny_enable_opensans',
            'tiny_advanced',
            'add_bootstrap_3',
            'tiny_table_plugin',
            'img_alt_in_upload',
            'transliteration_enable',
            'index_disable',
            'write_right_guid',
            'fix_guid',
            'change_glance_widget',
            'dashboard_plugin_list',
            'dashboard_server_info',
            'dashboard_role_list',
            'pll_hide_lang',
        ];

        foreach ($callbacks as $callback) {
            $path = match ($callback) {
                'show_all_custom_fields' => '/functions/posts/show-custom-fields.php',
                'disable_auto_update' => '/functions/DisableAutoUpdate.php',
                'show_id' => '/functions/posts/show-id.php',
                'show_thumbnail' => '/functions/posts/ShowThumbnail.php',
                'duplicate_post' => '/functions/posts/DuplicatePost.php',
                'remove_category_url' => '/functions/seo/RemoveCategoryURL.php',
                'hierarchical_tags_rewrite' => '/functions/seo/HierarchicalTagsRewrite.php',
                'change_excerpt' => '/functions/posts/post-excerpt.php',
                'exclude_cat' => '/functions/posts/CategoriesFilter.php',
                'disable_comments' => '/functions/comments/disable-comments.php',
                'disable_comment_nofollow' => '/functions/comments/remove-comments-nofollow.php',
                'remove_site_field_in_comment' => '/functions/comments/comments-function.php',
                'disable_guttenberg' => '/functions/TinyMCE/disable_guttenberg.php',
                'tiny_custom_colors' => '/functions/TinyMCE/custom-colors.php',
                'tiny_enable_opensans' => '/functions/TinyMCE/opensans.php',
                'tiny_advanced', 'tiny_table_plugin' => '/functions/TinyMCE/plugins.php',
                'add_bootstrap_3' => '/functions/TinyMCE/bootstrap-shortcodes.php',
                'img_alt_in_upload', 'transliteration_enable', 'index_disable' => '/functions/seo/seo.php',
                'write_right_guid', 'fix_guid' => '/functions/posts/fix_guid.php',
                'change_glance_widget' => '/functions/dashboard-widget/add-alltypes-at-a-glance.php',
                'dashboard_plugin_list' => '/functions/dashboard-widget/plugins-list.php',
                'dashboard_server_info' => '/functions/dashboard-widget/siteinfo.php',
                'dashboard_role_list' => '/functions/dashboard-widget/role-list.php',
                'pll_hide_lang' => '/functions/polylang/hide-lang.php',
                default => null,
            };

            require_once dirname(__DIR__, 2).$path;
            expect(function_exists($callback))->toBeTrue("Missing function {$callback}");
        }
    });
});
