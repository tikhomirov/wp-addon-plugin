<?php

/**
 * Plugin Name: Code Editor for Contact Form 7
 * Plugin URI: https://gist.github.com/campusboy87/2daad24e45116721759991549b626977
 * Author: Campusboy (wp-plus)
 * Author URI: https://www.youtube.com/wp-plus
 */
if (! defined('WPCF7_PLUGIN')) {
    return;
}

$hook_suffix = 'toplevel_page_wpcf7';

add_action("admin_print_styles-{$hook_suffix}", function () {
    if (empty($_GET['post'])) {
        return;
    }
    // Подключаем редактор кода для HTML.
    $settings = wp_enqueue_code_editor(['type' => 'text/html']);
    // Ничего не делаем, если CodeMirror отключен.
    if ($settings === false) {
        return;
    }
    // Инициализация редактора для редактирования шаблона формы
    /* wp_add_inline_script(
         'code-editor',
         sprintf( 'jQuery( function() { wp.codeEditor.initialize( "wpcf7-form", %s ); } );', wp_json_encode( $settings ) )
     );*/
    // Инициализация редактора для редактирования шаблона письма
    wp_add_inline_script(
        'code-editor',
        sprintf('jQuery( function() { wp.codeEditor.initialize( "wpcf7-mail-body", %s ); } );', wp_json_encode($settings))
    );
});
