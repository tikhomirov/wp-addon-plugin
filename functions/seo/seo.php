<?php

/**
 * Yoast functions
 */

/**
 * Заполняет поле для атрибута alt на основе заголовка изображения при его вставки в контент поста.
 */
function img_alt_in_upload()
{
    add_filter('wp_prepare_attachment_for_js', function ($response, $attachment, $meta) {
        if ($response && ! $response['alt']) {
            $response['alt'] = sanitize_text_field($response['title']);
        }

        return $response;
    }, 10, 3);
}

/**
 * Add transliteration
 */
function transliteration_enable()
{
    // транслитерация
    function cyr_to_lat($title)
    {
        global $wpdb;

        $iso9_table = [
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Ѓ' => 'G',
            'Ґ' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Є' => 'YE',
            'Ж' => 'ZH', 'З' => 'Z', 'Ѕ' => 'Z', 'И' => 'I', 'Й' => 'J',
            'Ј' => 'J', 'І' => 'I', 'Ї' => 'YI', 'К' => 'K', 'Ќ' => 'K',
            'Л' => 'L', 'Љ' => 'L', 'М' => 'M', 'Н' => 'N', 'Њ' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ў' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'TS',
            'Ч' => 'CH', 'Џ' => 'DH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => '',
            'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ѓ' => 'g',
            'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'ѕ' => 'z', 'и' => 'i', 'й' => 'j',
            'ј' => 'j', 'і' => 'i', 'ї' => 'yi', 'к' => 'k', 'ќ' => 'k',
            'л' => 'l', 'љ' => 'l', 'м' => 'm', 'н' => 'n', 'њ' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ў' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
            'ч' => 'ch', 'џ' => 'dh', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $geo2lat = [
            'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v',
            'ზ' => 'z', 'თ' => 'th', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm',
            'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's',
            'ტ' => 't', 'უ' => 'u', 'ფ' => 'ph', 'ქ' => 'q', 'ღ' => 'gh', 'ყ' => 'qh',
            'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'tch',
            'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',
        ];
        $iso9_table = array_merge($iso9_table, $geo2lat);

        $locale = get_locale();
        switch ($locale) {
            case 'bg_BG':
                $iso9_table['Щ'] = 'SHT';
                $iso9_table['щ'] = 'sht';
                $iso9_table['Ъ'] = 'A';
                $iso9_table['ъ'] = 'a';
                break;
            case 'uk':
            case 'uk_ua':
            case 'uk_UA':
                $iso9_table['И'] = 'Y';
                $iso9_table['и'] = 'y';
                break;
        }

        $is_term = false;
        $backtrace = debug_backtrace();
        foreach ($backtrace as $backtrace_entry) {
            if ($backtrace_entry['function'] === 'wp_insert_term') {
                $is_term = true;
                break;
            }
        }

        $term = $is_term ? $wpdb->get_var("SELECT slug FROM {$wpdb->terms} WHERE name = '$title'") : '';

        if (! empty($term)) {
            $title = $term;
        } else {
            $title = strtr($title, apply_filters('ctl_table', $iso9_table));
            if (function_exists('iconv')) {
                $title = iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $title);
            }
            $title = preg_replace("/[^A-Za-z0-9'_\-\.]/", '-', $title);
            $title = preg_replace('/\-+/', '-', $title);
            $title = preg_replace('/^-+/', '', $title);
            $title = preg_replace('/-+$/', '', $title);
            $title = strtolower($title);
        }

        return $title;
    }
    add_filter('sanitize_title', 'cyr_to_lat', 9);
    add_filter('sanitize_file_name', 'cyr_to_lat', 9);

    function ctl_convert_existing_slugs()
    {
        global $wpdb;

        $posts = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name REGEXP('[^A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private')");
        foreach ((array) $posts as $post) {
            $sanitized_name = sanitize_title(urldecode($post->post_name));
            if ($post->post_name != $sanitized_name) {
                add_post_meta($post->ID, '_wp_old_slug', $post->post_name);
                $wpdb->update($wpdb->posts, ['post_name' => $sanitized_name], ['ID' => $post->ID]);
            }
        }

        $terms = $wpdb->get_results("SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^A-Za-z0-9\-]+') ");
        foreach ((array) $terms as $term) {
            $sanitized_slug = sanitize_title(urldecode($term->slug));
            if ($term->slug != $sanitized_slug) {
                $wpdb->update($wpdb->terms, ['slug' => $sanitized_slug], ['term_id' => $term->term_id]);
            }
        }
    }

    function ctl_schedule_conversion()
    {
        add_action('shutdown', 'ctl_convert_existing_slugs');
    }
}

/**
 * Выключить индексацию. Убирает страницы из поисковой выдачи.
 */
function index_disable()
{
    add_action('wp_head', static function () {
        echo '<meta name="robots" content="noindex">';
    });
}
