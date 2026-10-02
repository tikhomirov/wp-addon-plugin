<?php

/**
 * Кодирование значений ячеек в SQL-литералы.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Преобразует значения строки БД в безопасные SQL-литералы.
 *
 * Отдельный класс без зависимости от WordPress и базы: правила кодирования
 * — самая важная часть дампа, и они должны проверяться напрямую.
 */
final class ValueEncoder
{
    /**
     * Кодирует одно значение ячейки.
     *
     * Важно: здесь намеренно не используется esc_sql(). Он заменяет "%"
     * служебным плейсхолдером-заглушкой (wpdb::add_placeholder_escape),
     * из-за чего содержимое базы портится. Собственное кодирование даёт
     * результат, не зависящий от настроек сервера.
     *
     * @param  mixed  $value  Значение из базы.
     */
    public static function literal($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        if ($value === '') {
            return "''";
        }

        // Битые байты, NUL и SUB нельзя писать текстовым литералом:
        // они ломают потоковый разбор и кодировку. Такие значения
        // сохраняем как hex — это работает для любых байтов.
        if (! self::is_text_safe($value)) {
            return '0x'.bin2hex($value);
        }

        return self::quote($value);
    }

    /**
     * Собирает список значений одной строки в виде "(v1,v2,...)".
     *
     * @param  array<string,mixed>  $row  Строка результата запроса.
     * @param  string[]  $columns  Колонки в порядке схемы.
     */
    public static function row_values(array $row, array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = self::literal($row[$column] ?? null);
        }

        return '('.implode(',', $parts).')';
    }

    /**
     * Экранирует строку как SQL-литерал.
     *
     * Парность с NO_BACKSLASH_ESCAPES=OFF, который дампер выставляет
     * явно через SET SESSION sql_mode: обратный слэш всегда экранирует
     * следующий символ.
     */
    public static function quote(string $value): string
    {
        return "'".addcslashes($value, "\0\n\r\t\\'\"\x1a")."'";
    }

    /**
     * Можно ли записать значение текстовым литералом.
     */
    public static function is_text_safe(string $value): bool
    {
        if (strpos($value, "\0") !== false || strpos($value, "\x1a") !== false) {
            return false;
        }

        // preg_match с модификатором /u возвращает false на невалидном UTF-8.
        return (bool) preg_match('//u', $value);
    }
}
