<?php

use WpAddon\Services\PageCacheRewriteService;

it('builds apache rules that bypass php for safe cache hits', function () {
    $rules = (new PageCacheRewriteService)->buildRules('/wp-content/cache/pages', 3600);

    expect($rules)
        ->toContain('RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$')
        ->toContain('RewriteCond %{QUERY_STRING} ^$')
        ->toContain('wordpress_logged_in_')
        ->toContain('wp_woocommerce_session_')
        ->toContain('/wp-content/cache/pages/%{HTTP_HOST}/$1/index.html.gz')
        ->toContain('Header set X-Page-Cache "HIT"')
        ->toContain('Header set Cache-Control "public, max-age=3600"')
        ->toContain('Header set Content-Encoding "gzip"');
});
