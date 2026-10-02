<?php
/**
 * Отрисовка интерфейса бэкапов внутри страницы настроек WP Addon.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Строит разметку блока бэкапов.
 */
final class Admin
{
    /**
     * Таблица со списком дампов и кнопками действий.
     *
     * Каждая строка и кнопка удаления несут data-backup с именем файла —
     * JS берёт имя отсюда, чтобы не дублировать разметку.
     *
     * @param  Storage  $storage  Хранилище дампов.
     */
    public static function render_backup_table(Storage $storage): void
    {
        $backups = $storage->list_backups();

        if ($backups === []) {
            echo '<p>'.esc_html__('Файлов дампов пока нет.', 'wp-addon').'</p>';

            return;
        }
        ?>
        <table class="widefat striped wp-addon-backup__table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Файл', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Размер', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Создан', 'wp-addon'); ?></th>
                    <th><?php echo esc_html__('Действия', 'wp-addon'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $backup) { ?>
                <tr data-backup="<?php echo esc_attr($backup['name']); ?>">
                    <td><code><?php echo esc_html($backup['name']); ?></code></td>
                    <td><?php echo esc_html(Storage::format_size($backup['size'])); ?></td>
                    <td><?php echo esc_html(gmdate('Y-m-d H:i', $backup['modified'])); ?></td>
                    <td>
                        <button type="button" class="button button-small wp-addon-backup-delete"
                                data-backup="<?php echo esc_attr($backup['name']); ?>">
                            <?php echo esc_html__('Удалить', 'wp-addon'); ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Можно ли выполнять восстановление.
     *
     * Восстановление перезаписывает базу, поэтому требует права
     * manage_options и работающего соединения с базой.
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
