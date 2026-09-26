<?php

namespace WpAddon\Services;

/**
 * Storage and reporting for blocked comment attempts.
 *
 * Blocked comments are never inserted (WordPress short-circuits the insert on
 * a WP_Error), so this table is the only trace that an attempt happened.
 * Rows are rotated out monthly.
 */
class CommentAntispamLogService
{
    public const TABLE = 'wp_addon_antispam_log';

    public const DB_VERSION_OPTION = 'wp_addon_antispam_db_version';

    public const DB_VERSION = '1.0';

    public const CRON_HOOK = 'wp_addon_antispam_rotate';

    /**
     * Months of history kept for the admin panel.
     */
    public const RETENTION_MONTHS = 2;

    /**
     * WP-Cron is unreliable on low traffic sites, so rotation also runs
     * opportunistically on roughly every 50th logged attempt.
     */
    private const ROTATION_SAMPLE_RATE = 50;

    private static int $insertCounter = 0;

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix.self::TABLE;
    }

    public static function maybeInstall(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        self::install();
    }

    public static function install(): void
    {
        global $wpdb;

        $table = self::tableName();
        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_gmt datetime NOT NULL,
            created_at datetime NOT NULL,
            reason varchar(100) NOT NULL DEFAULT '',
            score smallint(5) unsigned NOT NULL DEFAULT 0,
            threshold smallint(5) unsigned NOT NULL DEFAULT 0,
            ip varchar(45) NOT NULL DEFAULT '',
            author varchar(100) NOT NULL DEFAULT '',
            email varchar(100) NOT NULL DEFAULT '',
            content text,
            user_agent varchar(255) NOT NULL DEFAULT '',
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            comment_type varchar(20) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created_gmt (created_gmt),
            KEY reason (reason),
            KEY post_id (post_id)
        ) {$charsetCollate};";

        if (! function_exists('dbDelta')) {
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
        }

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;

        $table = self::tableName();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
        delete_option(self::DB_VERSION_OPTION);
    }

    public static function tableExists(): bool
    {
        global $wpdb;

        $table = self::tableName();
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $found === $table;
    }

    /**
     * @param  array{reason?: string, score?: int, threshold?: int, ip?: string, author?: string, email?: string, content?: string, user_agent?: string, post_id?: int, comment_type?: string}  $data
     */
    public static function log(array $data): void
    {
        global $wpdb;

        if (! self::tableExists()) {
            return;
        }

        $now = time();
        $row = [
            'created_gmt' => gmdate('Y-m-d H:i:s', $now),
            'created_at' => date('Y-m-d H:i:s', $now),
            'reason' => substr((string) ($data['reason'] ?? ''), 0, 100),
            'score' => (int) ($data['score'] ?? 0),
            'threshold' => (int) ($data['threshold'] ?? 0),
            'ip' => substr((string) ($data['ip'] ?? ''), 0, 45),
            'author' => substr((string) ($data['author'] ?? ''), 0, 100),
            'email' => substr((string) ($data['email'] ?? ''), 0, 100),
            'content' => substr((string) ($data['content'] ?? ''), 0, 2000),
            'user_agent' => substr((string) ($data['user_agent'] ?? ''), 0, 255),
            'post_id' => (int) ($data['post_id'] ?? 0),
            'comment_type' => substr((string) ($data['comment_type'] ?? ''), 0, 20),
        ];

        $formats = [
            '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
        ];

        $wpdb->insert(self::tableName(), $row, $formats);

        self::$insertCounter++;

        if (self::$insertCounter % self::ROTATION_SAMPLE_RATE === 0) {
            self::rotate();
        }
    }

    /**
     * Deletes rows older than the retention window. Safe to call repeatedly.
     */
    public static function rotate(): int
    {
        global $wpdb;

        if (! self::tableExists()) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', (int) strtotime('-'.self::RETENTION_MONTHS.' months', time()));

        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM '.self::tableName().' WHERE created_gmt < %s',
            $cutoff
        ));
    }

    /**
     * @return array{total: int, by_day: array<string, int>, by_reason: array<string, int>, top_ips: array<int, array{ip: string, hits: int}>, last: array<int, array<string, mixed>>}
     */
    public static function getReport(int $days = 30): array
    {
        global $wpdb;

        $report = [
            'total' => 0,
            'by_day' => [],
            'by_reason' => [],
            'top_ips' => [],
            'last' => [],
        ];

        if (! self::tableExists()) {
            return $report;
        }

        $table = self::tableName();
        $since = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $report['total'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_gmt >= %s",
            $since
        ));

        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_gmt) AS day, COUNT(*) AS hits FROM {$table}
             WHERE created_gmt >= %s GROUP BY DATE(created_gmt)",
            $since
        ), ARRAY_A);

        foreach ((array) $daily as $row) {
            $report['by_day'][(string) $row['day']] = (int) $row['hits'];
        }

        $reasons = $wpdb->get_results($wpdb->prepare(
            "SELECT reason, COUNT(*) AS hits FROM {$table}
             WHERE created_gmt >= %s GROUP BY reason ORDER BY hits DESC",
            $since
        ), ARRAY_A);

        foreach ((array) $reasons as $row) {
            $report['by_reason'][(string) $row['reason']] = (int) $row['hits'];
        }

        $ips = $wpdb->get_results($wpdb->prepare(
            "SELECT ip, COUNT(*) AS hits FROM {$table}
             WHERE created_gmt >= %s AND ip <> '' GROUP BY ip ORDER BY hits DESC LIMIT 10",
            $since
        ), ARRAY_A);

        foreach ((array) $ips as $row) {
            $report['top_ips'][] = [
                'ip' => (string) $row['ip'],
                'hits' => (int) $row['hits'],
            ];
        }

        $last = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at, reason, score, threshold, ip, author, email, content, post_id
             FROM {$table} WHERE created_gmt >= %s ORDER BY id DESC LIMIT 20",
            $since
        ), ARRAY_A);

        $report['last'] = is_array($last) ? $last : [];

        return $report;
    }
}
