<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\AjaxTrait;
use WpAddon\Services\MediaCleanupService;

class MediaCleanup implements ModuleInterface {
    use AjaxTrait;

    private MediaCleanupService $service;

    public function __construct(MediaCleanupService $service) {
        $this->service = $service;
    }

    public function init(): void {
        if (!$this->isEnabled()) {
            return;
        }

        add_action('wp_ajax_wp_addon_cleanup_images', [$this, 'cleanup']);
        add_action('wp_ajax_wp_addon_cleanup_images_dry_run', [$this, 'dryRun']);
    }

    public function dryRun(): void {
        if (!$this->isEnabled() || !current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'wp-addon'));
        }
        check_ajax_referer('cleanup_images', 'nonce');

        $uploadDir = wp_upload_dir();
        $path = $uploadDir['basedir'];
        $result = $this->service->getFilesToDelete($path);

        $files = $result['files'];
        $totalSize = $result['totalSize'];
        $sizeMb = round($totalSize / 1024 / 1024, 2);

        $fileListHtml = !empty($files)
            ? '<ul>' . implode('', array_map(fn($f) => '<li>' . esc_html(basename($f)) . '</li>', $files)) . '</ul>'
            : '<p>' . __('No files to delete.', 'wp-addon') . '</p>';
        $totalSizeHtml = '<p><strong>' . sprintf(__('Total size: %s MB', 'wp-addon'), $sizeMb) . '</strong></p>';

        $output = '<div class="notice notice-info"><p><strong>' . __('Files to delete:', 'wp-addon') . '</strong></p>' . $fileListHtml . $totalSizeHtml . '</div>';

        wp_die($output);
    }

    public function cleanup(): void {
        if (!$this->isEnabled() || !current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'wp-addon'));
        }
        check_ajax_referer('cleanup_images', 'nonce');

        $uploadDir = wp_upload_dir();
        $path = $uploadDir['basedir'];
        $result = $this->service->getFilesToDelete($path);

        $files = $result['files'];
        $deleteResult = $this->service->deleteFiles($files, $path);

        $message = sprintf(__('Deleted %d files.', 'wp-addon'), $deleteResult['deleted']);
        if ($deleteResult['errors'] > 0) {
            $message .= sprintf(__(' Errors: %d.', 'wp-addon'), $deleteResult['errors']);
        }

        $class = $deleteResult['errors'] > 0 ? 'notice-warning' : 'notice-success';
        $output = '<div class="notice ' . $class . '"><p>' . $message . '</p></div>';

        wp_die($output);
    }

    private function isEnabled(): bool {
        $options = get_option('wp-addon', []);

        return isset($options['media_cleanup_enabled'])
            && ($options['media_cleanup_enabled'] === true || $options['media_cleanup_enabled'] === 1 || $options['media_cleanup_enabled'] === '1' || $options['media_cleanup_enabled'] === 'true');
    }

    // Not used since we have separate methods
    public function handleAjax(): void {}
}