<?php

namespace WpAddon;

/**
 * PSR-4 Autoloader for WpAddon namespace
 */
class Autoloader
{
    /**
     * Register the autoloader
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Autoload function
     */
    public static function autoload(string $class): void
    {
        if (strpos($class, 'WpAddon\\') === 0) {
            $relativePath = str_replace('WpAddon\\', '', $class);
            $filePath = __DIR__.'/'.str_replace('\\', '/', $relativePath).'.php';

            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }
    }
}
