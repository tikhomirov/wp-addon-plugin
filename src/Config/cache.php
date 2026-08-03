<?php

return [
    'enabled' => true,
    'cache_dir' => WP_CONTENT_DIR.'/cache/pages/',
    'ttl' => 3600, // 1 hour
    'max_files' => 1000,
    'cleanup_batch_size' => 100,
    'exclude_logged_in' => true,
    'exclude_urls' => [
        '/wp-admin/',
        '/wp-login.php',
        '/checkout/',
        '/cart/',
    ],
    'preload_pages' => [
        '/',
        '/about/',
        '/contact/',
    ],
];
