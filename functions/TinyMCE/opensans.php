<?php

/**
 * Load Open Sans in TinyMCE editor preview.
 */
function tiny_enable_opensans()
{
    add_action('init', static function () {
        add_editor_style('https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800&display=swap');
    });
}
