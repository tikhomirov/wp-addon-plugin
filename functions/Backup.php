<?php

use WpAddon\Backup\Admin as BackupAdmin;
use WpAddon\Backup\Dumper;
use WpAddon\Backup\Restorer;
use WpAddon\Backup\Storage;
use WpAddon\Interfaces\ModuleInterface;

/**
 * Database backup module for WP Addon.
 *
 * Module UI renders inside the plugin settings page
 * (Backup DB section), no separate admin page is registered.
 */
final class Backup implements ModuleInterface
{
    /**
     * WP Addon settings page slug in admin.
     */
    private const SETTINGS_PAGE_HOOK = 'toplevel_page_wp-addon';

    /**
     * Nonce action for all module AJAX operations.
     */
    public const NONCE_ACTION = 'wp_addon_backup_actions';

    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        foreach (self::ajax_actions() as $action => $handler) {
            add_action('wp_ajax_'.$action, [$this, $handler]);
        }
    }

    /**
     * Module AJAX action map: action => handler method.
     *
     * Single source of truth for init() and unit tests: tests assert
     * names match assets/js/backup.js.
     *
     * @return array<string, string>
     */
    public static function ajax_actions(): array
    {
        return [
            'wp_addon_backup_create' => 'ajax_create',
            'wp_addon_backup_restore' => 'ajax_restore',
            'wp_addon_backup_delete' => 'ajax_delete',
            'wp_addon_backup_upload' => 'ajax_upload',
            'wp_addon_backup_download' => 'ajax_download',
            'wp_addon_backup_list' => 'ajax_list',
        ];
    }

    /**
     * Enqueues assets on the plugin settings page.
     *
     * @param  string  $hook_suffix  Current admin hook suffix.
     */
    public function enqueue_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== self::SETTINGS_PAGE_HOOK) {
            return;
        }

        wp_enqueue_style(
            'wp-addon-backup',
            RW_PLUGIN_URL.'assets/css/backup.css',
            [],
            WP_ADDON_VERSION
        );

        wp_enqueue_script(
            'wp-addon-backup',
            RW_PLUGIN_URL.'assets/js/backup.js',
            ['jquery'],
            WP_ADDON_VERSION,
            true
        );

        wp_localize_script('wp-addon-backup', 'wpAddonBackup', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'strings' => [
                'working' => __('Working…', 'wp-addon'),
                'done' => __('Done.', 'wp-addon'),
                'operationFailed' => __('Operation failed.', 'wp-addon'),
                'connectionError' => __('Server connection error.', 'wp-addon'),
                'fileNotSpecified' => __('No file specified.', 'wp-addon'),
                'deleteFailed' => __('Could not delete the file.', 'wp-addon'),
                'selectBackupFile' => __('Choose a backup file.', 'wp-addon'),
                'uploadFailed' => __('Upload failed.', 'wp-addon'),
                'confirmRestore' => __('Restore will replace the entire database. Continue?', 'wp-addon'),
                'confirmDelete' => __('Delete backup file?', 'wp-addon'),
                'download' => __('Download backup', 'wp-addon'),
                'uploadTooLarge' => __('File too large: the server rejected the request (nginx or PHP limit). Increase client_max_body_size and post_max_size.', 'wp-addon'),
                'uploadBadResponse' => __('The server returned an unexpected response while uploading. Refresh the page and try again.', 'wp-addon'),
            ],
            'canRestore' => BackupAdmin::can_restore(),
        ]);
    }

    /**
     * Backup DB section HTML for a CSF content field.
     *
     * No form fields: buttons are JS-driven; nonce
     * comes from the localized object, so CSF save
     * settings stay independent.
     *
     * @param  Storage|null  $storage  Storage (for tests).
     */
    public static function render_settings_content(?Storage $storage = null): string
    {
        $storage = $storage ?? new Storage;

        ob_start();
        ?>
        <div class="wp-addon-backup">
            <p class="wp-addon-backup__lead">
                <?php
                global $wpdb;

        printf(
            /* translators: %s: table prefix from wp-config.php */
            esc_html__('The snapshot includes schema and data for all tables. Table prefix: %s', 'wp-addon'),
            '<code>'.esc_html(isset($wpdb->prefix) ? (string) $wpdb->prefix : 'wp_').'</code>'
        );
        ?>
            </p>

            <div class="wp-addon-backup__actions">
                <button type="button" class="button button-primary" id="wp-addon-backup-create">
                    <?php echo esc_html__('Create backup', 'wp-addon'); ?>
                </button>

                <span class="wp-addon-backup__progress" id="wp-addon-backup-progress" hidden>
                    <span class="wp-addon-backup__progress-bar"></span>
                </span>
            </div>

            <div class="wp-addon-backup__status" id="wp-addon-backup-status" role="status" aria-live="polite"></div>

            <h3><?php echo esc_html__('Backup files', 'wp-addon'); ?></h3>
            <div id="wp-addon-backup-list">
                <?php BackupAdmin::render_backup_table($storage); ?>
            </div>

            <h3><?php echo esc_html__('Restore', 'wp-addon'); ?></h3>
            <p class="description">
                <?php echo esc_html__('Restore fully replaces this site database. Backups with a different table prefix are rejected.', 'wp-addon'); ?>
            </p>

            <h3><?php echo esc_html__('Upload backup', 'wp-addon'); ?></h3>
            <p class="description">
                <?php echo esc_html__('Upload an .sql backup created by this module to restore it on this site.', 'wp-addon'); ?>
            </p>
            <p>
                <input type="file" id="wp-addon-backup-upload" accept=".sql">
                <button type="button" class="button" id="wp-addon-backup-upload-btn">
                    <?php echo esc_html__('Upload backup file', 'wp-addon'); ?>
                </button>
            </p>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Checks capability and nonce for module AJAX.
     */
    private static function authorize(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Insufficient permissions.', 'wp-addon')],
                403
            );
        }

        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['nonce'])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(
                ['message' => __('Session expired; refresh the page.', 'wp-addon')],
                403
            );
        }
    }

    /**
     * Returns JSON error from exception message.
     */
    private static function fail(Throwable $error): void
    {
        $message = trim($error->getMessage());

        if ($message === '') {
            $message = __('Unknown error.', 'wp-addon');
        }

        wp_send_json_error(['message' => $message], 500);
    }

    public function ajax_create(): void
    {
        self::authorize();

        try {
            $storage = new Storage;
            $storage->ensure_directories();

            $label = gmdate('Y-m-d_H-i-s');
            $path = $storage->new_dump_path($label);

            $dumper = new Dumper;
            $dumper->open($path);

            try {
                $summary = $dumper->dump();
            } catch (Throwable $error) {
                $dumper->close();
                @unlink($path);

                throw $error;
            }

            $dumper->close();

            wp_send_json_success([
                'message' => sprintf(
                    /* translators: 1: tables, 2: size */
                    __('Backup created: %1$d tables, %2$s.', 'wp-addon'),
                    $summary['tables'],
                    Storage::format_size($summary['bytes'])
                ),
                'file' => basename($path),
            ]);
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_restore(): void
    {
        self::authorize();

        try {
            $storage = new Storage;
            $name = isset($_POST['backup']) ? sanitize_text_field(wp_unslash((string) $_POST['backup'])) : '';
            $path = $storage->resolve($name);

            $restorer = new Restorer;
            $restorer->open($path);

            try {
                $result = $restorer->restore();
            } finally {
                $restorer->close();
            }

            // Plain space thousands separator: status is injected via jQuery .text().
            $rows_label = number_format((int) $result['rows'], 0, '.', ' ');

            $message = sprintf(
                /* translators: 1: tables, 2: data rows restored (plain text, no HTML entities) */
                __('Database restored: %1$d tables, %2$s rows.', 'wp-addon'),
                $result['tables'],
                $rows_label
            );

            foreach ($result['warnings'] as $warning) {
                $message .= ' '.$warning;
            }

            wp_send_json_success(['message' => $message]);
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_delete(): void
    {
        self::authorize();

        try {
            $storage = new Storage;
            $name = isset($_POST['backup']) ? sanitize_text_field(wp_unslash((string) $_POST['backup'])) : '';
            $storage->delete($name);

            wp_send_json_success(['message' => __('Backup file deleted.', 'wp-addon')]);
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_upload(): void
    {
        self::authorize();

        try {
            if (empty($_FILES['backup_file'])) {
                throw new RuntimeException(__('No file selected.', 'wp-addon'));
            }

            $storage = new Storage;
            $name = $storage->store_uploaded($_FILES['backup_file']);

            wp_send_json_success([
                'message' => __('Backup uploaded.', 'wp-addon'),
                'file' => $name,
            ]);
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_download(): void
    {
        self::authorize();

        try {
            $storage = new Storage;
            $name = isset($_REQUEST['backup']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['backup'])) : '';
            $path = $storage->resolve($name);

            if (! is_file($path)) {
                throw new RuntimeException(__('Backup file not found.', 'wp-addon'));
            }

            // Disable WordPress output buffering — dump must be sent as-is.
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="'.basename($path).'"');
            header('Content-Length: '.(string) filesize($path));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            readfile($path);
            exit;
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_list(): void
    {
        self::authorize();

        $storage = new Storage;

        ob_start();
        BackupAdmin::render_backup_table($storage);

        wp_send_json_success(['html' => (string) ob_get_clean()]);
    }
}
