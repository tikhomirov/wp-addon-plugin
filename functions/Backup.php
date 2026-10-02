<?php

use WpAddon\Backup\Admin as BackupAdmin;
use WpAddon\Backup\Dumper;
use WpAddon\Backup\Restorer;
use WpAddon\Backup\Storage;
use WpAddon\Interfaces\ModuleInterface;

/**
 * Database backup module for WP Addon.
 */
final class Backup implements ModuleInterface
{
    private const PAGE_SLUG = 'wp-addon-backup';

    private const NONCE_ACTION = 'wp_addon_backup_actions';

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_wp_addon_backup_create', [$this, 'ajax_create']);
        add_action('wp_ajax_wp_addon_backup_restore', [$this, 'ajax_restore']);
        add_action('wp_ajax_wp_addon_backup_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_wp_addon_backup_upload', [$this, 'ajax_upload']);
        add_action('wp_ajax_wp_addon_backup_list', [$this, 'ajax_list']);
        add_action('wp_ajax_wp_addon_backup_progress', [$this, 'ajax_progress']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Database Backup', 'wp-addon'),
            __('Backup', 'wp-addon'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-database-export',
            76
        );
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if (strpos($hook_suffix, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'wp-addon-backup',
            RW_PLUGIN_URL . 'assets/css/backup.css',
            [],
            WP_ADDON_VERSION
        );

        wp_enqueue_script(
            'wp-addon-backup',
            RW_PLUGIN_URL . 'assets/js/backup.js',
            ['jquery'],
            WP_ADDON_VERSION,
            true
        );

        wp_localize_script('wp-addon-backup', 'wpAddonBackup', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'strings' => [
                'working' => __('Working...', 'wp-addon'),
                'confirmRestore' => __('Restoring will replace the entire database. Continue?', 'wp-addon'),
                'confirmDelete' => __('Delete backup file?', 'wp-addon'),
            ],
        ]);
    }

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
                ['message' => __('Session expired, refresh the page.', 'wp-addon')],
                403
            );
        }
    }

    private static function fail(\Throwable $error): void
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
            $storage = new Storage();
            $storage->ensure_directories();

            $label = gmdate('Y-m-d_H-i-s');
            $path = $storage->new_dump_path($label);

            $dumper = new Dumper();
            $dumper->open($path);

            try {
                $summary = $dumper->dump();
            } catch (\Throwable $error) {
                $dumper->close();
                @unlink($path);

                throw $error;
            }

            $dumper->close();

            self::remember_progress([
                'status' => 'done',
                'percent' => 100,
                'message' => sprintf(
                    __('Done: %1$d tables, %2$s rows, %3$s.', 'wp-addon'),
                    $summary['tables'],
                    number_format_i18n($summary['rows']),
                    Storage::format_size($summary['bytes'])
                ),
            ]);

            wp_send_json_success([
                'message' => sprintf(
                    __('Backup created: %1$d tables, %2$s.', 'wp-addon'),
                    $summary['tables'],
                    Storage::format_size($summary['bytes'])
                ),
                'file' => basename($path),
            ]);
        } catch (\Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_restore(): void
    {
        self::authorize();

        try {
            $storage = new Storage();
            $name = isset($_POST['backup']) ? sanitize_text_field(wp_unslash((string) $_POST['backup'])) : '';
            $path = $storage->resolve($name);

            self::remember_progress([
                'status' => 'running',
                'percent' => 0,
                'message' => __('Restore started...', 'wp-addon'),
            ]);

            $restorer = new Restorer();
            $restorer->open($path);

            try {
                $result = $restorer->restore();
            } finally {
                $restorer->close();
            }

            self::remember_progress([
                'status' => 'done',
                'percent' => 100,
                'message' => __('Restore completed.', 'wp-addon'),
            ]);

            $message = sprintf(
                __('Database restored: %1$d tables, %2$s rows.', 'wp-addon'),
                $result['tables'],
                number_format_i18n($result['rows'])
            );

            foreach ($result['warnings'] as $warning) {
                $message .= ' ' . $warning;
            }

            wp_send_json_success(['message' => $message]);
        } catch (\Throwable $error) {
            self::remember_progress([
                'status' => 'error',
                'percent' => 0,
                'message' => $error->getMessage(),
            ]);

            self::fail($error);
        }
    }

    public function ajax_delete(): void
    {
        self::authorize();

        try {
            $storage = new Storage();
            $name = isset($_POST['backup']) ? sanitize_text_field(wp_unslash((string) $_POST['backup'])) : '';
            $storage->delete($name);

            wp_send_json_success(['message' => __('Backup file deleted.', 'wp-addon')]);
        } catch (\Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_upload(): void
    {
        self::authorize();

        try {
            if (empty($_FILES['backup_file'])) {
                throw new \RuntimeException(__('File not selected.', 'wp-addon'));
            }

            $storage = new Storage();
            $name = $storage->store_uploaded($_FILES['backup_file']);

            wp_send_json_success([
                'message' => __('Backup uploaded.', 'wp-addon'),
                'file' => $name,
            ]);
        } catch (\Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_list(): void
    {
        self::authorize();

        $storage = new Storage();

        ob_start();
        BackupAdmin::render_backup_table($storage);

        wp_send_json_success(['html' => (string) ob_get_clean()]);
    }

    public function ajax_progress(): void
    {
        self::authorize();

        wp_send_json_success(['progress' => self::recall_progress()]);
    }

    private static function remember_progress(array $progress): void
    {
        update_option(
            'wp_addon_backup_progress',
            $progress + ['time' => time()],
            false
        );
    }

    private static function recall_progress(): array
    {
        $progress = get_option('wp_addon_backup_progress', []);

        if (! is_array($progress)) {
            return [];
        }

        if (($progress['time'] ?? 0) < time() - HOUR_IN_SECONDS) {
            delete_option('wp_addon_backup_progress');

            return [];
        }

        return $progress;
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'wp-addon'));
        }

        ?>
        <div class="wrap wp-addon-backup">
            <h1><?php esc_html_e('Database Backup', 'wp-addon'); ?></h1>

            <p class="wp-addon-backup__lead">
                <?php
                printf(
                    /* translators: %s: table prefix */
                    esc_html__('Snapshot includes schema and data for all tables. Prefix from wp-config.php: %s', 'wp-addon'),
                    '<code>' . esc_html($GLOBALS['wpdb']->prefix) . '</code>'
                );
                ?>
            </p>

            <div class="wp-addon-backup__actions">
                <button type="button" class="button button-primary" id="wp-addon-backup-create">
                    <?php esc_html_e('Create Backup', 'wp-addon'); ?>
                </button>

                <span class="wp-addon-backup__progress" id="wp-addon-backup-progress" hidden>
                    <span class="wp-addon-backup__progress-bar"></span>
                </span>
            </div>

            <div class="wp-addon-backup__status" id="wp-addon-backup-status" role="status" aria-live="polite"></div>

            <h2><?php esc_html_e('Backup Files', 'wp-addon'); ?></h2>
            <div id="wp-addon-backup-list">
                <?php BackupAdmin::render_backup_table(new Storage()); ?>
            </div>

            <h2><?php esc_html_e('Restore', 'wp-addon'); ?></h2>

            <?php if (! self::can_restore()) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Restore requires direct database access.', 'wp-addon'); ?></p>
                </div>
            <?php else : ?>
                <form id="wp-addon-backup-restore-form" method="post">
                    <?php wp_nonce_field(self::NONCE_ACTION, 'nonce'); ?>
                    <select name="backup" id="wp-addon-backup-select">
                        <option value=""><?php esc_html_e('Select backup file...', 'wp-addon'); ?></option>
                        <?php
                        foreach ((new Storage())->list_dumps() as $file) {
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($file),
                                esc_html($file)
                            );
                        }
                        ?>
                    </select>
                    <button type="button" class="button" id="wp-addon-backup-restore">
                        <?php esc_html_e('Restore Selected', 'wp-addon'); ?>
                    </button>
                </form>
            <?php endif; ?>

            <h2><?php esc_html_e('Upload Backup', 'wp-addon'); ?></h2>
            <form id="wp-addon-backup-upload-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field(self::NONCE_ACTION, 'nonce'); ?>
                <input type="file" name="backup_file" id="wp-addon-backup-file" accept=".sql,.zip">
                <button type="button" class="button" id="wp-addon-backup-upload">
                    <?php esc_html_e('Upload', 'wp-addon'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    private static function can_restore(): bool
    {
        return defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_NAME');
    }
}
