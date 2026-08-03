<?php

return [
    'enabled' => true,
    'minify_css' => true,
    'minify_js' => true,
    'combine_css' => true,
    'combine_js' => true,
    'critical_css_enabled' => true,
    'defer_non_critical_css' => true,
    'exclude_css' => [
        'admin-bar',
        'dashicons',
    ],
    'exclude_js' => [
        'jquery',
        'jquery-core',
    ],
    'cache_dir' => WP_CONTENT_DIR.'/cache/assets/',
    'version_salt' => 'wp-addon-v1',
];
