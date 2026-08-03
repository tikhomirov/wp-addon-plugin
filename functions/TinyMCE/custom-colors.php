<?php

function tiny_custom_colors()
{

    /**
     * Change Base colors for Tiny MCE
     *
     * @param  $init
     * @return mixed
     */
    add_filter('tiny_mce_before_init', function (array $init) {
        $media_colors = '
            "319cde", "Primary",
            "1f2e3f", "Secondary",
            "1e1e1e", "Text Color",
            "828282", "Post Meta",
            "383838", "Additional ",
            "e8e8e8", "Background",
            "ffffff", "White",
            "000000", "Black"';
        $colors = '
            "02A6F2", "LINK",
            "2E3E52", "BLACK",          
            "FFFFFF", "WHITE",
            "2AA76C", "GREEN",
            "DF543A", "RED",
            "333333", "VIP Emails",
            "8E8E8E", "Base Emails"
          ';

        $init['textcolor_map'] = '[ '.$media_colors.', '.$colors.']';
        $init['textcolor_rows'] = 2;

        return $init;
    });
}
