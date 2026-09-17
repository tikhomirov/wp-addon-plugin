<?php

require_once dirname(__DIR__).'/main-settings-helpers.php';

use WpAddon\Interfaces\ModuleInterface;

/**
 * Official Rank Math whitelabel / credit removal filters.
 *
 * Controlled by General Settings → SEO → rank_math_whitelabel.
 * Defaults to enabled when the option key is missing (existing installs).
 */
class RankMathWhitelabel implements ModuleInterface
{
    public function init(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_filter('rank_math/whitelabel', '__return_true');
        add_filter('rank_math/frontend/remove_credit_notice', '__return_true');
        add_filter('rank_math/sitemap/remove_credit', '__return_true');
    }

    private function isEnabled(): bool
    {
        $seo = wp_addon_main_section_settings('seo');

        if (! array_key_exists('rank_math_whitelabel', $seo)) {
            return true;
        }

        return wp_addon_is_setting_enabled($seo['rank_math_whitelabel']);
    }
}
