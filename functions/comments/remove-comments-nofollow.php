<?php

/*
Plugin Name: Disable nofollow
Description: Disable nofollow for this blog
Author: Alex Tikhomirov
Version: 1.0
*/

function disable_comment_nofollow()
{

    function commentdofollow($text)
    {
        return str_replace('" rel="nofollow">', '">', $text);
    }

    add_filter('comment_text', 'commentdofollow');
    remove_filter('pre_comment_content', 'wp_rel_nofollow', 15);

    function remove_nofollow($string)
    {
        return str_ireplace(' nofollow', '', $string);
    }
    add_filter('get_comment_author_link', 'remove_nofollow');

}
