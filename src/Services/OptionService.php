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

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<int, string>  $allowEmpty
     */
    public function mergeDefaults(array $defaults, array $allowEmpty = []): bool
    {
        $settings = $this->getSettings();
        $changed = false;

        foreach ($defaults as $key => $value) {
            if (in_array($key, $allowEmpty, true)) {
                if (! array_key_exists($key, $settings)) {
                    $settings[$key] = $value;
                    $changed = true;
                }

                continue;
            }

            if (! array_key_exists($key, $settings) || $settings[$key] === '' || $settings[$key] === null) {
                $settings[$key] = $value;
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        return $this->updateSettings($settings);
    }
}
