<?php

declare(strict_types=1);

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\CommentContentPreserveService;
use WpAddon\Traits\HookTrait;

/**
 * Preserve quotes and code-like markup in comments without weakening KSES.
 */
class CommentContentPreserve implements ModuleInterface
{
    use HookTrait;

    public function init(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        remove_filter('comment_text', 'wptexturize');
        remove_filter('comment_text', 'convert_chars');
        remove_filter('comment_text', 'force_balance_tags', 25);

        $this->addFilter('pre_comment_content', [$this, 'filterPreCommentContent'], 9);
        $this->addFilter('wp_kses_allowed_html', [$this, 'allowPreTag'], 10, 2);
    }

    private function isEnabled(): bool
    {
        $settings = wp_addon_main_settings();
        if (! array_key_exists('preserve_comment_code_in_comments', $settings)) {
            return true;
        }

        return wp_addon_is_setting_enabled($settings['preserve_comment_code_in_comments']);
    }

    /**
     * @param  string  $content  Slashed comment content.
     */
    public function filterPreCommentContent(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        if (! $this->authorNeedsMarkupPreservation()) {
            return $content;
        }

        return wp_slash(CommentContentPreserveService::escapeDisallowedTags(wp_unslash($content)));
    }

    private function authorNeedsMarkupPreservation(): bool
    {
        if (defined('DISALLOW_UNFILTERED_HTML') && DISALLOW_UNFILTERED_HTML) {
            return true;
        }

        return ! current_user_can('unfiltered_html');
    }

    /**
     * @param  array<string, array<string, bool>>|string  $allowed
     * @return array<string, array<string, bool>>|string
     */
    public function allowPreTag($allowed, string $context)
    {
        if ($context !== 'pre_comment_content' || ! is_array($allowed)) {
            return $allowed;
        }

        $allowed['pre'] = [];

        return $allowed;
    }
}
