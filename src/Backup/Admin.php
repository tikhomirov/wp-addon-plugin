<?php
/**
 * Backup UI rendered inside the WP Addon settings page.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Builds backup block markup.
 */
final class Admin
{
    /**
     * Table of backups with action buttons.
     *
     * Each row carries data-backup so JS does not duplicate markup.
     *
     * @param Storage $storage Backup storage.
     */
    public static function render_backup_table(Storage $storage): void
    {
        $backups = $storage->list_backups();

        if ($backups === []) {
            echo '<p>' . esc_html__('No backup files yet.', 'wp-addon') . '</p>';

            return;
        }
        ?>
        <table class="widefat striped wp-addon-backup__table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('File', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Size', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Created', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Actions', 'wp-addon'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $backup) { ?>
                <tr data-backup="<?php echo esc_attr($backup['name']); ?>">
                    <td><code><?php echo esc_html($backup['name']); ?></code></td>
                    <td><?php echo esc_html(Storage::format_size($backup['size'])); ?></td>
                    <td><?php echo esc_html(gmdate('Y-m-d H:i', $backup['modified'])); ?></td>
                    <td>
                        <button type="button" class="button button-small wp-addon-backup-restore"
                                data-backup="<?php echo esc_attr($backup['name']); ?>">
                            <?php echo esc_html__('Restore', 'wp-addon'); ?>
                        </button>
                        <button type="button" class="button button-small wp-addon-backup-download"
                                data-backup="<?php echo esc_attr($backup['name']); ?>">
                            <?php echo esc_html__('Download', 'wp-addon'); ?>
                        </button>
                        <button type="button" class="button button-small wp-addon-backup-delete"
                                data-backup="<?php echo esc_attr($backup['name']); ?>">
                            <?php echo esc_html__('Delete', 'wp-addon'); ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Whether restore is allowed (manage_options and live DB connection).
     */
    public static function can_restore(): bool
    {
        global $wpdb;

        if (! current_user_can('manage_options')) {
            return false;
        }

        return isset($wpdb) && is_object($wpdb) && $wpdb->prefix !== null;
    }
}
