<?php

if (! function_exists('change_excerpt')) {
    function change_excerpt()
    {
        /**
         * Обрезка выдержки, если она больше 181 символов. (c точкой).
         *
         * @return string
         */
        function change_excerpt_length(string $excerpt, WP_Post $post)
        {
            $excerpt = strip_shortcodes($excerpt);
            $excerpt = wp_strip_all_tags($excerpt);

            if (mb_strlen($excerpt, 'UTF-8') > 181) {
                $excerpt = preg_replace('( [.*?])', '', $excerpt);
                $excerpt = mb_substr($excerpt, 0, 180, 'UTF-8');
                $excerpt = mb_substr($excerpt, 0, strripos($excerpt, ' '), 'UTF-8');
                $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
                $excerpt .= '...';
            }

            return $excerpt;
        }
        add_filter('get_the_excerpt', 'change_excerpt_length', 10, 2);
    }
}
