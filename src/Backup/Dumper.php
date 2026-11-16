<?php

/**
 * Streaming MySQL/MariaDB database dump to a portable SQL file.
 *
 * Format: manifest header, then per-table DROP/CREATE and data
 * as batched INSERTs. Rows are read one at a time so the file
 * stays memory-efficient: large tables work with normal
 * hosting memory_limit.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

use mysqli;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Writes a portable SQL database dump.
 *
 * Table prefix comes from WordPress ($table_prefix); the dump
 * restores on the same prefix it was taken from.
 */
final class Dumper
{
    /** Dump format version; bump on incompatible changes. */
    public const FORMAT_VERSION = '2.0';

    /** Rows per multi-row INSERT batch. */
    private const ROWS_PER_INSERT = 200;

    /** @var resource|null Dump file handle. */
    private $handle = null;

    /** @var int Tables written to the dump. */
    private int $table_count = 0;

    /** @var int Total data rows written. */
    private int $row_count = 0;

    /** @var string Current site table prefix. */
    private string $prefix;

    /** @var string Connection charset name (utf8mb4). */
    private string $charset;

    /** @var string Connection collation or empty string. */
    private string $collate = '';

    /**
     * @throws \RuntimeException If prefix or connection is unavailable.
     */
    public function __construct()
    {
        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb)) {
            throw new \RuntimeException('No WordPress database connection ($wpdb missing).');
        }

        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';

        if ($prefix === '') {
            throw new \RuntimeException('Could not determine table prefix ($table_prefix).');
        }

        $this->prefix = $prefix;
        $this->resolve_charset();
    }

    /**
     * Parse get_charset_collate() into charset and collation names.
     *
     * WordPress returns a string like
     * "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci",
     * SET NAMES accepts only the charset name; do not pass
     * the full string — imports would fail.
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

        // Sanitize values from config for SQL identifiers:
        // letters, digits, and underscore only.
        $charset = (string) preg_replace('/[^A-Za-z0-9_]/', '', $charset);
        $collate = (string) preg_replace('/[^A-Za-z0-9_]/', '', $collate);

        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        $this->charset = $charset;
        $this->collate = $collate;
    }

    /**
     * Write dump of all database tables.
     *
     * @return array{tables:int,rows:int,bytes:int} Dump totals for the UI.
     *
     * @throws \RuntimeException On write/read failure.
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
            'rows'   => $this->row_count,
            'bytes'  => (int) $this->size(),
        ];
    }

    /**
     * Open dump file for writing.
     *
     * @param string $path Absolute file path.
     *
     * @throws \RuntimeException If directory or file open fails.
     */
    public function open(string $path): void
    {
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Could not open backup file for writing.');
        }

        $this->handle = $handle;
    }

    /**
     * Close dump file if open.
     */
    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }

    /**
     * List tables in stable alphabetical order.
     *
     * @return string[]
     */
    private function list_tables(): array
    {
        global $wpdb;

        $tables = $wpdb->get_col('SHOW TABLES');

        if (! is_array($tables)) {
            throw new \RuntimeException('Could not list tables (SHOW TABLES).');
        }

        $tables = array_values(array_filter(array_map('strval', $tables)));
        sort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * File header: format version, prefix, source, charset.
     *
     * Manifest lines are read by the restorer; keep format stable.
     */
    private function write_manifest(): void
    {
        $lines = [
            '-- Woo2iiko WordPress database backup',
            '-- Format: ' . self::FORMAT_VERSION,
            '-- Prefix: ' . $this->prefix,
            '-- Source: ' . $this->site_url(),
            '-- WordPress: ' . $this->wordpress_version(),
            '-- Charset: ' . $this->charset . ($this->collate !== '' ? ' ' . $this->collate : ''),
            '-- Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC',
            '',
            'SET NAMES ' . $this->charset . ';',
            'SET FOREIGN_KEY_CHECKS = 0;',
        ];

        $this->write(implode("\n", $lines) . "\n");
    }

    /**
     * Save/restore sql_mode so dumps
     * interpret consistently on import.
     */
    private function write_session(): void
    {
        global $wpdb;

        $sql_mode = $wpdb->get_var('SELECT @@SESSION.sql_mode');
        $sql_mode = is_string($sql_mode) ? trim($sql_mode) : '';

        $lines = ['SET @OLD_SQL_MODE = @@SESSION.sql_mode;'];

        if ($sql_mode !== '') {
            $lines[] = 'SET SESSION sql_mode = ' . ValueEncoder::quote($sql_mode) . ';';
        }

        $this->write(implode("\n", $lines) . "\n\n");
    }

    /**
     * Source site URL is metadata only, not in SQL.
     */
    private function site_url(): string
    {
        return function_exists('get_site_url') ? (string) get_site_url() : '';
    }

    /**
     * WordPress version for reporting.
     */
    private function wordpress_version(): string
    {
        return function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';
    }

    /**
     * Write schema and data for one table.
     */
    private function dump_table(string $table): void
    {
        $this->table_count++;

        $this->write(
            "\n-- --------------------------------------------------------\n"
            . '-- Table: ' . $table . "\n"
            . "-- --------------------------------------------------------\n\n",
        );

        $create_sql = $this->create_table_sql($table);

        if ($create_sql === null) {
            // Without schema the table cannot restore; write a warning so
            // import does not look successful while missing a table.
            $this->write(
                '-- WARNING: could not get CREATE TABLE for ' . $table . "; table skipped.\n\n",
            );

            return;
        }

        $this->write('DROP TABLE IF EXISTS `' . $table . "`;\n");
        $this->write($create_sql . ";\n\n");

        $this->dump_rows($table);
    }

    /**
     * CREATE TABLE for a table.
     */
    private function create_table_sql(string $table): ?string
    {
        global $wpdb;

        $row = $wpdb->get_row('SHOW CREATE TABLE `' . $table . '`', ARRAY_N);

        if (! is_array($row) || ! isset($row[1])) {
            return null;
        }

        $sql = trim((string) $row[1]);

        return $sql === '' ? null : $sql;
    }

    /**
     * Write table data as batched INSERTs.
     *
     * Primary path: unbuffered mysqli read, one
     * query, rows one-by-one, memory bounded by batch size.
     * Fallback: batched reads by primary key when mysqli
     * is unavailable (e.g. db.php drop-in).
     */
    private function dump_rows(string $table): void
    {
        $columns = $this->columns($table);

        if ($columns === []) {
            return;
        }

        $quoted = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns));
        $prefix_sql = 'INSERT INTO `' . $table . '` (' . $quoted . ') VALUES';

        if ($this->stream_rows($table, $columns, $prefix_sql)) {
            $this->write("\n");

            return;
        }

        $this->chunk_rows($table, $columns, $prefix_sql);
        $this->write("\n");
    }

    /**
     * Stream rows via unbuffered mysqli query.
     *
     * @return bool True if data was written via this path.
     */
    private function stream_rows(string $table, array $columns, string $prefix_sql): bool
    {
        global $wpdb;

        $dbh = isset($wpdb->dbh) ? $wpdb->dbh : null;

        if (! $dbh instanceof \mysqli) {
            return false;
        }

        $select = 'SELECT ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns))
            . ' FROM `' . $table . '`';

        $result = @$dbh->query($select, MYSQLI_USE_RESULT);

        if ($result === false) {
            // Query failed — fall back to batching so one failed select does not drop the table.
            return false;
        }

        $batch = [];

        while (($row = mysqli_fetch_assoc($result)) !== null) {
            $batch[] = $this->row_values($row, $columns);

            if (count($batch) >= self::ROWS_PER_INSERT) {
                $this->write($prefix_sql . "\n" . implode(",\n", $batch) . ";\n");
                $this->row_count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->write($prefix_sql . "\n" . implode(",\n", $batch) . ";\n");
            $this->row_count += count($batch);
        }

        mysqli_free_result($result);

        return true;
    }

    /**
     * Fallback: batched reads by primary key.
     *
     * OFFSET is O(n²) on large tables; prefer keyset pagination when possible.
     *
     * @param string[] $columns
     */
    private function chunk_rows(string $table, array $columns, string $prefix_sql): void
    {
        $keys = $this->primary_key($table);
        $last = null;

        while (true) {
            $where = '';

            if ($keys !== [] && $last !== null) {
                $where = ' WHERE ' . $this->key_cursor_condition($keys, $last);
            }

            $select = 'SELECT ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns))
                . ' FROM `' . $table . '`' . $where;

            if ($keys !== []) {
                $select .= ' ORDER BY ' . implode(', ', array_map(static fn (string $k): string => '`' . $k . '`', $keys));
            }

            $select .= ' LIMIT ' . self::ROWS_PER_INSERT;

            $rows = $this->select_rows($select);

            if ($rows === []) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $batch[] = $this->row_values($row, $columns);
                $this->row_count++;
            }

            $this->write($prefix_sql . "\n" . implode(",\n", $batch) . ";\n");

            // Partial final batch means the table is fully read.
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
     * Composite primary key cursor condition (lexicographic).
     *
     * @param string[] $keys
     */
    private function key_cursor_condition(array $keys, array $last): string
    {
        $clauses = [];
        $acc = [];

        foreach ($keys as $index => $key) {
            $equals = [];

            for ($i = 0; $i < $index; $i++) {
                $equals[] = '`' . $keys[$i] . '` = ' . $this->value_literal($last[$i]);
            }

            $equals[] = '`' . $key . '` > ' . $this->value_literal($last[$index]);
            $acc[] = '(' . implode(' AND ', $equals) . ')';
        }

        $clauses = $acc;

        return implode(' OR ', $clauses);
    }

    /**
     * Run SELECT via $wpdb.
     *
     * @return array<int,array<string,mixed>>
     */
    private function select_rows(string $sql): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (! is_array($rows)) {
            throw new \RuntimeException('Error reading table data: ' . $wpdb->last_error);
        }

        return $rows;
    }

    /**
     * Primary key columns in index order.
     *
     * @return string[]
     */
    private function primary_key(string $table): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SHOW KEYS FROM `' . $table . "` WHERE Key_name = 'PRIMARY'", ARRAY_A);

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
     * Table columns in schema declaration order.
     *
     * @return string[]
     */
    private function columns(string $table): array
    {
        global $wpdb;

        $rows = $wpdb->get_results('SHOW COLUMNS FROM `' . $table . '`', ARRAY_A);

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
     * Build one row value list.
     *
     * Encoding lives in ValueEncoder and should be tested without a DB.
     *
     * @param array<string,mixed> $row
     * @param string[]            $columns
     */
    private function row_values(array $row, array $columns): string
    {
        return ValueEncoder::row_values($row, $columns);
    }

    /**
     * SQL literal for one cell value.
     *
     * @param mixed $value
     */
    private function value_literal($value): string
    {
        return ValueEncoder::literal($value);
    }

    /**
     * Closing section of the dump file.
     */
    private function write_footer(): void
    {
        $this->write(
            "\nSET SESSION sql_mode = @OLD_SQL_MODE;\n"
            . "SET FOREIGN_KEY_CHECKS = 1;\n"
            . '-- End of backup. Tables: ' . $this->table_count . ', rows: ' . $this->row_count . "\n",
        );
    }

    /**
     * Current dump file size in bytes.
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
     * Flush write buffer to disk.
     */
    public function flush(): void
    {
        if (is_resource($this->handle)) {
            fflush($this->handle);
        }
    }

    /**
     * Write a line to the dump file.
     *
     * @throws \RuntimeException If the file is not open or write fails.
     */
    private function write(string $chunk): void
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Backup file is not open for writing.');
        }

        $length = mb_strlen($chunk);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($this->handle, mb_substr($chunk, $offset));

            if ($written === false || $written === 0) {
                throw new \RuntimeException('Could not write to backup file (disk full or permission denied).');
            }

            $offset += $written;
        }
    }

    /**
     * Delete a partially written dump file.
     *
     * Used when a dump aborts; incomplete files must not be offered for restore.
     */
    public function discard(): void
    {
        $this->close();
    }
}
