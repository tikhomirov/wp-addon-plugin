<?php

/**
 * Hide email from Spam Bots using a shortcode.
 *
 * @param  array  $atts  Shortcode attributes. Not used.
 * @param  string  $content  The shortcode content. Should be an email address.
 * @return string The obfuscated email address.
 */
function hide_email_shortcode($atts, $content = null)
{
    if (! is_email($content)) {
        return false;
    }

    $content = antispambot($content);
    $email_link = sprintf('mailto:%s', $content);

    return sprintf(
        '<a href="%s" class="mail"><i class="fa fa-envelope"></i> %s</a>',
        esc_url($email_link, ['mailto']),
        esc_html($content)
    );
}

add_shortcode('email', 'hide_email_shortcode');
