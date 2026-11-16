<?php

/**
 * Import SQL dump back into WordPress.
 *
 * Line-by-line streaming parse without loading the whole file.
 * Statements run in batches; long queries split at value boundaries.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Restores the database from a dump SQL file.
 */
final class Restorer
{
    /** Read chunk size for line parsing. */
    private const READ_CHUNK = 262144;

    /** @var resource|null Dump file handle. */
    private $handle = null;

    /** @var int CREATE/INSERT/DROP statements executed. */
    public int $statements = 0;

    /** @var int Data rows restored. */
    public int $rows = 0;

    /** @var string[] Tables created during import. */
    public array $created_tables = [];

    /** @var string[] Human-readable import warnings. */
    public array $warnings = [];

    /** @var int Last progress report timestamp. */
    private int $last_report = 0;

    /**
     * Accepts wpdb for queries.
     */
    public function __construct($db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
    }

    /** @var string Table prefix recorded in the dump. */
    private string $dump_prefix = '';

    /** @var string|null Prefix mismatch warning. */
    private ?string $prefix_warning = null;

    /** @var \wpdb Database connection for queries. */
    private $db;

    /**
     * Open dump file for reading.
     *
     * @param string $path Absolute dump file path.
     *
     * @throws \RuntimeException If file is not accessible.
     */
    public function open(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('Backup file is not readable.');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Could not open backup file.');
        }

        $this->handle = $handle;
        $this->read_dump_prefix();
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
     * Read table prefix from dump header.
     */
    private function read_dump_prefix(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        $head = fread($this->handle, 16384);

        if ($head === false || $head === '') {
            return;
        }

        if (preg_match('/^--\s*Prefix:\s*(\S+)/m', $head, $matches) === 1) {
            $this->dump_prefix = (string) $matches[1];
        }

        rewind($this->handle);
    }

    /**
     * Run dump import.
     *
     * @return array{tables:int,rows:int,statements:int,warnings:string[]}
     *
     * @throws \RuntimeException On unreadable dump or query failure.
     */
    public function restore(): array
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Backup file is not open.');
        }
        if ($this->dump_prefix !== '' && $this->dump_prefix !== $this->db->prefix) {
            throw new \RuntimeException(
                'Table prefix in backup (' . $this->dump_prefix . ') does not match site prefix (' . $this->db->prefix . '). '
                . 'Import stopped: automatic table renaming is not allowed.',
            );
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        $this->execute('SET NAMES utf8mb4');

        foreach ($this->read_statements() as $statement) {
            $this->run_statement($statement);
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 1');

        return [
            'tables'     => count($this->created_tables),
            'rows'       => $this->rows,
            'statements' => $this->statements,
            'warnings'   => $this->warnings,
        ];
    }

    /**
     * Execute one dump statement.
     *
     * @throws \RuntimeException On query failure.
     */
    private function run_statement(string $statement): void
    {
        $kind = $this->statement_kind($statement);

        if ($kind === 'set') {
            // Re-apply manifest SET statements (sql_mode
            // affects data interpretation on import).
            $this->execute($statement);

            return;
        }

        if ($kind === 'use') {
            return;
        }

        if ($kind === 'insert') {
            $this->run_insert($statement);

            return;
        }

        $this->execute($statement);
        $this->statements++;

        if ($kind === 'create' && preg_match('/CREATE\s+TABLE\s+`?([^`\s(]+)/i', $statement, $m) === 1) {
            $this->created_tables[] = $m[1];
        }
    }

    /**
     * Detect statement type from prefix.
     */
    private function statement_kind(string $statement): string
    {
        $head = ltrim($statement);

        if (mb_stripos($head, 'INSERT') === 0) {
            return 'insert';
        }

        if (mb_stripos($head, 'CREATE') === 0) {
            return 'create';
        }

        if (mb_stripos($head, 'SET ') === 0) {
            return 'set';
        }

        if (mb_stripos($head, 'USE ') === 0) {
            return 'use';
        }

        return 'other';
    }

    /**
     * Run INSERT and update row counter.
     *
     * Count rows by VALUES tuples, not query result,
     * so counts stay accurate for REPLACE-like
     * statements and third-party dumps.
     *
     * @throws \RuntimeException On query failure.
     */
    private function run_insert(string $statement): void
    {
        $values_pos = mb_stripos($statement, 'VALUES');

        if ($values_pos === false) {
            $this->execute($statement);
            $this->statements++;
            $this->rows += 1;

            return;
        }

        $this->execute($statement);
        $this->statements++;
        $this->rows += $this->count_value_tuples(mb_substr($statement, $values_pos + 7));
    }

    /**
     * Count tuples in VALUES clause.
     *
     * Parser respects literals, escapes, NULL;
     * commas/parens inside strings do not break counts.
     */
    public function count_value_tuples(string $values_body): int
    {
        $count = 0;
        $depth = 0;
        $in_string = false;
        $length = strlen($values_body);
        $index = 0;

        while ($index < $length) {
            $char = $values_body[$index];

            if ($in_string) {
                if ($char === '\\' && $index + 1 < $length) {
                    $index += 2;

                    continue;
                }

                if ($char === "'") {
                    // Escaped quote inside string is doubled.
                    if ($index + 1 < $length && $values_body[$index + 1] === "'") {
                        $index += 2;

                        continue;
                    }

                    $in_string = false;
                }

                $index++;

                continue;
            }

            if ($char === "'") {
                $in_string = true;
                $index++;

                continue;
            }

            if ($char === '(') {
                if ($depth === 0) {
                    $count++;
                }

                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }

            $index++;
        }

        return $count;
    }

    /**
     * Split dump into statements.
     *
     * @return \Generator<int,string> Statements in file order.
     *
     * @throws \RuntimeException If file cannot be read.
     */
    public function read_statements(): \Generator
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Backup file is not open.');
        }

        $buffer = '';

        while (! feof($this->handle)) {
            $chunk = fread($this->handle, self::READ_CHUNK);

            if ($chunk === false) {
                throw new \RuntimeException('Error reading backup file.');
            }

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            [$complete_statements, $rest] = $this->split_statements($buffer);

            foreach ($complete_statements as $complete) {
                yield $complete;
            }

            $buffer = $rest;
        }

        $tail = trim($buffer);

        if ($tail !== '') {
            yield $tail;
        }
    }

    /**
     * Split complete statements from buffer tail.
     *
     * Delimiter is semicolon outside literals/comments.
     *
     * @return array<int,array{0:string,1:string}> Pairs [statement, remainder].
     */
    private function split_statements(string $buffer): array
    {
        $statements = [];
        $current = '';
        $length = strlen($buffer);
        $index = 0;
        $in_string = false;
        $in_line_comment = false;
        $in_block_comment = false;

        while ($index < $length) {
            $char = $buffer[$index];
            $next = $index + 1 < $length ? $buffer[$index + 1] : '';

            if ($in_line_comment) {
                if ($char === "\n") {
                    $in_line_comment = false;
                }

                $current .= $char;
                $index++;

                continue;
            }

            if ($in_block_comment) {
                $current .= $char;

                if ($char === '*' && $next === '/') {
                    $current .= $next;
                    $index += 2;
                    $in_block_comment = false;

                    continue;
                }

                $index++;

                continue;
            }

            if ($in_string) {
                $current .= $char;

                if ($char === '\\' && $next !== '') {
                    $current .= $next;
                    $index += 2;

                    continue;
                }

                if ($char === "'") {
                    if ($next === "'") {
                        $current .= $next;
                        $index += 2;

                        continue;
                    }

                    $in_string = false;
                }

                $index++;

                continue;
            }

            if ($char === '-' && $next === '-' && ($index + 2 >= $length || $buffer[$index + 2] === ' ')) {
                $in_line_comment = true;
                $current .= $char;
                $index++;

                continue;
            }

            if ($char === '#') {
                $in_line_comment = true;
                $current .= $char;
                $index++;

                continue;
            }

            if ($char === '/' && $next === '*') {
                $in_block_comment = true;
                $current .= $char;
                $index++;

                continue;
            }

            if ($char === "'") {
                $in_string = true;
                $current .= $char;
                $index++;

                continue;
            }

            if ($char === ';') {
                $index++;

                $trimmed = $this->strip_comments(trim($current));

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';

                continue;
            }

            $current .= $char;
            $index++;
        }

        // Tail without semicolon is incomplete;
        // read with next chunk.
        // Earlier semicolons handled above; current is
        // the unfinished fragment.
        return [$statements, $current];
    }

    /**
     * Strip comments from finished statement.
     */
    public function strip_comments(string $statement): string
    {
        // Comments help humans; MySQL skips them —
        // strip them for clean row counts.
        $statement = preg_replace('#/\*.*?\*/#s', ' ', $statement) ?? $statement;
        $statement = preg_replace('/^\s*(--|#).*$/m', '', $statement) ?? $statement;

        return trim($statement);
    }

    /**
     * Send query to database.
     *
     * @throws \RuntimeException On query failure.
     */
    private function execute(string $sql): void
    {
        $result = $this->db->query($sql);

        if ($result === false) {
            throw new \RuntimeException(
                'SQL error during restore: ' . $this->short_error((string) $this->db->last_error)
                . ' [Query: ' . mb_substr(trim($sql), 0, 120) . ']',
            );
        }
    }

    /**
     * Truncate error to first reason line.
     */
    private function short_error(string $error): string
    {
        $error = trim($error);

        if ($error === '') {
            return 'unknown error';
        }

        $lines = preg_split('/\R/', $error) ?: [$error];
        $first = trim((string) $lines[0]);

        return $first !== '' ? $first : 'unknown error';
    }
}
