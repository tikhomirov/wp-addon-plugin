<?php

use WpAddon\Backup\Admin as BackupAdmin;
use WpAddon\Backup\Dumper;
use WpAddon\Backup\Restorer;
use WpAddon\Backup\Storage;
use WpAddon\Interfaces\ModuleInterface;

/**
 * Модуль бэкапа базы данных для WP Addon.
 *
 * Интерфейс модуля рендерится внутри страницы настроек плагина
 * (секция «Backup DB»), отдельная страница админки не создаётся.
 */
final class Backup implements ModuleInterface
{
    /**
     * Slug страницы настроек WP Addon в админке.
     */
    private const SETTINGS_PAGE_HOOK = 'toplevel_page_wp-addon';

    /**
     * Nonce-действие для всех AJAX-операций модуля.
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
     * Карта AJAX-действий модуля: action => метод-обработчик.
     *
     * Единый источник правды для init() и unit-тестов: тест сверяет
     * эти имена с тем, что вызывает assets/js/backup.js.
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
            'wp_addon_backup_list' => 'ajax_list',
        ];
    }

    /**
     * Подключает стили и скрипты на странице настроек плагина.
     *
     * @param  string  $hook_suffix  Текущий админский хук.
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
                'working' => __('Выполняется…', 'wp-addon'),
                'confirmRestore' => __('Восстановление полностью заменит содержимое базы данных. Продолжить?', 'wp-addon'),
                'confirmDelete' => __('Удалить файл дампа?', 'wp-addon'),
            ],
        ]);
    }

    /**
     * HTML секции «Backup DB» для встраивания в CSF-поле типа content.
     *
     * Без форм и полей с name: кнопки управляются скриптом, nonce
     * передаётся из локализованного объекта, поэтому CSF-сохранение
     * настроек ничего не путает.
     *
     * @param  Storage|null  $storage  Хранилище (для тестов).
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
            /* translators: %s: префикс таблиц из wp-config.php */
            esc_html__('Снимок включает схему и данные всех таблиц. Префикс таблиц: %s', 'wp-addon'),
            '<code>'.esc_html(isset($wpdb->prefix) ? (string) $wpdb->prefix : 'wp_').'</code>'
        );
        ?>
            </p>

            <div class="wp-addon-backup__actions">
                <button type="button" class="button button-primary" id="wp-addon-backup-create">
                    <?php echo esc_html__('Создать дамп', 'wp-addon'); ?>
                </button>

                <span class="wp-addon-backup__progress" id="wp-addon-backup-progress" hidden>
                    <span class="wp-addon-backup__progress-bar"></span>
                </span>
            </div>

            <div class="wp-addon-backup__status" id="wp-addon-backup-status" role="status" aria-live="polite"></div>

            <h3><?php echo esc_html__('Файлы дампов', 'wp-addon'); ?></h3>
            <div id="wp-addon-backup-list">
                <?php BackupAdmin::render_backup_table($storage); ?>
            </div>

            <h3><?php echo esc_html__('Восстановление', 'wp-addon'); ?></h3>

            <?php if (! BackupAdmin::can_restore()) { ?>
                <div class="notice notice-warning inline">
                    <p><?php echo esc_html__('Восстановление недоступно: нет прав или соединения с базой.', 'wp-addon'); ?></p>
                </div>
            <?php } else { ?>
                <p>
                    <select id="wp-addon-backup-select" class="regular-text">
                        <option value=""><?php echo esc_html__('Выберите файл дампа…', 'wp-addon'); ?></option>
                        <?php foreach ($storage->list_backups() as $backup) { ?>
                            <option value="<?php echo esc_attr($backup['name']); ?>">
                                <?php echo esc_html($backup['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <button type="button" class="button" id="wp-addon-backup-restore">
                        <?php echo esc_html__('Восстановить базу', 'wp-addon'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php echo esc_html__('Восстановление полностью заменит содержимое базы данных текущего сайта. Дамп с другим префиксом таблиц будет отклонён.', 'wp-addon'); ?>
                </p>
            <?php } ?>

            <h3><?php echo esc_html__('Загрузка дампа', 'wp-addon'); ?></h3>
            <p class="description">
                <?php echo esc_html__('Загрузите .sql-дамп, снятый этим же модулем, чтобы восстановить его на этом сайте.', 'wp-addon'); ?>
            </p>
            <p>
                <input type="file" id="wp-addon-backup-upload" accept=".sql">
                <button type="button" class="button" id="wp-addon-backup-upload-btn">
                    <?php echo esc_html__('Загрузить дамп', 'wp-addon'); ?>
                </button>
            </p>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Проверяет права и nonce для AJAX-операций модуля.
     */
    private static function authorize(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Недостаточно прав.', 'wp-addon')],
                403
            );
        }

        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['nonce'])) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(
                ['message' => __('Сессия истекла, обновите страницу.', 'wp-addon')],
                403
            );
        }
    }

    /**
     * Отвечает ошибкой с текстом исключения.
     */
    private static function fail(Throwable $error): void
    {
        $message = trim($error->getMessage());

        if ($message === '') {
            $message = __('Неизвестная ошибка.', 'wp-addon');
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
                    /* translators: 1: таблиц, 2: размер */
                    __('Дамп создан: %1$d таблиц, %2$s.', 'wp-addon'),
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

            $message = sprintf(
                /* translators: 1: таблиц, 2: строк */
                __('База восстановлена: %1$d таблиц, %2$s строк.', 'wp-addon'),
                $result['tables'],
                number_format_i18n($result['rows'])
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

            wp_send_json_success(['message' => __('Файл дампа удалён.', 'wp-addon')]);
        } catch (Throwable $error) {
            self::fail($error);
        }
    }

    public function ajax_upload(): void
    {
        self::authorize();

        try {
            if (empty($_FILES['backup_file'])) {
                throw new RuntimeException(__('Файл не выбран.', 'wp-addon'));
            }

            $storage = new Storage;
            $name = $storage->store_uploaded($_FILES['backup_file']);

            wp_send_json_success([
                'message' => __('Дамп загружен.', 'wp-addon'),
                'file' => $name,
            ]);
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
