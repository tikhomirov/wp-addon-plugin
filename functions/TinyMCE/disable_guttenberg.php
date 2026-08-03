<?php

// # Отключает новый редактор блоков в WordPress (Гутенберг) полностью.
// # ver: 1.1
if (! function_exists('disable_guttenberg')) {
    /* <<<<<<<<<<<<<<  ✨ Windsurf Command ⭐ >>>>>>>>>>>>>>>> */
    /**
     * Disable the new block editor (Gutenberg) completely.
     *
     * This function will remove all the filters and actions required to
     * use the block editor, including the frontend and backend styles,
     * and the admin notices.
     *
     * @return void
     */
    /* <<<<<<<<<<  d87f8b0c-1531-41de-bd74-577c35599fbc  >>>>>>>>>>> */
    function disable_guttenberg()
    {
        // Отключить для всех типов записей (включая кастомные)
        add_filter('use_block_editor_for_post_type', '__return_false', 100);

        // Отключить для всех постов
        add_filter('use_block_editor_for_post', '__return_false', 100);

        // Отключить виджеты на блоках
        add_filter('use_widgets_block_editor', '__return_false');

        // Удалить стили и скрипты Gutenberg из фронтенда
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-blocks-style');
            wp_dequeue_style('global-styles');
        }, 100);

        // Удалить стили Gutenberg из админки
        add_action('admin_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
        });

        // Удалить SVG и глобальные стили
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

        // Переместить уведомление Privacy Policy обратно под заголовок
        add_action('admin_init', function () {
            remove_action('admin_notices', ['WP_Privacy_Policy_Content', 'notice']);
            add_action('edit_form_after_title', ['WP_Privacy_Policy_Content', 'notice']);
        });
    }
}
