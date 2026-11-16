<?php

// Disable block editor for widgets
function disable_guttenberg_widget()
{
    add_filter('gutenberg_use_widgets_block_editor', '__return_false', 100);
    add_filter('use_widgets_block_editor', '__return_false');
}
