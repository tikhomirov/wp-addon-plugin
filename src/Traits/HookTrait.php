<?php

namespace WpAddon\Traits;

trait HookTrait
{
    public function addHook(string $hook, $callback, int $priority = 10, int $args = 1): void
    {
        $priority = \apply_filters('wp_addon_hook_priority', $priority, $hook, $callback, $this);
        $args = \apply_filters('wp_addon_hook_args', $args, $hook, $callback, $this);
        \add_action($hook, $callback, $priority, $args);
    }

    public function addFilter(string $filter, $callback, int $priority = 10, int $args = 1): void
    {
        $priority = \apply_filters('wp_addon_filter_priority', $priority, $filter, $callback, $this);
        $args = \apply_filters('wp_addon_filter_args', $args, $filter, $callback, $this);
        \add_filter($filter, $callback, $priority, $args);
    }
}
