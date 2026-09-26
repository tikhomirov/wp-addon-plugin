<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\CommentAntispamBacklinkService;
use WpAddon\Services\CommentAntispamLogService;
use WpAddon\Services\CommentAntispamRateLimitService;
use WpAddon\Services\CommentAntispamScoringService;
use WpAddon\Traits\HookTrait;

/**
 * Comment spam protection.
 *
 * Replaces the Kama SpamBlock plugin: honeypot + time trap instead of a
 * reversible JavaScript nonce, content scoring instead of a bare "is the field
 * filled" check, and the same pingback/trackback backlink verification.
 *
 * A blocked comment is never stored. The `pre_comment_approved` filter returns
 * a WP_Error, so WordPress short-circuits the insert: nothing lands in the
 * database, no notification email is sent, and the page cache is not purged.
 */
class CommentAntispam implements ModuleInterface
{
    use HookTrait;

    public const HONEYPOT_FIELD = 'antispam_website';

    public const TIMESTAMP_FIELD = 'antispam_ts';

    public const REASON_HONEYPOT = 'honeypot';

    public const REASON_TOO_FAST = 'too_fast';

    public const REASON_RATE_LIMIT = 'rate_limit';

    public const REASON_NO_BACKLINK = 'no_backlink';

    public const REASON_SCORE = 'score';

    private const KAMA_PLUGIN = 'kama-spamblock/kama-spamblock.php';

    private const OPTIONS_PREFIX = 'wp-addon';

    private const BLOCKED_MESSAGE = 'Your comment looks like spam and was rejected. Please review it and try again.';

    private int $threshold = 3;

    private ?CommentAntispamBacklinkService $backlinks = null;

    private ?CommentAntispamRateLimitService $rateLimiter = null;

    public function init(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->threshold = $this->getNumberSetting('antispam_threshold', 3);

        CommentAntispamLogService::maybeInstall();

        // Must run before the event is scheduled: wp_schedule_event() validates
        // the interval name against the schedules registered by this filter.
        $this->addFilter('cron_schedules', [$this, 'addMonthlySchedule']);
        $this->scheduleRotation();

        $this->addFilter('pre_comment_approved', [$this, 'filterPreCommentApproved'], 1, 2);
        $this->addFilter('rest_pre_insert_comment', [$this, 'filterRestPreInsertComment'], 10, 1);

        add_action(CommentAntispamLogService::CRON_HOOK, [$this, 'rotateLog']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('comment_form_after_fields', [$this, 'renderHoneypot']);
        add_action('admin_notices', [$this, 'renderKamaNotice']);
    }

    /**
     * Main gate for the classic comment form, pingbacks and trackbacks.
     *
     * @param  int|string|WP_Error  $approved
     * @param  array<string, mixed>  $commentdata
     * @return int|string|WP_Error
     */
    public function filterPreCommentApproved($approved, $commentdata)
    {
        if (is_wp_error($approved) || $this->trustsModerators()) {
            return $approved;
        }

        // The disallowed keys list already spoke for this comment.
        if (is_string($approved) && in_array($approved, ['spam', 'trash'], true)) {
            return $approved;
        }

        $error = $this->screen(is_array($commentdata) ? $commentdata : []);

        return $error ?? $approved;
    }

    /**
     * The REST comments controller inserts with wp_insert_comment() and never
     * reaches pre_comment_approved, so it needs its own entry point.
     *
     * @param  array<string, mixed>|WP_Error  $preparedComment
     * @return array<string, mixed>|WP_Error
     */
    public function filterRestPreInsertComment($preparedComment)
    {
        if (is_wp_error($preparedComment) || ! is_array($preparedComment)) {
            return $preparedComment;
        }

        $error = $this->screen($preparedComment);

        if ($error === null) {
            return $preparedComment;
        }

        // wp-comments-post.php casts the error data straight into the HTTP
        // status, while REST only reads a ['status' => int] array. Rebuild the
        // error so both entry points answer 403.
        return new WP_Error(
            $error->get_error_code(),
            $error->get_error_message(),
            ['status' => (int) $error->get_error_data()]
        );
    }

    /**
     * @param  array<string, mixed>  $commentdata
     */
    private function screen(array $commentdata): ?WP_Error
    {
        $type = (string) ($commentdata['comment_type'] ?? 'comment');

        if ($this->checksBacklinks() && in_array($type, ['pingback', 'trackback'], true)) {
            $sourceUrl = $commentdata['comment_author_url'] ?? '';

            if (! $this->backlinks()->isPingbackSourceValid(is_string($sourceUrl) ? $sourceUrl : '')) {
                return $this->reject(self::REASON_NO_BACKLINK, 0, $commentdata);
            }

            return null;
        }

        if (! in_array($type, ['', 'comment'], true)) {
            return null;
        }

        $honeypot = $this->getSwitcher('antispam_honeypot', true);
        if ($honeypot && trim($this->getPostedValue(self::HONEYPOT_FIELD)) !== '') {
            return $this->reject(self::REASON_HONEYPOT, 0, $commentdata);
        }

        // The page comes from the page cache, so the timestamp is written by
        // the browser. A missing field means no JavaScript, not a bot.
        $minSeconds = $this->getNumberSetting('antispam_min_seconds', 3);
        $timestamp = (int) $this->getPostedValue(self::TIMESTAMP_FIELD);
        if ($minSeconds > 0 && $timestamp > 0 && (time() - $timestamp) < $minSeconds) {
            return $this->reject(self::REASON_TOO_FAST, 0, $commentdata);
        }

        $ip = (string) ($commentdata['comment_author_IP'] ?? '');

        if ($this->rateLimiter()->isExceeded($ip)) {
            return $this->reject(self::REASON_RATE_LIMIT, 0, $commentdata);
        }

        $verdict = CommentAntispamScoringService::score(
            (string) ($commentdata['comment_content'] ?? ''),
            (string) ($commentdata['comment_author'] ?? ''),
            (string) ($commentdata['comment_author_email'] ?? ''),
            $this->isFirstVisit()
        );

        if ($verdict['score'] < $this->threshold) {
            $this->rateLimiter()->register($ip);

            return null;
        }

        $reason = self::REASON_SCORE.':'.implode(',', $verdict['reasons']);

        return $this->reject($reason, $verdict['score'], $commentdata);
    }

    /**
     * @param  array<string, mixed>  $commentdata
     */
    private function reject(string $reason, int $score, array $commentdata): WP_Error
    {
        $ip = (string) ($commentdata['comment_author_IP'] ?? '');

        $this->rateLimiter()->register($ip);

        CommentAntispamLogService::log([
            'reason' => $reason,
            'score' => $score,
            'threshold' => $this->threshold,
            'ip' => $ip,
            'author' => (string) ($commentdata['comment_author'] ?? ''),
            'email' => (string) ($commentdata['comment_author_email'] ?? ''),
            'content' => (string) ($commentdata['comment_content'] ?? ''),
            'user_agent' => (string) ($commentdata['comment_agent'] ?? ''),
            'post_id' => (int) ($commentdata['comment_post_ID'] ?? 0),
            'comment_type' => (string) ($commentdata['comment_type'] ?? ''),
        ]);

        return new WP_Error(
            'comment_antispam_rejected',
            (string) $this->getSetting('antispam_blocked_message', self::BLOCKED_MESSAGE),
            403
        );
    }

    public function enqueueAssets(): void
    {
        if (! is_singular() || ! comments_open()) {
            return;
        }

        wp_enqueue_style(
            'wp-addon-comment-antispam',
            RW_PLUGIN_URL.'assets/css/comment-antispam.css',
            [],
            WP_ADDON_VERSION
        );

        wp_enqueue_script(
            'wp-addon-comment-antispam',
            RW_PLUGIN_URL.'assets/js/comment-antispam.js',
            [],
            WP_ADDON_VERSION,
            true
        );
    }

    public function renderHoneypot(): void
    {
        if (! $this->getSwitcher('antispam_honeypot', true)) {
            return;
        }

        printf(
            '<p class="wp-addon-antispam-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
            .'<label for="%1$s">%2$s</label>'
            .'<input type="text" id="%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off" />'
            .'</p>',
            esc_attr(self::HONEYPOT_FIELD),
            esc_html__('Leave this field empty', 'wp-addon')
        );
    }

    public function addMonthlySchedule(array $schedules): array
    {
        if (! isset($schedules['wp_addon_monthly'])) {
            $schedules['wp_addon_monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display' => __('Once Monthly', 'wp-addon'),
            ];
        }

        return $schedules;
    }

    public function rotateLog(): void
    {
        CommentAntispamLogService::rotate();
    }

    public function renderKamaNotice(): void
    {
        if (! $this->isEnabled() || ! current_user_can('activate_plugins')) {
            return;
        }

        if (! function_exists('is_plugin_active') || ! is_plugin_active(self::KAMA_PLUGIN)) {
            return;
        }

        $deactivateUrl = wp_nonce_url(
            admin_url('plugins.php?action=deactivate&plugin='.rawurlencode(self::KAMA_PLUGIN)),
            'deactivate-plugin_'.self::KAMA_PLUGIN
        );

        echo '<div class="notice notice-warning"><p><strong>'.esc_html__('WP Addon: Comment Spam', 'wp-addon').'</strong> — ';
        echo esc_html__(
            'Kama SpamBlock is active and overlaps with this module. Its JavaScript nonce is not needed anymore and it can be deactivated.',
            'wp-addon'
        );
        echo ' <a href="'.esc_url($deactivateUrl).'">'.esc_html__('Deactivate Kama SpamBlock', 'wp-addon').'</a></p></div>';
    }

    public static function renderAdminPanel(): string
    {
        if (! is_admin()) {
            return '';
        }

        $report = CommentAntispamLogService::getReport(30);

        if ($report['total'] === 0) {
            return '<div class="notice notice-info inline"><p>'
                .esc_html__('No spam comments blocked in the last 30 days.', 'wp-addon')
                .'</p></div>';
        }

        $html = '<div class="wp-addon-antispam-stats" style="margin:0 0 12px">';
        $html .= '<p><strong>'
            .sprintf(esc_html__('Blocked in 30 days: %s', 'wp-addon'), esc_html(number_format_i18n($report['total'])))
            .'</strong></p>';

        if ($report['by_reason'] !== []) {
            $html .= '<p><strong>'.esc_html__('By reason', 'wp-addon').'</strong><br>';
            foreach ($report['by_reason'] as $reason => $hits) {
                $html .= esc_html($reason).' — '.esc_html(number_format_i18n($hits)).'<br>';
            }
            $html .= '</p>';
        }

        if ($report['top_ips'] !== []) {
            $html .= '<p><strong>'.esc_html__('Top IPs', 'wp-addon').'</strong><br>';
            foreach ($report['top_ips'] as $row) {
                $html .= esc_html($row['ip']).' — '.esc_html(number_format_i18n($row['hits'])).'<br>';
            }
            $html .= '</p>';
        }

        if ($report['last'] !== []) {
            $html .= '<p><strong>'.esc_html__('Last attempts', 'wp-addon').'</strong></p>';
            $html .= '<table class="widefat striped" style="max-width:100%"><thead><tr>'
                .'<th>'.esc_html__('Time', 'wp-addon').'</th>'
                .'<th>'.esc_html__('Reason', 'wp-addon').'</th>'
                .'<th>'.esc_html__('Score', 'wp-addon').'</th>'
                .'<th>'.esc_html__('IP', 'wp-addon').'</th>'
                .'<th>'.esc_html__('Author', 'wp-addon').'</th>'
                .'<th>'.esc_html__('Comment', 'wp-addon').'</th>'
                .'</tr></thead><tbody>';
            foreach ($report['last'] as $row) {
                $html .= '<tr>'
                    .'<td>'.esc_html((string) ($row['created_at'] ?? '')).'</td>'
                    .'<td>'.esc_html((string) ($row['reason'] ?? '')).'</td>'
                    .'<td>'.esc_html((string) ($row['score'] ?? '')).' / '.esc_html((string) ($row['threshold'] ?? '')).'</td>'
                    .'<td>'.esc_html((string) ($row['ip'] ?? '')).'</td>'
                    .'<td>'.esc_html((string) ($row['author'] ?? '')).'</td>'
                    .'<td>'.esc_html(mb_substr((string) ($row['content'] ?? ''), 0, 80)).'</td>'
                    .'</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<p style="margin-top:8px">'
            .sprintf(
                esc_html__('History is kept for %s months.', 'wp-addon'),
                esc_html(number_format_i18n(CommentAntispamLogService::RETENTION_MONTHS))
            )
            .'</p></div>';

        return $html;
    }

    private function scheduleRotation(): void
    {
        if (! wp_next_scheduled(CommentAntispamLogService::CRON_HOOK)) {
            wp_schedule_event(time(), 'wp_addon_monthly', CommentAntispamLogService::CRON_HOOK);
        }
    }

    private function backlinks(): CommentAntispamBacklinkService
    {
        return $this->backlinks ??= new CommentAntispamBacklinkService;
    }

    private function rateLimiter(): CommentAntispamRateLimitService
    {
        return $this->rateLimiter ??= new CommentAntispamRateLimitService(
            $this->getNumberSetting('antispam_rate_hour', 0),
            $this->getNumberSetting('antispam_rate_day', 0)
        );
    }

    private function trustsModerators(): bool
    {
        return $this->getSwitcher('antispam_trust_moderators', true)
            && current_user_can('moderate_comments');
    }

    private function checksBacklinks(): bool
    {
        return $this->getSwitcher('antispam_check_backlinks', true);
    }

    private function isFirstVisit(): bool
    {
        $hash = defined('COOKIEHASH') ? COOKIEHASH : md5((string) get_site_option('siteurl'));

        foreach (['comment_author_', 'comment_author_email_', 'comment_author_url_'] as $cookie) {
            if (! empty($_COOKIE[$cookie.$hash])) {
                return false;
            }
        }

        return true;
    }

    private function getPostedValue(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim(wp_unslash($value)) : '';
    }

    private function isEnabled(): bool
    {
        return $this->getSwitcher('antispam_enabled', true);
    }

    private function getSwitcher(string $key, bool $default): bool
    {
        return (bool) $this->getSetting($key, $default);
    }

    private function getNumberSetting(string $key, int $default): int
    {
        $value = $this->getSetting($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = get_option(self::OPTIONS_PREFIX, []);

        return is_array($settings) ? ($settings[$key] ?? $default) : $default;
    }
}
