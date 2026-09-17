<?php

return [
    'enabled' => true,
    'cache_dir' => WP_CONTENT_DIR.'/cache/pages/',
    'ttl' => 3600, // 1 hour
    'max_files' => 10000,
    'cleanup_batch_size' => 500,
    'exclude_logged_in' => true,
    'exclude_urls' => [
        '/wp-admin/',
        '/wp-login.php',
        '/wp-json/',
        '/xmlrpc.php',
        '/cart/',
        '/checkout/',
        '/my-account/',
    ],
    'preload_pages' => [],
];
