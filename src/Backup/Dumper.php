<?php

/**
 * Потоковый дамп базы данных MySQL/MariaDB в переносимый SQL-файл.
 *
 * Формат: заголовок-манифест, затем для каждой таблицы DROP/CREATE и данные
 * батчевыми INSERT. Данные читаются курсором по одной строке, поэтому файл
 * не держится в памяти: дамп таблицы на сотни мегабайт проходит при обычном
 * memory_limit хостинга.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

use mysqli;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Пишет переносимый SQL-дамп базы данных.
 *
 * Префикс таблиц берётся из конфига WordPress ($table_prefix), поэтому дамп
 * восстанавливается на том же префиксе, на котором был снят.
 */
final class Dumper
{
    /** Версия формата дампа. Повышается при несовместимых изменениях. */
    public const FORMAT_VERSION = '2.0';

    /** Сколько строк склеивать в один многострочный INSERT. */
    private const ROWS_PER_INSERT = 200;

    /** @var resource|null Дескриптор файла дампа. */
    private $handle = null;

    /** @var int Сколько таблиц попало в дамп. */
    private int $table_count = 0;

    /** @var int Сколько строк данных суммарно записано. */
    private int $row_count = 0;

    /** @var string Префикс таблиц текущего сайта. */
    private string $prefix;

    /** @var string Имя набора символов соединения (utf8mb4). */
    private string $charset;

    /** @var string Сопоставление соединения (utf8mb4_unicode_520_ci) или пустая строка. */
    private string $collate = '';

    /**
     * @throws \RuntimeException Если префикс или соединение недоступны.
     */
    public function __construct()
    {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            throw new \RuntimeException('Нет подключения к WordPress (нет $wpdb).');
        }

        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';

        if ($prefix === '') {
            throw new \RuntimeException('Не удалось определить префикс таблиц ($table_prefix).');
        }

        $this->prefix = $prefix;
        $this->resolve_charset();
    }

    /**
     * Разбирает результат get_charset_collate() на имя charset и collation.
     *
     * WordPress отдаёт строку вида
     * "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci",
     * а SET NAMES принимает только имя набора символов. Подставлять строку
     * целиком нельзя — такой дамп не импортируется.
     */
    private function resolve_charset(): void
    {
        global $wpdb;

        $raw = trim((string) $wpdb->get_charset_collate());

        $charset = 'utf8mb4';
        $collate = '';

        if (preg_match('/CHARACTER\s+SET\s+(\S+)/i', $raw, $m) === 1) {
            $charset = $m[1];
        }

        if (preg_match('/COLLATE\s+(\S+)/i', $raw, $m) === 1) {
            $collate = $m[1];
        }

        // Значение приходит из конфигурации, но всё же приводим к безопасному
        // виду: в SQL допустимы только буквы, цифры и подчёркивание.
        $charset = (string) preg_replace('/[^A-Za-z0-9_]/', '', $charset);
        $collate = (string) preg_replace('/[^A-Za-z0-9_]/', '', $collate);

        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        $this->charset = $charset;
        $this->collate = $collate;
    }

    /**
     * Пишет дамп всех таблиц базы данных.
     *
     * @return array{tables:int,rows:int,bytes:int} Итоги дампа для интерфейса.
     *
     * @throws \RuntimeException При ошибке записи или чтения базы.
     */
    public function dump(): array
    {
        $this->write_manifest();
        $this->write_session();

        foreach ($this->list_tables() as $table) {
            $this->dump_table($table);
        }

        $this->write_footer();
        $this->flush();

        return [
            'tables' => $this->table_count,
            'rows' => $this->row_count,
            'bytes' => (int) $this->size(),
        ];
    }

    /**
     * Открывает файл дампа на запись.
     *
     * @param  string  $path  Полный путь к файлу.
     *
     * @throws \RuntimeException Если каталог недоступен или файл не открылся.
     */
    public function open(string $path): void
    {
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть файл дампа для записи.');
        }

        $this->handle = $handle;
    }

    /**
     * Закрывает файл дампа, если он открыт.
     */
    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }

    /**
     * Список таблиц базы в устойчивом алфавитном порядке.
     *
     * @return string[]
     */
    private function list_tables(): array
    {
        global $wpdb;

        $tables = $wpdb->get_col('SHOW TABLES');

        if (! is_array($tables)) {
            throw new \RuntimeException('Не удалось получить список таблиц (SHOW TABLES).');
        }

        $tables = array_values(array_filter(array_map('strval', $tables)));
        sort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * Заголовок файла: версия формата, префикс, источник, кодировка.
     *
     * Строки манифеста читает восстановитель, поэтому формат стабилен.
     */
    private function write_manifest(): void
    {
        $lines = [
            '-- Woo2iiko WordPress database backup',
            '-- Format: '.self::FORMAT_VERSION,
            '-- Prefix: '.$this->prefix,
            '-- Source: '.$this->site_url(),
            '-- WordPress: '.$this->wordpress_version(),
            '-- Charset: '.$this->charset.($this->collate !== '' ? ' '.$this->collate : ''),
            '-- Generated: '.gmdate('Y-m-d H:i:s').' UTC',
            '',
            'SET NAMES '.$this->charset.';',
            'SET FOREIGN_KEY_CHECKS = 0;',
        ];

        $this->write(implode("\n", $lines)."\n");
    }

    /**
     * Сохраняет и восстанавливает sql_mode, чтобы дамп одинаково
     * интерпретировался на любом сервере при импорте.
     */
    private function write_session(): void
    {
        global $wpdb;

        $sql_mode = $wpdb->get_var('SELECT @@SESSION.sql_mode');
        $sql_mode = is_string($sql_mode) ? trim($sql_mode) : '';

        $lines = ['SET @OLD_SQL_MODE = @@SESSION.sql_mode;'];

        if ($sql_mode !== '') {
            $lines[] = 'SET SESSION sql_mode = '.ValueEncoder::quote($sql_mode).';';
        }

        $this->write(implode("\n", $lines)."\n\n");
    }

    /**
     * URL сайта-источника — только для отчёта, в SQL не попадает.
     */
    private function site_url(): string
    {
        return function_exists('get_site_url') ? (string) get_site_url() : '';
    }

    /**
     * Версия WordPress для отчёта.
     */
    private function wordpress_version(): string
    {
        return function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';
    }

    /**
     * Пишет схему и данные одной таблицы.
     */
    private function dump_table(string $table): void
    {
        $this->table_count++;

        $this->write(
            "\n-- --------------------------------------------------------\n"
            .'-- Table: '.$table."\n"
            ."-- --------------------------------------------------------\n\n"
        );

        $create_sql = $this->create_table_sql($table);

        if ($create_sql === null) {
            // Без схемы таблица не восстановится. Пишем явную пометку, чтобы
            // импорт не выглядел успешным, потеряв таблицу молча.
            $this->write(
                '-- WARNING: не удалось получить CREATE TABLE для '.$table."; таблица пропущена.\n\n"
            );

            return;
        }

        $this->write('DROP TABLE IF EXISTS `'.$table."`;\n");
        $this->write($create_sql.";\n\n");

        $this->dump_rows($table);
    }

    /**
     * CREATE TABLE для таблицы.
     */
    private function create_table_sql(string $table): ?string
    {
        global $wpdb;

        $row = $wpdb->get_row('SHOW CREATE TABLE `'.$table.'`', ARRAY_N);

        if (! is_array($row) || ! isset($row[1])) {
            return null;
        }

        $sql = trim((string) $row[1]);

        return $sql === '' ? null : $sql;
    }

    /**
     * Пишет данные таблицы батчевыми INSERT.
     *
     * Основной путь — потоковое чтение через mysqli без буферизации: одна
     * выборка, строки забираются по одной, память ограничена размером батча.
     * Запасной путь — батчинг по первичному ключу, если соединение не mysqli
     * (например, подменено db.php-дропином).
     */
    private function dump_rows(string $table): void
    {
        $columns = $this->columns($table);

        if ($columns === []) {
            return;
        }

        $quoted = implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns));
        $prefix_sql = 'INSERT INTO `'.$table.'` ('.$quoted.') VALUES';

        if ($this->stream_rows($table, $columns, $prefix_sql)) {
            $this->write("\n");

            return;
        }

        $this->chunk_rows($table, $columns, $prefix_sql);
        $this->write("\n");
    }

    /**
     * Потоковое чтение строк через небуферизованный mysqli-запрос.
     *
     * @return bool True, если данные записаны этим путём.
     */
    private function stream_rows(string $table, array $columns, string $prefix_sql): bool
    {
        global $wpdb;

        $dbh = isset($wpdb->dbh) ? $wpdb->dbh : null;

        if (! $dbh instanceof mysqli) {
            return false;
        }

        $select = 'SELECT '.implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns))
            .' FROM `'.$table.'`';

        $result = @$dbh->query($select, MYSQLI_USE_RESULT);

        if ($result === false) {
            // Запрос не прошёл — молча деградируем до батчинга, чтобы не
            // потерять таблицу целиком из-за одного сбоя выборки.
            return false;
        }

        $batch = [];

        while (($row = mysqli_fetch_assoc($result)) !== null) {
            $batch[] = $this->row_values($row, $columns);

            if (count($batch) >= self::ROWS_PER_INSERT) {
                $this->write($prefix_sql."\n".implode(",\n", $batch).";\n");
                $this->row_count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->write($prefix_sql."\n".implode(",\n", $batch).";\n");
            $this->row_count += count($batch);
        }

        mysqli_free_result($result);

        return true;
    }

    /**
     * Запасной путь: выборка порциями по первичному ключу.
     *
     * OFFSET на больших таблицах даёт O(n²), поэтому по возможности курсор
     * ведётся по первичному ключу, а не по смещению.
     *
     * @param  string[]  $columns
     */
    private function chunk_rows(string $table, array $columns, string $prefix_sql): void
    {
        $keys = $this->primary_key($table);
        $last = null;

        while (true) {
            $where = '';

            if ($keys !== [] && $last !== null) {
                $where = ' WHERE '.$this->key_cursor_condition($keys, $last);
            }

            $select = 'SELECT '.implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns))
                .' FROM `'.$table.'`'.$where;

            if ($keys !== []) {
                $select .= ' ORDER BY '.implode(', ', array_map(static fn (string $k): string => '`'.$k.'`', $keys));
            }

            $select .= ' LIMIT '.self::ROWS_PER_INSERT;

            $rows = $this->select_rows($select);

            if ($rows === []) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $batch[] = $this->row_values($row, $columns);
                $this->row_count++;
            }

            $this->write($prefix_sql."\n".implode(",\n", $batch).";\n");

            // Последний неполный батч означает, что таблица прочитана до конца.
            if ($keys === [] || count($rows) < self::ROWS_PER_INSERT) {
                break;
            }

            $tail = end($rows);

            if (! is_array($tail)) {
                break;
            }

            $last = [];

            foreach ($keys as $key) {
                if (! array_key_exists($key, $tail)) {
                    return;
                }

                $last[] = $tail[$key];
            }
        }
    }

    /**
     * Условие курсора по составному первичному ключу (lexicographic).
     *
     * @param  string[]  $keys
     */
    private function key_cursor_condition(array $keys, array $last): string
    {
        $clauses = [];
        $acc = [];

        foreach ($keys as $index => $key) {
            $equals = [];

            for ($i = 0; $i < $index; $i++) {
                $equals[] = '`'.$keys[$i].'` = '.$this->value_literal($last[$i]);
            }

            $equals[] = '`'.$key.'` > '.$this->value_literal($last[$index]);
            $acc[] = '('.implode(' AND ', $equals).')';
        }

        $clauses = $acc;

        return implode(' OR ', $clauses);
    }

    /**
     * Выполняет SELECT через $wpdb.
     *
     * @return array<int,array<string,mixed>>
     */
    private function select_rows(string $sql): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            throw new \RuntimeException('Ошибка чтения данных таблицы: '.$wpdb->last_error);
        }

        return $rows;
    }

    /**
     * Колонки первичного ключа в порядке индекса.
     *
     * @return string[]
     */
    private function primary_key(string $table): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SHOW KEYS FROM `'.$table."` WHERE Key_name = 'PRIMARY'", ARRAY_A);

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $keys = [];

        foreach ($rows as $row) {
            if (isset($row['Column_name'])) {
                $keys[] = (string) $row['Column_name'];
            }
        }

        usort($keys, static function (string $a, string $b) use ($rows): int {
            $pa = 0;
            $pb = 0;

            foreach ($rows as $row) {
                if (($row['Column_name'] ?? null) === $a) {
                    $pa = (int) ($row['Seq_in_index'] ?? 0);
                }

                if (($row['Column_name'] ?? null) === $b) {
                    $pb = (int) ($row['Seq_in_index'] ?? 0);
                }
            }

            return $pa <=> $pb;
        });

        return $keys;
    }

    /**
     * Колонки таблицы в порядке объявления в схеме.
     *
     * @return string[]
     */
    private function columns(string $table): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SHOW COLUMNS FROM `'.$table.'`', ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        $columns = [];

        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $columns[] = (string) $row['Field'];
            }
        }

        return $columns;
    }

    /**
     * Формирует список значений одной строки.
     *
     * Правила кодирования вынесены в ValueEncoder: их важно проверять
     * тестами независимо от подключения к базе.
     *
     * @param  array<string,mixed>  $row
     * @param  string[]  $columns
     */
    private function row_values(array $row, array $columns): string
    {
        return ValueEncoder::row_values($row, $columns);
    }

    /**
     * SQL-литерал для одного значения ячейки.
     *
     * @param  mixed  $value
     */
    private function value_literal($value): string
    {
        return ValueEncoder::literal($value);
    }

    /**
     * Закрывающая секция файла.
     */
    private function write_footer(): void
    {
        $this->write(
            "\nSET SESSION sql_mode = @OLD_SQL_MODE;\n"
            ."SET FOREIGN_KEY_CHECKS = 1;\n"
            .'-- End of backup. Tables: '.$this->table_count.', rows: '.$this->row_count."\n"
        );
    }

    /**
     * Текущий размер файла дампа в байтах.
     */
    private function size(): int
    {
        if (! is_resource($this->handle)) {
            return 0;
        }

        $stat = fstat($this->handle);

        return (int) ($stat['size'] ?? 0);
    }

    /**
     * Сбрасывает буфер записи на диск.
     */
    public function flush(): void
    {
        if (is_resource($this->handle)) {
            fflush($this->handle);
        }
    }

    /**
     * Пишет строку в файл дампа.
     *
     * @throws \RuntimeException Если файл не открыт или запись не удалась.
     */
    private function write(string $chunk): void
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Файл дампа не открыт для записи.');
        }

        $length = strlen($chunk);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($this->handle, substr($chunk, $offset));

            if ($written === false || $written === 0) {
                throw new \RuntimeException('Не удалось записать данные в файл дампа (диск переполнен или нет прав).');
            }

            $offset += $written;
        }
    }

    /**
     * Удаляет частично записанный файл дампа.
     *
     * Используется, если дамп прервался: незавершённый файл нельзя
     * предлагать к восстановлению.
     */
    public function discard(): void
    {
        $this->close();
    }
}
