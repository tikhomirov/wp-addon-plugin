<?php

/**
 * Comments hack
 *
 * @author: Aleksey Tikhomirov
 *
 * @year: 2019-03-28
 */

/**
 * Remove site in comment form
 */
function remove_site_field_in_comment()
{
    add_filter('comment_form_default_fields', 'remove_url_field');
    function remove_url_field($fields)
    {
        if (isset($fields['url'])) {
            unset($fields['url']);
        }

        return $fields;
    }
}
