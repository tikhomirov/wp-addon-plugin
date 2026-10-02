<?php
/**
 * Отрисовка страницы бэкапов в админке.
 *
 * @package WpAddon
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Строит разметку страницы бэкапов.
 */
final class Admin
{
    /**
     * Рендерит страницу плагина.
     *
     * @param Storage $storage Хранилище дампов.
     */
    public static function render(Storage $storage): void
    {
        $prefix = self::table_prefix();
        ?>
        <div class="wrap wp-backup">
            <h1><?php esc_html_e('Бэкапы базы данных', 'wp-addon'); ?></h1>

            <p class="wp-backup__lead">
                <?php
                printf(
                    /* translators: %s: префикс таблиц из wp-config.php */
                    esc_html__('Снимок включает схему и данные всех таблиц. Префикс берётся из wp-config.php: %s', 'wp-addon'),
                    '<code>' . esc_html($prefix) . '</code>'
                );
                ?>
            </p>

            <div class="wp-backup__actions">
                <button type="button" class="button button-primary" id="wp-backup-create">
                    <?php esc_html_e('Создать дамп', 'wp-addon'); ?>
                </button>

                <span class="wp-backup__progress" id="wp-backup-progress" hidden>
                    <span class="wp-backup__progress-bar"></span>
                </span>
            </div>

            <div class="wp-backup__status" id="wp-backup-status" role="status" aria-live="polite"></div>

            <h2><?php esc_html_e('Файлы дампов', 'wp-addon'); ?></h2>
            <div id="wp-backup-list">
                <?php self::render_backup_table($storage); ?>
            </div>

            <h2><?php esc_html_e('Восстановление', 'wp-addon'); ?></h2>

            <?php if (! self::can_restore()) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e('Восстановление недоступно: корневая директория WordPress недоступна для записи.', 'wp-addon'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form id="wp-backup-restore-form" class="wp-backup__form">
                <p>
                    <label for="wp-backup-select"><?php esc_html_e('Файл дампа:', 'wp-addon'); ?></label><br>
                    <select name="backup" id="wp-backup-select" class="regular-text"></select>
                </p>
                <p>
                    <button type="button" class="button" id="wp-backup-restore">
                        <?php esc_html_e('Восстановить базу', 'wp-addon'); ?>
                    </button>
                </p>
            </form>

            <h2><?php esc_html_e('Загрузка дампа', 'wp-addon'); ?></h2>
            <p class="description">
                <?php esc_html_e('Загрузите дамп, снятый на другом сайте, чтобы восстановить его на этом.', 'wp-addon'); ?>
            </p>

            <form id="wp-backup-upload-form" enctype="multipart/form-data" class="wp-backup__form">
                <p>
                    <input type="file" name="backup_file" id="wp-backup-upload" accept=".sql,text/plain" required>
                </p>
                <p>
                    <button type="submit" class="button"><?php esc_html_e('Загрузить дамп', 'wp-addon'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Таблица со списком дампов и кнопками действий.
     *
     * @param Storage $storage Хранилище дампов.
     */
    public static function render_backup_table(Storage $storage): void
    {
        $backups = $storage->list_backups();

        if ($backups === []) {
            echo '<p>' . esc_html__('Файлов дампов пока нет.', 'wp-addon') . '</p>';

            return;
        }
        ?>
        <table class="widefat striped wp-backup__table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Файл', 'wp-addon'); ?></th>
                    <th><?php esc_html_e('Размер', 'wp-addon'); ?></th>
                    <th><?php esc_html_e('Создан', 'wp-addon'); ?></th>
                    <th><?php esc_html_e('Действия', 'wp-addon'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $backup) : ?>
                <tr data-backup="<?php echo esc_attr($backup['name']); ?>">
                    <td><code><?php echo esc_html($backup['name']); ?></code></td>
                    <td><?php echo esc_html(Storage::format_size($backup['size'])); ?></td>
                    <td>
                        <?php
                        echo esc_html(
                            date_i18n('Y-m-d H:i', $backup['modified'])
                        );
                        ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small wp-backup-delete"
                                data-backup="<?php echo esc_attr($backup['name']); ?>">
                            <?php esc_html_e('Удалить', 'wp-addon'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Доступен ли префикс таблиц.
     */
    private static function table_prefix(): string
    {
        global $wpdb;

        return isset($wpdb->prefix) ? (string) $wpdb->prefix : 'wp_';
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
