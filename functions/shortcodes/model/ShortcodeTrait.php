<?php

/**
 * @year: 2019-08-19
 */
trait ShortcodeTrait
{
    public $tag;

    public $title;

    public $description;

    public $icon;

    public function __construct($tag, $title, $description, $icon = null)
    {
        $this->tag = $tag;
        $this->title = $title;
        $this->description = $description;

        $icon_def = file_exists(get_stylesheet_directory().'/img/black_color.svg') ?
            get_stylesheet_directory_uri().'/img/black_color.svg' :
            'https://cdn1.iconfinder.com/data/icons/sugar-glyph/64/174_sugar-white-cubes-512.png';
        $this->icon = $icon ?? $icon_def;

        add_shortcode($this->tag, [$this, 'html']);
        add_action('init', [$this, 'vc_support']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('admin_head', [$this, 'tiny_mce_support']);
        add_action('init', [$this, 'pll_support']);
    }
}
