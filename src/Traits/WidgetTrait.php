<?php

namespace WpAddon\Traits;

trait WidgetTrait
{
    abstract public function widget($args, $instance): void;

    public function registerWidget(): void
    {
        $widgetClass = apply_filters('wp_addon_widget_class', get_class($this), $this);
        add_action('widgets_init', function () use ($widgetClass) {
            register_widget($widgetClass);
        });
    }
}
