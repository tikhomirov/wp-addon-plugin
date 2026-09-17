<?php

namespace WpAddon\Services;

class PageCacheRewriteService
{
    private const BEGIN_MARKER = '# BEGIN WP Addon Page Cache';

    private const END_MARKER = '# END WP Addon Page Cache';

    public function sync(string $cacheDirectory, int $ttl): bool
    {
        $htaccess = $this->getHtaccessPath();
        if ($htaccess === null) {
            return false;
        }
        if (! is_file($htaccess) || ! is_writable($htaccess)) {
            return false;
        }

        $contentDirectory = wp_normalize_path(WP_CONTENT_DIR);
        $cacheDirectory = trailingslashit(wp_normalize_path($cacheDirectory));
        if (! str_starts_with($cacheDirectory, trailingslashit($contentDirectory))) {
            return false;
        }

        $relativeCacheDirectory = trim(substr($cacheDirectory, strlen($contentDirectory)), '/');
        $contentPath = trim((string) wp_parse_url(content_url(), PHP_URL_PATH), '/');
        $cacheUrlPath = '/'.trim($contentPath.'/'.$relativeCacheDirectory, '/');
        $rules = $this->buildRules($cacheUrlPath, $ttl);
        $current = (string) file_get_contents($htaccess);
        $updated = $this->replaceBlock($current, $rules);

        if ($updated === $current) {
            return true;
        }

        $temporaryFile = tempnam(dirname($htaccess), '.htaccess-page-cache-');
        if ($temporaryFile === false || file_put_contents($temporaryFile, $updated, LOCK_EX) === false) {
            return false;
        }

        if (! rename($temporaryFile, $htaccess)) {
            @unlink($temporaryFile);

            return false;
        }

        @chmod($htaccess, 0644);

        return true;
    }

    public function remove(): bool
    {
        $htaccess = $this->getHtaccessPath();
        if ($htaccess === null || ! is_file($htaccess) || ! is_writable($htaccess)) {
            return false;
        }

        $current = (string) file_get_contents($htaccess);
        $updated = $this->replaceBlock($current, '');

        return $updated === $current || file_put_contents($htaccess, $updated, LOCK_EX) !== false;
    }

    public function buildRules(string $cacheUrlPath, int $ttl): string
    {
        $cacheUrlPath = '/'.trim($cacheUrlPath, '/');
        $maxAge = max(1, $ttl);

        return self::BEGIN_MARKER."\n".
            "<IfModule mod_rewrite.c>\n".
            "RewriteEngine On\n".
            "RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$\n".
            "RewriteCond %{QUERY_STRING} ^$\n".
            "RewriteCond %{HTTP_HOST} ^[A-Za-z0-9.-]+(?::[0-9]+)?$\n".
            "RewriteCond %{HTTP:Cookie} !(?:wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_) [NC]\n".
            "RewriteCond %{DOCUMENT_ROOT}{$cacheUrlPath}/%{HTTP_HOST}/index.html.gz -f\n".
            "RewriteCond %{HTTP:Accept-Encoding} gzip [NC]\n".
            "RewriteRule ^$ {$cacheUrlPath}/%{HTTP_HOST}/index.html.gz [L]\n".
            "RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$\n".
            "RewriteCond %{QUERY_STRING} ^$\n".
            "RewriteCond %{HTTP_HOST} ^[A-Za-z0-9.-]+(?::[0-9]+)?$\n".
            "RewriteCond %{HTTP:Cookie} !(?:wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_) [NC]\n".
            "RewriteCond %{DOCUMENT_ROOT}{$cacheUrlPath}/%{HTTP_HOST}/index.html -f\n".
            "RewriteRule ^$ {$cacheUrlPath}/%{HTTP_HOST}/index.html [L]\n".
            "RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$\n".
            "RewriteCond %{QUERY_STRING} ^$\n".
            "RewriteCond %{HTTP_HOST} ^[A-Za-z0-9.-]+(?::[0-9]+)?$\n".
            "RewriteCond %{HTTP:Cookie} !(?:wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_) [NC]\n".
            "RewriteCond %{DOCUMENT_ROOT}{$cacheUrlPath}/%{HTTP_HOST}/$1/index.html.gz -f\n".
            "RewriteCond %{HTTP:Accept-Encoding} gzip [NC]\n".
            "RewriteRule ^(.+?)/?$ {$cacheUrlPath}/%{HTTP_HOST}/$1/index.html.gz [L]\n".
            "RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$\n".
            "RewriteCond %{QUERY_STRING} ^$\n".
            "RewriteCond %{HTTP_HOST} ^[A-Za-z0-9.-]+(?::[0-9]+)?$\n".
            "RewriteCond %{HTTP:Cookie} !(?:wordpress_logged_in_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash|wp_woocommerce_session_) [NC]\n".
            "RewriteCond %{DOCUMENT_ROOT}{$cacheUrlPath}/%{HTTP_HOST}/$1/index.html -f\n".
            "RewriteRule ^(.+?)/?$ {$cacheUrlPath}/%{HTTP_HOST}/$1/index.html [L]\n".
            "</IfModule>\n".
            "<IfModule mod_headers.c>\n".
            "<FilesMatch \"index\\.html(?:\\.gz)?$\">\n".
            "Header set X-Page-Cache \"HIT\"\n".
            "Header set Cache-Control \"public, max-age={$maxAge}\"\n".
            "Header append Vary \"Accept-Encoding, Cookie\"\n".
            "</FilesMatch>\n".
            "<FilesMatch \"\\.html\\.gz$\">\n".
            "Header set Content-Encoding \"gzip\"\n".
            "Header set Content-Type \"text/html; charset=UTF-8\"\n".
            "</FilesMatch>\n".
            "</IfModule>\n".
            self::END_MARKER;
    }

    private function getHtaccessPath(): ?string
    {
        if (! function_exists('get_home_path')) {
            $file = ABSPATH.'wp-admin/includes/file.php';
            if (! is_file($file)) {
                return null;
            }

            require_once $file;
        }

        return get_home_path().'.htaccess';
    }

    private function replaceBlock(string $content, string $block): string
    {
        $pattern = '/\n?'.preg_quote(self::BEGIN_MARKER, '/').'.*?'.preg_quote(self::END_MARKER, '/').'\n?/s';
        $content = preg_replace($pattern, "\n", $content) ?? $content;
        $content = ltrim($content);

        return $block === '' ? $content : $block."\n\n".$content;
    }
}
