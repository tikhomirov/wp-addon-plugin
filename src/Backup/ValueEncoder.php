<?php

/**
 * Encode cell values as SQL literals.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Turns database row values into safe SQL literals.
 *
 * Standalone from WordPress and the DB layer so encoding rules are tested directly.
 */
final class ValueEncoder
{
    /**
     * Encode a single cell value.
     *
     * esc_sql() is intentionally not used: it replaces "%" with wpdb's placeholder
     * escape and corrupts dump contents. Custom encoding is server-setting agnostic.
     *
     * @param  mixed  $value  Value from the database.
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

        // Broken bytes, NUL, and SUB cannot be written as text literals; use hex instead.
        if (! self::is_text_safe($value)) {
            return '0x'.bin2hex($value);
        }

        return self::quote($value);
    }

    /**
     * Build one row as "(v1,v2,...)".
     *
     * @param  array<string,mixed>  $row  Query result row.
     * @param  string[]  $columns  Columns in schema order.
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
     * Escape a string as an SQL literal.
     *
     * Matches NO_BACKSLASH_ESCAPES=OFF set by the dumper via SET SESSION sql_mode.
     */
    public static function quote(string $value): string
    {
        return "'".addcslashes($value, "\0\n\r\t\\'\"\x1a")."'";
    }

    /**
     * Whether the value can be written as a text literal.
     */
    public static function is_text_safe(string $value): bool
    {
        if (strpos($value, "\0") !== false || strpos($value, "\x1a") !== false) {
            return false;
        }

        // preg_match with /u returns false on invalid UTF-8.
        return (bool) preg_match('//u', $value);
    }
}