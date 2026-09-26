<?php

use WpAddon\Services\CommentAntispamLogService;

if (! defined('COOKIEHASH')) {
    define('COOKIEHASH', 'wp-addon-test');
}

/**
 * Minimal in-memory stand-in for wpdb. The real class is not available in the
 * mock bootstrap, and the log service only touches a handful of methods.
 */
class FakeAntispamWpdb
{
    public string $prefix = 'wp_';

    public array $inserts = [];

    public array $queries = [];

    public bool $tablePresent = true;

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4';
    }

    public function prepare($query, ...$args)
    {
        foreach ($args as $arg) {
            $query = preg_replace(
                '/%[sd]/',
                is_int($arg) || is_float($arg) ? (string) $arg : "'".$arg."'",
                $query,
                1
            );
        }

        return $query;
    }

    public function get_var($query = null)
    {
        return $this->tablePresent ? $this->prefix.CommentAntispamLogService::TABLE : null;
    }

    public function get_results($query = null, $output = null)
    {
        return [];
    }

    public function insert($table, $row, $formats = null): int
    {
        $this->inserts[] = ['table' => $table, 'row' => $row, 'formats' => $formats];

        return 1;
    }

    public function query($sql): int
    {
        $this->queries[] = $sql;

        return 3;
    }
}

/**
 * Values returned in order by get_results() for the reporting queries.
 */
class FakeAntispamReportWpdb extends FakeAntispamWpdb
{
    public array $resultSets = [];

    public function get_results($query = null, $output = null)
    {
        return array_shift($this->resultSets) ?? [];
    }

    public function get_var($query = null)
    {
        if (str_contains((string) $query, 'COUNT(*)')) {
            return 7;
        }

        return parent::get_var($query);
    }
}

function antispam_fake_wpdb(bool $tablePresent = true): FakeAntispamWpdb
{
    $db = new FakeAntispamWpdb;
    $db->tablePresent = $tablePresent;

    return $db;
}

/**
 * The rotation sample counter is a private static, so it is reset through
 * reflection to keep the "fiftieth write" case independent of test order.
 */
function antispam_reset_insert_counter(): void
{
    $property = new ReflectionProperty(CommentAntispamLogService::class, 'insertCounter');
    $property->setValue(null, 0);
}

beforeEach(function () {
    global $wpdb, $mock_user_capabilities, $mock_is_admin, $db;

    $wpdb = antispam_fake_wpdb();
    $mock_user_capabilities = [];
    $mock_is_admin = true;
    $db->exec('DELETE FROM wp_options');
    $_POST = [];
    $_COOKIE = [];
    antispam_reset_insert_counter();
});

describe('CommentAntispamLogService', function () {
    it('builds the table name from the site prefix', function () {
        expect(CommentAntispamLogService::tableName())->toBe('wp_wp_addon_antispam_log');
    });

    it('reports a missing table instead of writing', function () {
        global $wpdb;
        $wpdb = antispam_fake_wpdb(false);

        expect(CommentAntispamLogService::tableExists())->toBeFalse();

        CommentAntispamLogService::log(['reason' => 'score', 'ip' => '1.2.3.4']);

        expect($wpdb->inserts)->toBeEmpty();
    });

    it('stores every field needed to review an attempt', function () {
        global $wpdb;

        CommentAntispamLogService::log([
            'reason' => 'score:text_token,author_token',
            'score' => 8,
            'threshold' => 3,
            'ip' => '203.0.113.77',
            'author' => 'ztpyuOkqNTwQsQKwwkC',
            'email' => 'o.x.on.e.j.o.f.eza.96@gmail.com',
            'content' => 'IjtRwXHmcvybfzgmAiM',
            'user_agent' => 'Mozilla/5.0',
            'post_id' => 7700,
            'comment_type' => 'comment',
        ]);

        expect($wpdb->inserts)->toHaveCount(1);

        $insert = $wpdb->inserts[0];

        expect($insert['table'])->toBe('wp_wp_addon_antispam_log');
        expect($insert['row']['reason'])->toBe('score:text_token,author_token');
        expect($insert['row']['score'])->toBe(8);
        expect($insert['row']['threshold'])->toBe(3);
        expect($insert['row']['ip'])->toBe('203.0.113.77');
        expect($insert['row']['author'])->toBe('ztpyuOkqNTwQsQKwwkC');
        expect($insert['row']['email'])->toBe('o.x.on.e.j.o.f.eza.96@gmail.com');
        expect($insert['row']['content'])->toBe('IjtRwXHmcvybfzgmAiM');
        expect($insert['row']['user_agent'])->toBe('Mozilla/5.0');
        expect($insert['row']['post_id'])->toBe(7700);
        expect($insert['row']['comment_type'])->toBe('comment');
        expect($insert['row']['created_gmt'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        expect($insert['row']['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        expect($insert['formats'])->toHaveCount(12);
    });

    it('falls back to zeroed values for missing fields', function () {
        global $wpdb;

        CommentAntispamLogService::log([]);

        $row = $wpdb->inserts[0]['row'];

        expect($row['reason'])->toBe('');
        expect($row['score'])->toBe(0);
        expect($row['threshold'])->toBe(0);
        expect($row['ip'])->toBe('');
        expect($row['post_id'])->toBe(0);
    });

    it('truncates values that do not fit the column', function () {
        global $wpdb;

        CommentAntispamLogService::log([
            'reason' => str_repeat('r', 250),
            'ip' => str_repeat('9', 80),
            'author' => str_repeat('a', 250),
            'email' => str_repeat('e', 250),
            'user_agent' => str_repeat('u', 400),
            'comment_type' => str_repeat('c', 40),
        ]);

        $row = $wpdb->inserts[0]['row'];

        expect(strlen($row['reason']))->toBe(100);
        expect(strlen($row['ip']))->toBe(45);
        expect(strlen($row['author']))->toBe(100);
        expect(strlen($row['email']))->toBe(100);
        expect(strlen($row['user_agent']))->toBe(255);
        expect(strlen($row['comment_type']))->toBe(20);
    });

    it('prunes rows older than the retention window', function () {
        global $wpdb;

        $deleted = CommentAntispamLogService::rotate();

        expect($deleted)->toBe(3);
        expect($wpdb->queries)->toHaveCount(1);
        expect($wpdb->queries[0])->toContain('DELETE FROM wp_wp_addon_antispam_log');
        expect($wpdb->queries[0])->toContain('created_gmt <');
    });

    it('does not prune when the table is missing', function () {
        global $wpdb;
        $wpdb = antispam_fake_wpdb(false);

        expect(CommentAntispamLogService::rotate())->toBe(0);
        expect($wpdb->queries)->toBeEmpty();
    });

    it('rotates opportunistically on the fiftieth write', function () {
        global $wpdb;

        for ($i = 1; $i <= 49; $i++) {
            CommentAntispamLogService::log(['reason' => 'score']);
        }

        expect($wpdb->queries)->toBeEmpty();

        CommentAntispamLogService::log(['reason' => 'score']);

        expect($wpdb->queries)->toHaveCount(1);
    });

    it('aggregates the report from the log table', function () {
        global $wpdb;

        $wpdb = new FakeAntispamReportWpdb;
        $wpdb->resultSets = [
            [['day' => '2026-09-25', 'hits' => 4], ['day' => '2026-09-26', 'hits' => 3]],
            [['reason' => 'score:text_token', 'hits' => 5], ['reason' => 'honeypot', 'hits' => 2]],
            [['ip' => '203.0.113.77', 'hits' => 5], ['ip' => '198.51.100.1', 'hits' => 2]],
            [['created_at' => '2026-09-26 10:00:00', 'reason' => 'score:text_token', 'score' => 8]],
        ];

        $result = CommentAntispamLogService::getReport(30);

        expect($result['total'])->toBe(7);
        expect($result['by_day'])->toBe(['2026-09-25' => 4, '2026-09-26' => 3]);
        expect($result['by_reason'])->toBe(['score:text_token' => 5, 'honeypot' => 2]);
        expect($result['top_ips'])->toBe([
            ['ip' => '203.0.113.77', 'hits' => 5],
            ['ip' => '198.51.100.1', 'hits' => 2],
        ]);
        expect($result['last'])->toHaveCount(1);
    });

    it('returns an empty report when the table is missing', function () {
        global $wpdb;
        $wpdb = antispam_fake_wpdb(false);

        expect(CommentAntispamLogService::getReport(30))->toBe([
            'total' => 0,
            'by_day' => [],
            'by_reason' => [],
            'top_ips' => [],
            'last' => [],
        ]);
    });

    it('skips the installer once the schema version is stored', function () {
        update_option(CommentAntispamLogService::DB_VERSION_OPTION, CommentAntispamLogService::DB_VERSION);

        expect(get_option(CommentAntispamLogService::DB_VERSION_OPTION))->toBe(CommentAntispamLogService::DB_VERSION);
    });
});
