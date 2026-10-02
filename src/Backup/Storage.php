<?php
/**
 * Хранилище дампов: безопасный доступ к каталогу, поиск, удаление, приём загрузок.
 *
 * @package WpAddon
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Отвечает за файлы дампов в uploads/backup.
 */
final class Storage
{
    /** Имя каталога с дампами внутри wp-content/uploads. */
    public const DIRNAME = 'backup';

    /** Префикс имён файлов дампов. */
    private const PREFIX = 'backup_';

    /** Расширение дампа. */
    private const EXTENSION = '.sql';

    /** Подкаталог для загруженных извне дампов. */
    private const UPLOADED_DIRNAME = 'uploaded';

    /** @var string Абсолютный путь к корневому каталогу дампов. */
    private string $root;

    /**
     * @param string|null $root Каталог дампов; по умолчанию uploads/backup.
     *
     * @throws \RuntimeException Если каталог не удаётся создать.
     */
    public function __construct(?string $root = null)
    {
        $base = $root ?? (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : '');
        $this->root = rtrim($base, '/') . '/' . self::DIRNAME;
    }

    /**
     * Абсолютный путь к каталогу дампов.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Каталог для загруженных дампов.
     */
    public function uploaded_root(): string
    {
        return $this->root . '/' . self::UPLOADED_DIRNAME;
    }

    /**
     * Создаёт каталоги и закрывает их от веб-доступа.
     *
     * Файлы .htaccess и index.php — единственная защита, доступная на
     * shared-хостинге, где nginx/apache настроены снаружи.
     *
     * @throws \RuntimeException Если каталоги недоступны для записи.
     */
    public function ensure_directories(): void
    {
        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
                throw new \RuntimeException(__('Не удалось создать каталог для бэкапов.', 'wp-addon'));
            }

            $this->write_protection($dir);
        }
    }

    /**
     * Кладёт в каталог заглушки, запрещающие прямой доступ по HTTP.
     */
    private function write_protection(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        $index = $dir . '/index.php';

        if (! is_file($htaccess)) {
            $rules = "# Создано плагином бэкапов: запрет прямого доступа к дампам.\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n";

            @file_put_contents($htaccess, $rules);
        }

        if (! is_file($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\nexit;\n");
        }
    }

    /**
     * Список дампов: имя, размер, дата изменения.
     *
     * @return array<int,array{name:string,size:int,modified:int}>
     */
    public function list_backups(): array
    {
        $backups = [];

        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $entries = @scandir($dir);

            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! $this->is_backup_name($entry)) {
                    continue;
                }

                $path = $dir . '/' . $entry;

                if (! is_file($path)) {
                    continue;
                }

                $backups[] = [
                    'name' => $entry,
                    'size' => (int) @filesize($path),
                    'modified' => (int) @filemtime($path),
                ];
            }
        }

        usort($backups, static function (array $a, array $b): int {
            $by_date = $b['modified'] <=> $a['modified'];

            return $by_date !== 0 ? $by_date : strcmp($b['name'], $a['name']);
        });

        return $backups;
    }

    /**
     * Разрешает имя файла в путь внутри каталога дампов.
     *
     * Защита от path traversal: имя проверяется по белому списку, затем
     * реальный путь сверяется с корнем каталога. Это единственная точка,
     * где пользовательская строка превращается в путь файла.
     *
     * @param string $name Имя файла из запроса.
     *
     * @return string Абсолютный путь к файлу.
     *
     * @throws \RuntimeException Если имя недопустимо или файл вне каталога.
     */
    public function resolve(string $name): string
    {
        $safe = basename(trim($name));

        if ($safe === '' || $safe === '.' || $safe === '..' || ! $this->is_backup_name($safe)) {
            throw new \RuntimeException(__('Недопустимое имя файла дампа.', 'wp-addon'));
        }

        $found = $this->find($safe);

        if ($found === null) {
            throw new \RuntimeException(__('Файл дампа не найден.', 'wp-addon'));
        }

        return $found;
    }

    /**
     * Ищет файл дампа в корневом каталоге и в каталоге загрузок.
     */
    private function find(string $name): ?string
    {
        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            $candidate = $dir . '/' . $name;

            if (! is_file($candidate)) {
                continue;
            }

            $real = @realpath($candidate);
            $real_root = @realpath($dir);

            if ($real === false || $real_root === false) {
                continue;
            }

            // Симлинк или подпапка вне каталога — отказ.
            if (strncmp($real, $real_root . DIRECTORY_SEPARATOR, strlen($real_root) + 1) !== 0) {
                continue;
            }

            return $real;
        }

        return null;
    }

    /**
     * Проверяет, что имя соответствует шаблону имени дампа.
     *
     * Разрешён только формат backup_<метка>.sql либо загруженный
     * upload_<метка>.sql — это исключает попытки указать index.php,
     * .htaccess или любой другой файл каталога.
     */
    public function is_backup_name(string $name): bool
    {
        return (bool) preg_match(
            '/^(?:backup|upload)_[A-Za-z0-9._-]+' . preg_quote(self::EXTENSION, '/') . '$/',
            basename($name)
        );
    }

    /**
     * Удаляет дамп.
     *
     * @throws \RuntimeException Если файл не найден или не удалён.
     */
    public function delete(string $name): void
    {
        $path = $this->resolve($name);

        if (! @unlink($path)) {
            throw new \RuntimeException(__('Не удалось удалить файл дампа.', 'wp-addon'));
        }
    }

    /**
     * Свободный путь для нового дампа с меткой времени.
     */
    public function new_dump_path(string $label): string
    {
        return $this->root . '/' . self::PREFIX . $label . self::EXTENSION;
    }

    /**
     * Принимает загруженный пользователем файл дампа.
     *
     * Содержимое проверяется по маркеру формата: это отсекает попытки
     * положить в каталог что-то, что потом будет исполнено как SQL.
     *
     * @param array $file Элемент $_FILES.
     *
     * @return string Имя сохранённого файла.
     *
     * @throws \RuntimeException При некорректной загрузке или содержимом.
     */
    public function store_uploaded(array $file): string
    {
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %d: код ошибки загрузки */
                    __('Файл не загружен (код ошибки: %d).', 'wp-addon'),
                    $error
                )
            );
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new \RuntimeException(__('Некорректная загрузка файла.', 'wp-addon'));
        }

        $size = (int) @filesize($tmp);

        if ($size < 32) {
            throw new \RuntimeException(__('Файл слишком мал, это не дамп базы данных.', 'wp-addon'));
        }

        $head = (string) @file_get_contents($tmp, false, null, 0, 4096);

        if (! $this->looks_like_dump($head)) {
            throw new \RuntimeException(__('Файл не похож на дамп базы WordPress.', 'wp-addon'));
        }

        $this->ensure_directories();

        $name = 'upload_' . gmdate('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . self::EXTENSION;
        $target = $this->uploaded_root() . '/' . $name;

        $moved = @move_uploaded_file($tmp, $target);

        if (! $moved) {
            throw new \RuntimeException(__('Не удалось сохранить загруженный дамп.', 'wp-addon'));
        }

        $this->write_protection($this->uploaded_root());

        return $name;
    }

    /**
     * Похоже ли начало файла на SQL-дамп.
     *
     * Принимается и дамп этого плагина (по его маркеру), и выгрузка
     * сторонних инструментов — phpMyAdmin, mysqldump. Иначе перенос дампа
     * между сайтами ломался бы об импорте из phpMyAdmin.
     *
     * Отдельно проверяется, что файл вообще похож на SQL: это отсекает
     * попытки загрузить исполняемый код.
     */
    public function looks_like_dump(string $head): bool
    {
        if ($head === '' || ! mb_check_encoding($head, 'UTF-8')) {
            return false;
        }

        if (str_contains($head, '-- Woo2iiko WordPress database backup')) {
            return true;
        }

        // Достаточно одного характерного маркера SQL-дампа.
        return (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|--\s*MySQL\s+dump|--\s*Table\s+structure)/i', $head);
    }

    /**
     * Человекочитаемый размер файла.
     */
    public static function format_size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            ++$index;
        }

        return round($value, 1) . ' ' . $units[$index];
    }
}
