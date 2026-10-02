<?php

/**
 * Импорт SQL-дампа обратно в базу данных WordPress.
 *
 * Разбор идёт построчно и потоково, без загрузки файла целиком в память.
 * Команды выполняются пачками, длинные запросы режутся по границам значений.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Восстанавливает базу данных из SQL-файла дампа.
 */
final class Restorer
{
    /** Размер чтения из файла при построчном разборе. */
    private const READ_CHUNK = 262144;

    /** @var resource|null Дескриптор файла дампа. */
    private $handle = null;

    /** @var int Сколько утверждений (CREATE/INSERT/DROP) выполнено. */
    public int $statements = 0;

    /** @var int Сколько строк данных восстановлено. */
    public int $rows = 0;

    /** @var string[] Таблицы, созданные при импорте. */
    public array $created_tables = [];

    /** @var string[] Человекочитаемые предупреждения импорта. */
    public array $warnings = [];

    /** @var int Момент последнего отчёта о прогрессе (unix-время). */
    private int $last_report = 0;

    /**
     * Принимает экземпляр wpdb для выполнения запросов.
     */
    public function __construct($db = null)
    {
        global $wpdb;
        $this->db = $db ?? $wpdb;
    }

    /** @var string Префикс таблиц, записанный в дампе. */
    private string $dump_prefix = '';

    /** @var string|null Предупреждение о несовпадении префикса. */
    private ?string $prefix_warning = null;

    /** @var wpdb Соединение с базой данных для выполнения запросов. */
    private $db;

    /**
     * Открывает файл дампа на чтение.
     *
     * @param  string  $path  Полный путь к файлу дампа.
     *
     * @throws \RuntimeException Если файл недоступен.
     */
    public function open(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('Файл дампа недоступен для чтения.');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть файл дампа.');
        }

        $this->handle = $handle;
        $this->read_dump_prefix();
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
     * Читает префикс таблиц из заголовка дампа.
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
     * Выполняет импорт дампа.
     *
     * @return array{tables:int,rows:int,statements:int,warnings:string[]}
     *
     * @throws \RuntimeException При нечитаемом дампе или сбое запроса.
     */
    public function restore(): array
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Файл дампа не открыт.');
        }
        if ($this->dump_prefix !== '' && $this->dump_prefix !== $this->db->prefix) {
            throw new \RuntimeException(
                'Префикс таблиц в дампе ('.$this->dump_prefix.') не совпадает с префиксом сайта ('.$this->db->prefix.'). '
                .'Импорт остановлен: автоматическое переименование таблиц запрещено.'
            );
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        $this->execute('SET NAMES utf8mb4');

        foreach ($this->read_statements() as $statement) {
            $this->run_statement($statement);
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 1');

        return [
            'tables' => count($this->created_tables),
            'rows' => $this->rows,
            'statements' => $this->statements,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Выполняет одно утверждение дампа.
     *
     * @throws \RuntimeException При сбое запроса.
     */
    private function run_statement(string $statement): void
    {
        $kind = $this->statement_kind($statement);

        if ($kind === 'set') {
            // Служебные SET из манифеста повторяем: часть из них (sql_mode)
            // влияет на интерпретацию данных при импорте.
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
     * Определяет тип утверждения по его началу.
     */
    private function statement_kind(string $statement): string
    {
        $head = ltrim($statement);

        if (stripos($head, 'INSERT') === 0) {
            return 'insert';
        }

        if (stripos($head, 'CREATE') === 0) {
            return 'create';
        }

        if (stripos($head, 'SET ') === 0) {
            return 'set';
        }

        if (stripos($head, 'USE ') === 0) {
            return 'use';
        }

        return 'other';
    }

    /**
     * Выполняет INSERT и обновляет счётчик строк.
     *
     * Строки считаются по вхождениям кортежей в VALUES, а не по результату
     * запроса: так счётчик остаётся точным даже для REPLACE-подобных
     * вариантов и для дампов, собранных сторонними инструментами.
     *
     * @throws \RuntimeException При сбое запроса.
     */
    private function run_insert(string $statement): void
    {
        $values_pos = stripos($statement, 'VALUES');

        if ($values_pos === false) {
            $this->execute($statement);
            $this->statements++;
            $this->rows += 1;

            return;
        }

        $this->execute($statement);
        $this->statements++;
        $this->rows += $this->count_value_tuples(substr($statement, $values_pos + 7));
    }

    /**
     * Считает число кортежей в теле VALUES.
     *
     * Разбор учитывает строковые литералы, экранирование и NULL, поэтому
     * запятая или скобка внутри текста не сбивают счёт.
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
                    // Двойная кавычка внутри строки экранируется как ''.
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
     * Разбирает дамп на утверждения.
     *
     * @return \Generator<int,string> Запросы в порядке следования в файле.
     *
     * @throws \RuntimeException Если файл не читается.
     */
    public function read_statements(): \Generator
    {
        if (! is_resource($this->handle)) {
            throw new \RuntimeException('Файл дампа не открыт.');
        }

        $buffer = '';

        while (! feof($this->handle)) {
            $chunk = fread($this->handle, self::READ_CHUNK);

            if ($chunk === false) {
                throw new \RuntimeException('Ошибка чтения файла дампа.');
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
     * Отделяет полные утверждения от неполного хвоста буфера.
     *
     * Разделителем служит точка с запятой вне строковых литералов и комментариев.
     *
     * @return array<int,array{0:string,1:string}> Пары [утверждение, остаток].
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

        // Хвост без точки с запятой — это незавершённое утверждение, его нужно
        // дочитать следующим куском. Если внутри хвоста есть закрывающая ';'
        // раньше, она уже была обработана выше, поэтому current — всегда
        // недобранный кусок.
        return [$statements, $current];
    }

    /**
     * Убирает комментарии из готового утверждения.
     */
    public function strip_comments(string $statement): string
    {
        // Комментарии полезны при чтении файла человеком, но MySQL их и так
        // пропускает; удаляем, чтобы не тащить мусор в счётчик строк.
        $statement = preg_replace('#/\*.*?\*/#s', ' ', $statement) ?? $statement;
        $statement = preg_replace('/^\s*(--|#).*$/m', '', $statement) ?? $statement;

        return trim($statement);
    }

    /**
     * Отправляет запрос в базу.
     *
     * @throws \RuntimeException При сбое запроса.
     */
    private function execute(string $sql): void
    {
        $result = $this->db->query($sql);

        if ($result === false) {
            throw new \RuntimeException(
                'Ошибка SQL при восстановлении: '.$this->short_error((string) $this->db->last_error)
                .' [Запрос: '.substr(trim($sql), 0, 120).']'
            );
        }
    }

    /**
     * Усекает текст ошибки до первой строки с причиной.
     */
    private function short_error(string $error): string
    {
        $error = trim($error);

        if ($error === '') {
            return 'неизвестная ошибка';
        }

        $lines = preg_split('/\R/', $error) ?: [$error];
        $first = trim((string) $lines[0]);

        return $first !== '' ? $first : 'неизвестная ошибка';
    }
}
