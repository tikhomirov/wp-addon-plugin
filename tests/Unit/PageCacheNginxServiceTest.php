<?php

use WpAddon\Services\PageCacheNginxService;

it('builds nginx rules that serve page cache before php', function () {
    $config = (new PageCacheNginxService)->buildConfig('/wp-content/cache/pages', 3600);

    expect($config)
        ->toContain('try_files $wp_addon_cache_uri $uri $uri/ /index.php?$args;')
        ->toContain('if ($request_method !~ ^(GET|HEAD)$)')
        ->toContain('if ($query_string != "")')
        ->toContain('wordpress_logged_in_')
        ->toContain('wp_woocommerce_session_')
        ->toContain('gzip_static on;')
        ->toContain('add_header X-Page-Cache HIT always;')
        ->toContain('add_header Cache-Control "public, max-age=3600" always;');
});
