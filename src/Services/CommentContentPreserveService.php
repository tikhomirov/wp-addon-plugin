<?php

declare(strict_types=1);

namespace WpAddon\Services;

/**
 * Keeps markup that WordPress comment KSES would strip, by escaping disallowed tags
 * before {@see wp_filter_kses()} runs. Allowed tags match core {@see $allowedtags} plus pre.
 */
final class CommentContentPreserveService
{
    /**
     * @return list<string>
     */
    public static function allowedTagNames(): array
    {
        return [
            'a',
            'abbr',
            'acronym',
            'b',
            'blockquote',
            'cite',
            'code',
            'del',
            'em',
            'i',
            'pre',
            'q',
            's',
            'strike',
            'strong',
        ];
    }

    /**
     * Escape angle-bracket markup that KSES would remove so it is stored and shown as text.
     */
    public static function escapeDisallowedTags(string $content): string
    {
        if (! str_contains($content, '<')) {
            return $content;
        }

        $allowed = array_flip(self::allowedTagNames());

        return (string) preg_replace_callback(
            '/<(\/?)([A-Za-z][A-Za-z0-9]*)\b[^>]*>/',
            static function (array $match) use ($allowed): string {
                $tag = strtolower($match[2]);
                if (isset($allowed[$tag])) {
                    return $match[0];
                }

                return htmlspecialchars($match[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $content
        );
    }
}
