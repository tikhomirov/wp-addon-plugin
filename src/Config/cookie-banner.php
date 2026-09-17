<?php

require_once __DIR__.'/cookie-banner-defaults.php';

$cookieBannerDefaults = wp_addon_get_cookie_banner_defaults();

return [
    [
        'id' => 'cookie_banner_enabled',
        'type' => 'switcher',
        'title' => __('Enable cookie banner', 'wp-addon'),
        'desc' => __('Shows a cookie consent banner on the frontend.', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_enabled'] === '1',
    ],
    [
        'id' => 'cookie_banner_position',
        'type' => 'select',
        'title' => __('Banner position', 'wp-addon'),
        'options' => [
            'bottom-center' => __('Bottom center', 'wp-addon'),
            'bottom-left' => __('Bottom left', 'wp-addon'),
            'bottom-right' => __('Bottom right', 'wp-addon'),
        ],
        'default' => $cookieBannerDefaults['cookie_banner_position'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_text',
        'type' => 'textarea',
        'title' => __('Banner text', 'wp-addon'),
        'desc' => __('Use {link1} and {link2} placeholders for links configured below. A cookie icon is added automatically on the frontend.', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_text'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_link_1_text',
        'type' => 'text',
        'title' => __('Link 1 text', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_link_1_text'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_link_1_url',
        'type' => 'text',
        'title' => __('Link 1 URL', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_link_1_url'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_link_2_text',
        'type' => 'text',
        'title' => __('Link 2 text', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_link_2_text'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_link_2_url',
        'type' => 'text',
        'title' => __('Link 2 URL', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_link_2_url'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_button_mode',
        'type' => 'select',
        'title' => __('Buttons mode', 'wp-addon'),
        'desc' => __('In two-button mode analytics code is loaded only after accepting all cookies.', 'wp-addon'),
        'options' => [
            'one' => __('One button (accept)', 'wp-addon'),
            'two' => __('Two buttons (essential / accept all)', 'wp-addon'),
        ],
        'default' => $cookieBannerDefaults['cookie_banner_button_mode'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_button_1_text',
        'type' => 'text',
        'title' => __('Button 1 text', 'wp-addon'),
        'desc' => __('For one-button mode this is the accept button. For two-button mode this is the essential-only button.', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_button_1_text'],
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
    [
        'id' => 'cookie_banner_button_2_text',
        'type' => 'text',
        'title' => __('Button 2 text', 'wp-addon'),
        'default' => $cookieBannerDefaults['cookie_banner_button_2_text'],
        'dependency' => [
            ['cookie_banner_enabled', '==', 'true'],
            ['cookie_banner_button_mode', '==', 'two'],
        ],
    ],
    [
        'id' => 'cookie_banner_analytics_code',
        'type' => 'code_editor',
        'title' => __('Analytics code', 'wp-addon'),
        'desc' => __('Paste the full counter HTML from Yandex Metrika or another service. The code is stored and injected as-is after consent. Leave empty to disable analytics loading.', 'wp-addon'),
        'settings' => [
            'theme' => 'monokai',
            'mode' => 'htmlmixed',
        ],
        'default' => $cookieBannerDefaults['cookie_banner_analytics_code'],
        'sanitize' => false,
        'dependency' => ['cookie_banner_enabled', '==', 'true'],
    ],
];
