<?php

require_once dirname(__DIR__, 2).'/functions/main-settings-helpers.php';

if (! function_exists('wpmain_switcher')) {
    /**
     * @return array{id: string, type: string, title: string, default: bool, desc: string}
     */
    function wpmain_switcher(string $id, string $title, bool $default, string $desc, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 'switcher',
            'title' => $title,
            'default' => $default,
            'desc' => $desc,
        ], $extra);
    }
}

$fields = [
    wpmain_switcher(
        'enable_maintenance',
        __('Maintenance Mode', 'wp-addon'),
        false,
        __('Shows an "Under maintenance" page to all visitors except administrators. Useful when updating the theme, plugins, or migrating content.', 'wp-addon')
    ),
    [
        'id' => 'maintenance_message',
        'type' => 'textarea',
        'title' => __('Maintenance message', 'wp-addon'),
        'default' => __('Sorry, the site is temporarily unavailable due to maintenance. Please try again later.', 'wp-addon'),
        'desc' => __('Custom text shown on the maintenance page. Leave empty to use the default message.', 'wp-addon'),
        'dependency' => ['enable_maintenance', '==', 'true'],
    ],
    [
        'id' => 'maintenance_ip_whitelist',
        'type' => 'textarea',
        'title' => __('Maintenance IP whitelist', 'wp-addon'),
        'default' => '',
        'desc' => __('One IP address per line. Whitelisted IPs can browse the site while maintenance mode is active.', 'wp-addon'),
        'dependency' => ['enable_maintenance', '==', 'true'],
    ],
    [
        'id' => 'maintenance_role_whitelist',
        'type' => 'checkbox',
        'title' => __('Maintenance role whitelist', 'wp-addon'),
        'desc' => __('Selected roles can browse the site while maintenance mode is active. Administrators always have access.', 'wp-addon'),
        'options' => wp_addon_get_role_checkbox_options(),
        'default' => [],
        'dependency' => ['enable_maintenance', '==', 'true'],
    ],
    wpmain_switcher(
        'show_all_custom_fields',
        __('Show custom fields for categories and posts', 'wp-addon'),
        false,
        __('Restores the standard Custom Fields meta box on post edit screens. Needed when you work with meta fields without a separate plugin.', 'wp-addon')
    ),
    wpmain_switcher(
        'disable_auto_update',
        __('⚠️ Disable all updates.', 'wp-addon'),
        false,
        __('Fully disables automatic and background updates for core, plugins, and themes. Use only on staging or when you control updates manually. When enabled, it also covers Tweaks "Block WordPress.org update HTTP requests" and "Disable aggressive update".', 'wp-addon')
    ),
];

$fields['posts'] = [
    'id' => 'posts',
    'type' => 'fieldset',
    'title' => __('Posts and Page Settings', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'show_id',
            __('Show posts IDs', 'wp-addon'),
            true,
            __('Adds an ID column to post, page, and media lists. Handy for shortcodes, SQL queries, and debugging.', 'wp-addon')
        ),
        wpmain_switcher(
            'show_thumbnail',
            __('Show posts thumbnails', 'wp-addon'),
            true,
            __('Shows the post thumbnail in the Posts admin table. Helps you quickly find posts with a featured image.', 'wp-addon')
        ),
        wpmain_switcher(
            'duplicate_post',
            __('Enable duplicate post and page', 'wp-addon'),
            true,
            __('Adds a Duplicate action for posts and pages. Creates a draft copy with the same content.', 'wp-addon')
        ),
        wpmain_switcher(
            'remove_category_url',
            __('Remove category base from URL', 'wp-addon'),
            true,
            __('Removes /category/ from category URLs. Permalinks are flushed automatically when this option changes.', 'wp-addon')
        ),
        wpmain_switcher(
            'hierarchical_tags_rewrite',
            __('Enable hierarchical tags rewrite rules', 'wp-addon'),
            true,
            __('Enables pretty URLs for nested tags such as /tag/git/submodules/. The rewrite rule maps to the final tag slug, not the full path. Permalinks are flushed automatically when this option changes.', 'wp-addon')
        ),
        wpmain_switcher(
            'change_excerpt',
            __('Change length post excerpt? To maximum 200 symbols.', 'wp-addon'),
            true,
            __('Trims manual or automatic excerpts to about 180 characters with an ellipsis. Useful for post cards and SEO snippets.', 'wp-addon')
        ),
        wpmain_switcher(
            'exclude_cat',
            __('⚠️ Don`t allow to show specific categories in frontend.', 'wp-addon'),
            false,
            __('Hides selected categories on the frontend: posts are excluded from archives and feeds, and a direct visit to a category archive redirects to the home page.', 'wp-addon')
        ),
        [
            'id' => 'exclude_cat_val',
            'type' => 'checkbox',
            'title' => __('Categories to hide on the frontend', 'wp-addon'),
            'desc' => __(
                'Select categories that should be hidden from archives, feeds, and direct category URLs. Legacy comma-separated IDs are still supported.',
                'wp-addon'
            ),
            'options' => wp_addon_get_category_checkbox_options(),
            'default' => [],
            'dependency' => ['exclude_cat', '==', 'true'],
        ],
    ],
];

$fields['comments'] = [
    'id' => 'comment',
    'type' => 'fieldset',
    'title' => __('Comment form', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'disable_comments',
            __('Disable comments', 'wp-addon'),
            true,
            __('Disables comments on the site. If no post types are selected below, comments are disabled everywhere. If you select post types, comments stay enabled only where you need them.', 'wp-addon')
        ),
        [
            'id' => 'disable_comments_post_types',
            'type' => 'checkbox',
            'title' => __('Post types without comments', 'wp-addon'),
            'desc' => __(
                'Select post types where comments should be disabled. If nothing is selected, comments are disabled everywhere.',
                'wp-addon'
            ),
            'options' => wp_addon_get_public_post_type_options(),
            'default' => [],
            'dependency' => ['disable_comments', '==', 'true'],
        ],
        wpmain_switcher(
            'disable_comment_nofollow',
            __('Disable link Nofollow in comment', 'wp-addon'),
            false,
            __('Removes rel="nofollow" from comment author links. Enable only if you intentionally pass link equity from comments.', 'wp-addon')
        ),
        wpmain_switcher(
            'remove_site_field_in_comment',
            __('Remove site field in comment', 'wp-addon'),
            true,
            __('Hides the Website field in the comment form. Reduces spam and avoids asking readers for extra data.', 'wp-addon')
        ),
    ],
];

$fields['tiny-mce'] = [
    'id' => 'tiny-mce',
    'type' => 'fieldset',
    'title' => __('TinyMCE Settings', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'disable_guttenberg',
            __('⚠️ Disable guttenber?', 'wp-addon'),
            true,
            __('Restores the classic TinyMCE editor instead of the block editor for all post types. On newer WordPress versions it may conflict with plugins built for blocks. For the widget screen only, use Shortcodes and Widgets → Disable Gutenberg for widgets.', 'wp-addon')
        ),
        wpmain_switcher(
            'tiny_custom_colors',
            __('Add a pack color to tinyMCE', 'wp-addon'),
            true,
            __('Adds an extended text and background color palette in the visual editor.', 'wp-addon')
        ),
        wpmain_switcher(
            'tiny_enable_opensans',
            __('Enable "Open Sans" google font for tinyMCE.', 'wp-addon'),
            true,
            __('Loads the Open Sans font in the editor preview. Works independently of TinyMCE Advanced.', 'wp-addon')
        ),
        wpmain_switcher(
            'tiny_advanced',
            __('Add TinyMCE Advanced functions', 'wp-addon'),
            true,
            __('Adds a second toolbar: fonts, sizes, tables, subscript/superscript, and extra formatting buttons.', 'wp-addon')
        ),
        wpmain_switcher(
            'add_bootstrap_3',
            __('Enable CSS framework in editor', 'wp-addon'),
            true,
            __('Loads a CSS framework into the TinyMCE preview and adds a button to insert framework markup. Needed when content uses grid and component classes.', 'wp-addon')
        ),
        [
            'id' => 'tinymce_css_framework',
            'type' => 'select',
            'title' => __('CSS framework for TinyMCE preview', 'wp-addon'),
            'desc' => __(
                'Bootstrap 3 is the supported framework for the current theme and shortcodes. Bootstrap 5 and Tailwind are reserved for future use and may not match the frontend styles yet.',
                'wp-addon'
            ),
            'options' => [
                'bootstrap3' => __('Bootstrap 3 (recommended)', 'wp-addon'),
                'bootstrap5' => __('Bootstrap 5 (future)', 'wp-addon'),
                'tailwind' => __('Tailwind CSS (future)', 'wp-addon'),
            ],
            'default' => 'bootstrap3',
            'dependency' => ['add_bootstrap_3', '==', 'true'],
        ],
        wpmain_switcher(
            'tiny_table_plugin',
            __('Add table plugin', 'wp-addon'),
            true,
            __('Enables creating and editing tables in TinyMCE with ready-made Bootstrap CSS classes.', 'wp-addon')
        ),
    ],
];

$fields['seo'] = [
    'id' => 'seo',
    'type' => 'fieldset',
    'title' => __('SEO Settings', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'img_alt_in_upload',
            __('Set Alt in updload from Title media', 'wp-addon'),
            true,
            __('If an uploaded image has an empty alt attribute, uses the media library title. Helps with accessibility and SEO.', 'wp-addon')
        ),
        wpmain_switcher(
            'transliteration_enable',
            __('Enable transliteration?', 'wp-addon'),
            true,
            __('Transliterates Cyrillic to Latin for post, category, and filename slugs. Handy for Russian sites with Latin URLs.', 'wp-addon')
        ),
        wpmain_switcher(
            'index_disable',
            __('⚠️ Disable site indexing.', 'wp-addon'),
            false,
            __('Adds meta robots noindex on every page of the site. Use on test or staging hosts so search engines do not index a draft site. Different from Tweaks "Noindex for search, attachment and date archives", which only targets low-value archive pages.', 'wp-addon')
        ),
    ],
];

$fields['guid'] = [
    'id' => 'guid',
    'type' => 'fieldset',
    'title' => __('Data Base Optimization', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'write_right_guid',
            __('Write right link to guid for New posts.', 'wp-addon'),
            true,
            __('When saving a new post, writes its permalink into guid instead of a temporary ?p=ID URL. Useful for RSS and some integrations.', 'wp-addon')
        ),
        wpmain_switcher(
            'fix_guid',
            __('⚠️ This option enable interface for replace wp guide to right GUID.', 'wp-addon'),
            false,
            __('Opens an admin tool for bulk viewing and replacing GUID values. Change GUIDs deliberately — RSS subscribers may receive duplicate items.', 'wp-addon')
        ),
    ],
];

$fields[] = [
    'id' => 'dashboard_widgets',
    'type' => 'fieldset',
    'title' => __('Admin dashboard widgets', 'wp-addon'),
    'fields' => [
        wpmain_switcher(
            'change_glance_widget',
            __('Add all types at a glance widget', 'wp-addon'),
            true,
            __('Expands the At a Glance widget on the admin dashboard: shows all public post types, taxonomies, and the user count.', 'wp-addon')
        ),
        wpmain_switcher(
            'dashboard_plugin_list',
            __('Plugins list', 'wp-addon'),
            true,
            __('Adds a list of installed plugins with versions to the dashboard. Visible to administrators only.', 'wp-addon')
        ),
        wpmain_switcher(
            'dashboard_server_info',
            __('Server info', 'wp-addon'),
            true,
            __('Shows server, domain, and WP_DEBUG information. Useful for diagnostics, but avoid enabling on shared hosting unless needed.', 'wp-addon')
        ),
        wpmain_switcher(
            'dashboard_role_list',
            __('Users role info', 'wp-addon'),
            true,
            __('Adds a WordPress roles list widget to the dashboard. Helps you recall role slugs when configuring permissions.', 'wp-addon')
        ),
    ],
];

if (defined('WPCF7_PLUGIN')) {
    $fields[] = [
        'id' => 'cf7',
        'type' => 'fieldset',
        'title' => __('Contact Form 7 settings', 'wp-addon'),
        'fields' => [
            wpmain_switcher(
                'cf7_show_shortcode',
                __('Show additional shortcode?', 'wp-addon'),
                false,
                __('Shows a hint with extra CF7 shortcodes: date, time, URL, IP, and user agent. Handy when collecting technical data from a form.', 'wp-addon')
            ),
        ],
    ];
}

if (defined('POLYLANG_VERSION')) {
    $fields[] = [
        'id' => 'pll',
        'type' => 'fieldset',
        'title' => __('Polylang settings', 'wp-addon'),
        'fields' => [
            [
                'id' => 'pll_hide_lang',
                'type' => 'text',
                'title' => __('Hide languages in wp Front. Enter lang slug, separated: ", ".', 'wp-addon'),
                'default' => '',
                'desc' => __(
                    'List of Polylang language slugs to hide in the frontend switcher. Example: en, de. Spaces after commas are allowed.',
                    'wp-addon'
                ),
            ],
        ],
    ];
}

return $fields;
