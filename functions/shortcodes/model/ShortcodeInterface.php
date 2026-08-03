<?php

/**
 * Shortcode interface
 */

// Объявим интерфейс 'iTemplate'
interface ShortcodeInterface
{
    public function __construct($tag, $title, $description, $icon = null);

    public function html($atts);

    public function vc_support();

    public function assets();

    public function tiny_mce_support();

    public function admin_script($screen);
}
