<?php

namespace WpAddon\Services;

/**
 * Pingback and trackback source verification.
 *
 * Kept compatible with Kama SpamBlock so that plugin can be dropped without
 * losing the backlink check it provided.
 */
class CommentAntispamBacklinkService
{
    public function isPingbackSourceValid(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        $response = wp_safe_remote_get($url, [
            'timeout' => 5,
            'redirection' => 2,
            'limit_response_size' => 1024 * 1024,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return $this->hasBacklink((string) wp_remote_retrieve_body($response));
    }

    public function hasBacklink(string $html, ?string $homeHost = null): bool
    {
        $homeHost = $homeHost ?? (string) wp_parse_url(home_url(), PHP_URL_HOST);

        if ($homeHost === '' || trim($html) === '') {
            return false;
        }

        $quotedHost = preg_quote($homeHost, '~');
        $pattern = '~<a[^>]+href=[\'"](?:https?:)?//(?:www\.)?'.$quotedHost.'(?=[:/?#\'"\\s>])~si';

        return preg_match($pattern, $html) === 1;
    }
}
