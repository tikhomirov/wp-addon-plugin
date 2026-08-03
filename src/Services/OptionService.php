<?php

namespace WpAddon\Services;

/**
 * Service for managing plugin options
 */
class OptionService
{
    /**
     * Option key
     */
    private string $optionKey;

    /**
     * Constructor
     */
    public function __construct(string $optionKey = 'wp-addon')
    {
        $this->optionKey = $optionKey;
    }

    /**
     * Get plugin settings from DB
     */
    public function getSettings(): array
    {
        return get_option($this->optionKey, []) ?: [];
    }

    /**
     * Update plugin settings
     */
    public function updateSettings(array $settings): bool
    {
        return update_option($this->optionKey, $settings);
    }

    /**
     * Get specific setting value
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();

        return $settings[$key] ?? $default;
    }
}
