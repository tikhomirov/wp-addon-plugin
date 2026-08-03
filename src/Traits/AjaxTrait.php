<?php

namespace WpAddon\Traits;

trait AjaxTrait
{
    abstract public function handleAjax(): void;

    public function registerAjax(string $action): void
    {
        $action = apply_filters('wp_addon_ajax_action', $action, $this);
        add_action("wp_ajax_$action", [$this, 'handleAjax']);
        add_action("wp_ajax_nopriv_$action", [$this, 'handleAjax']);
    }
}
