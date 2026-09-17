<?php

require_once dirname(__DIR__).'/main-settings-helpers.php';

/**
 * Hide selected Polylang languages on the frontend.
 */
function pll_hide_lang()
{
    if (! defined('POLYLANG_VERSION')) {
        return;
    }

    add_filter('pll_the_languages', static function ($languages) {
        if (! is_array($languages)) {
            return $languages;
        }

        $hidden = wp_addon_main_section_settings('pll')['pll_hide_lang'] ?? '';

        if (! is_string($hidden) || trim($hidden) === '') {
            return $languages;
        }

        $hiddenSlugs = array_filter(array_map('trim', preg_split('/\s*,\s*/', $hidden) ?: []));

        foreach ($hiddenSlugs as $slug) {
            unset($languages[$slug]);
        }

        return $languages;
    }, 20);
}
