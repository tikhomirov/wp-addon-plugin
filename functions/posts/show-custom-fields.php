<?php

/**
 * Show built-in custom fields meta box in post edit screens.
 */
function show_all_custom_fields()
{
    add_action('init', static function () {
        $postTypes = get_post_types(['public' => true], 'names');

        foreach ($postTypes as $postType) {
            add_post_type_support($postType, 'custom-fields');
        }
    }, 20);
}
