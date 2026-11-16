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
    // Code editor for HTML
    $settings = wp_enqueue_code_editor(['type' => 'text/html']);
    // Skip when CodeMirror is disabled
    if ($settings === false) {
        return;
    }
    // Form template editor
    /* wp_add_inline_script(
         'code-editor',
         sprintf( 'jQuery( function() { wp.codeEditor.initialize( "wpcf7-form", %s ); } );', wp_json_encode( $settings ) )
     );*/
    // Mail template editor
    wp_add_inline_script(
        'code-editor',
        sprintf('jQuery( function() { wp.codeEditor.initialize( "wpcf7-mail-body", %s ); } );', wp_json_encode($settings)),
    );
});
