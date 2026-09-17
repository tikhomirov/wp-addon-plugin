<?php

if (! function_exists('wp_addon_get_cookie_banner_defaults')) {
    function wp_addon_get_cookie_banner_defaults(): array
    {
        return [
            'cookie_banner_enabled' => '1',
            'cookie_banner_position' => 'bottom-center',
            'cookie_banner_text' => 'Сайт использует cookie для работы и аналитики. {link1}',
            'cookie_banner_link_1_text' => 'Подробнее',
            'cookie_banner_link_1_url' => '/privacy-policy/',
            'cookie_banner_link_2_text' => '',
            'cookie_banner_link_2_url' => '',
            'cookie_banner_button_mode' => 'two',
            'cookie_banner_button_1_text' => 'Обязательные',
            'cookie_banner_button_2_text' => 'Принять все',
            'cookie_banner_analytics_code' => '',
        ];
    }
}
