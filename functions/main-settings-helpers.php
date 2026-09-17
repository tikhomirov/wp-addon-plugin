<?php

if (! function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

/**
 * Testable helpers for "General Settings" options.
 */

function wp_addon_main_settings(): array
{
    $settings = get_option('wp-addon', []);

    return is_array($settings) ? $settings : [];
}

function wp_addon_main_section_settings(string $section): array
{
    $sectionSettings = wp_addon_main_settings()[$section] ?? [];

    return is_array($sectionSettings) ? $sectionSettings : [];
}

function wp_addon_is_setting_enabled(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1';
}

function wp_addon_is_root_setting_enabled(string $id): bool
{
    return wp_addon_is_setting_enabled(wp_addon_main_settings()[$id] ?? false);
}

/**
 * @return array<int, string>|null Null means all post types, empty array means feature off.
 */
function wp_addon_comments_disabled_post_types(): ?array
{
    $commentSettings = wp_addon_main_section_settings('comment');

    if (! wp_addon_is_setting_enabled($commentSettings['disable_comments'] ?? false)) {
        return [];
    }

    $postTypes = $commentSettings['disable_comments_post_types'] ?? [];

    if (! is_array($postTypes) || $postTypes === []) {
        return null;
    }

    return array_values(array_filter(array_map('strval', $postTypes)));
}

function wp_addon_should_disable_comments_for_post_type(string $postType): bool
{
    $disabledPostTypes = wp_addon_comments_disabled_post_types();

    if ($disabledPostTypes === []) {
        return false;
    }

    if ($disabledPostTypes === null) {
        return true;
    }

    return in_array($postType, $disabledPostTypes, true);
}

/**
 * @return array<int, int>
 */
function wp_addon_normalize_category_exclude_ids(mixed $raw): array
{
    if ($raw === null) {
        return [];
    }

    if (is_array($raw)) {
        $ids = [];

        foreach ($raw as $value) {
            $id = absint($value);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    if (! is_string($raw) || trim($raw) === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
    $ids = [];

    foreach ($parts as $part) {
        $id = absint(ltrim(trim((string) $part), '-+'));

        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @return array<int, int>
 */
function wp_addon_parse_category_exclude_ids(?string $raw): array
{
    return wp_addon_normalize_category_exclude_ids($raw);
}

/**
 * @return array<int, string>
 */
function wp_addon_get_public_post_type_options(): array
{
    if (! function_exists('get_post_types')) {
        return [];
    }

    $postTypes = get_post_types(['public' => true], 'objects');
    $options = [];

    foreach ($postTypes as $postType) {
        if (! $postType instanceof WP_Post_Type) {
            continue;
        }

        $options[$postType->name] = $postType->labels->name ?: $postType->label;
    }

    asort($options);

    return $options;
}

/**
 * @return array<int, string>
 */
function wp_addon_get_category_checkbox_options(): array
{
    if (! function_exists('get_categories')) {
        return [];
    }

    $categories = get_categories([
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    $options = [];

    foreach ($categories as $category) {
        if (! $category instanceof WP_Term) {
            continue;
        }

        $options[(string) $category->term_id] = $category->name;
    }

    return $options;
}

/**
 * @return array<int, string>
 */
function wp_addon_get_role_checkbox_options(): array
{
    if (! function_exists('wp_roles')) {
        return [];
    }

    return wp_roles()->get_names();
}

/**
 * @return array<int, string>
 */
function wp_addon_parse_ip_whitelist(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }

    $lines = preg_split('/\R/u', trim($raw)) ?: [];
    $ips = [];

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '') {
            continue;
        }

        if (function_exists('rest_is_ip_address') && rest_is_ip_address($line) !== false) {
            $ips[] = $line;

            continue;
        }

        if (filter_var($line, FILTER_VALIDATE_IP) !== false) {
            $ips[] = $line;
        }
    }

    return array_values(array_unique($ips));
}

function wp_addon_get_visitor_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (! is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $parts = array_map('trim', explode(',', $candidate));

        foreach ($parts as $part) {
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP) !== false) {
                return $part;
            }
        }
    }

    return '';
}

function wp_addon_is_ip_whitelisted(string $ip, array $whitelist): bool
{
    if ($ip === '' || $whitelist === []) {
        return false;
    }

    return in_array($ip, $whitelist, true);
}

/**
 * @param  array<int, string>  $allowedRoles
 */
function wp_addon_user_has_allowed_role(array $allowedRoles): bool
{
    if ($allowedRoles === [] || ! function_exists('wp_get_current_user')) {
        return false;
    }

    $user = wp_get_current_user();

    if (! $user instanceof WP_User || $user->ID === 0) {
        return false;
    }

    return (bool) array_intersect($allowedRoles, (array) $user->roles);
}

function wp_addon_should_bypass_maintenance(array $settings): bool
{
    if (function_exists('current_user_can') && current_user_can('manage_options')) {
        return true;
    }

    $visitorIp = wp_addon_get_visitor_ip();
    $ipWhitelist = wp_addon_parse_ip_whitelist((string) ($settings['maintenance_ip_whitelist'] ?? ''));

    if (wp_addon_is_ip_whitelisted($visitorIp, $ipWhitelist)) {
        return true;
    }

    $roleWhitelist = $settings['maintenance_role_whitelist'] ?? [];

    if (is_array($roleWhitelist) && wp_addon_user_has_allowed_role(array_values(array_filter(array_map('strval', $roleWhitelist))))) {
        return true;
    }

    return false;
}

/**
 * @param  array<string, mixed>  $newSettings
 * @param  array<string, mixed>  $oldSettings
 */
function wp_addon_should_flush_rewrite_rules_on_settings_change(array $newSettings, array $oldSettings): bool
{
    $keys = ['remove_category_url', 'hierarchical_tags_rewrite'];

    foreach ($keys as $key) {
        $newValue = wp_addon_is_setting_enabled($newSettings['posts'][$key] ?? false);
        $oldValue = wp_addon_is_setting_enabled($oldSettings['posts'][$key] ?? false);

        if ($newValue !== $oldValue) {
            return true;
        }
    }

    return false;
}

function wp_addon_schedule_rewrite_rules_flush(): void
{
    if (function_exists('update_option')) {
        update_option('wp_addon_flush_rewrite_rules', '1', false);
    }
}

function wp_addon_register_rewrite_flush_on_settings_change(): void
{
    add_filter('pre_update_option_wp-addon', static function ($value, $oldValue) {
        if (! is_array($value) || ! is_array($oldValue)) {
            return $value;
        }

        if (wp_addon_should_flush_rewrite_rules_on_settings_change($value, $oldValue)) {
            wp_addon_schedule_rewrite_rules_flush();
        }

        return $value;
    }, 10, 2);

    add_action('init', static function () {
        if (! function_exists('get_option') || get_option('wp_addon_flush_rewrite_rules') !== '1') {
            return;
        }

        add_action('shutdown', static function () {
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules(false);
            }

            if (function_exists('delete_option')) {
                delete_option('wp_addon_flush_rewrite_rules');
            }
        });
    }, PHP_INT_MAX);
}

function wp_addon_get_tinymce_framework(): string
{
    $tinySettings = wp_addon_main_section_settings('tiny-mce');

    if (! wp_addon_is_setting_enabled($tinySettings['add_bootstrap_3'] ?? false)) {
        return '';
    }

    $framework = (string) ($tinySettings['tinymce_css_framework'] ?? 'bootstrap3');

    return in_array($framework, ['bootstrap3', 'bootstrap5', 'tailwind'], true) ? $framework : 'bootstrap3';
}

function wp_addon_get_tinymce_framework_stylesheet(string $framework): ?string
{
    switch ($framework) {
        case 'bootstrap3':
            if (defined('RW_PLUGIN_URL')) {
                return RW_PLUGIN_URL.'assets/css/min/bootstrap.min.css';
            }

            return null;
        case 'bootstrap5':
            return 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
        case 'tailwind':
            if (function_exists('get_theme_file_path') && function_exists('get_theme_file_uri')) {
                $themePath = get_theme_file_path('assets/css/tailwind-editor.css');

                if ($themePath !== '' && file_exists($themePath)) {
                    return get_theme_file_uri('assets/css/tailwind-editor.css');
                }
            }

            return 'https://cdn.jsdelivr.net/npm/tailwindcss@3.4.17/tailwind.min.css';
        default:
            return null;
    }
}

if (function_exists('add_filter')) {
    wp_addon_register_rewrite_flush_on_settings_change();
}

/**
 * @return array<int, string>
 */
function wp_addon_collect_main_setting_ids(array $fields): array
{
    $ids = [];

    foreach ($fields as $field) {
        if (! is_array($field)) {
            continue;
        }

        if (isset($field['id']) && ($field['type'] ?? '') !== 'fieldset') {
            $ids[] = (string) $field['id'];
        }

        if (isset($field['fields']) && is_array($field['fields'])) {
            $ids = array_merge($ids, wp_addon_collect_main_setting_ids($field['fields']));
        }
    }

    return $ids;
}
