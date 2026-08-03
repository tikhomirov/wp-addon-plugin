<?php

namespace WpAddon\Services;

/**
 * Service for managing assets (CSS/JS enqueueing)
 */
class AssetService
{
    /**
     * Plugin file
     */
    private string $file;

    /**
     * Plugin URL
     */
    private string $url;

    /**
     * Plugin name
     */
    private string $name;

    /**
     * Plugin version
     */
    private string $version;

    /**
     * Plugin path
     */
    private string $path;

    /**
     * Constructor
     */
    public function __construct(string $file, string $url, string $name, string $version)
    {
        $this->file = $file;
        $this->path = plugin_dir_path($file);
        $this->url = $url;
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            $this->name,
            $this->url.'assets/css/min/wp-addon.min.css',
            [],
            $this->version
        );

        do_action('rw_enqueue_scripts');
    }
}
