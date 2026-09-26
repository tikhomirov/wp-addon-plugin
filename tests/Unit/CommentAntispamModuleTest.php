<?php

use WpAddon\Services\CommentAntispamLogService;
use WpAddon\Services\CommentAntispamScoringService;

/**
 * The real spam sample reported on rwsite.ru.
 */
function antispam_spam_sample(array $overrides = []): array
{
    return array_merge([
        'comment_post_ID' => 7700,
        'comment_author' => 'ztpyuOkqNTwQsQKwwkC',
        'comment_author_email' => 'o.x.on.e.j.o.f.eza.96@gmail.com',
        'comment_author_url' => '',
        'comment_author_IP' => '203.0.113.77',
        'comment_content' => 'IjtRwXHmcvybfzgmAiM',
        'comment_type' => 'comment',
        'comment_agent' => 'Mozilla/5.0',
    ], $overrides);
}

function antispam_real_comment(array $overrides = []): array
{
    return array_merge([
        'comment_post_ID' => 7700,
        'comment_author' => 'Алексей',
        'comment_author_email' => 'a.leks@mail.ru',
        'comment_author_url' => '',
        'comment_author_IP' => '198.51.100.5',
        'comment_content' => 'Спасибо, статья очень полезная!',
        'comment_type' => 'comment',
    ], $overrides);
}

/**
 * Minimal in-memory stand-in for wpdb, which the mock bootstrap does not ship.
 */
class AntispamModuleFakeWpdb
{
    public string $prefix = 'wp_';

    public array $inserts = [];

    public bool $tablePresent = true;

    public int $total = 0;

    public function prepare($query, ...$args)
    {
        return $query;
    }

    public function get_var($query = null)
    {
        if (str_contains((string) $query, 'COUNT(*)')) {
            return (string) $this->total;
        }

        return $this->tablePresent ? $this->prefix.CommentAntispamLogService::TABLE : null;
    }

    public function get_results($query = null, $output = null)
    {
        return [];
    }

    public function insert($table, $row, $formats = null): int
    {
        $this->inserts[] = $row;

        return 1;
    }

    public function query($sql): int
    {
        return 0;
    }
}

beforeEach(function () {
    global $wpdb, $mock_user_capabilities, $mock_is_admin, $mock_functions, $mock_http_bodies, $db;

    $wpdb = new AntispamModuleFakeWpdb;
    $mock_user_capabilities = [];
    $mock_is_admin = true;
    $mock_functions = [];
    $mock_http_bodies = [];
    $db->exec('DELETE FROM wp_options');
    $_POST = [];
    $_COOKIE = [];
});

/**
 * The mock get_option() cannot store arrays, so the settings option is stubbed.
 */
function antispam_set_settings(array $settings): void
{
    global $mock_functions;

    $mock_functions['get_option'] = function ($key, $default = '') use ($settings) {
        return $key === 'wp-addon' ? $settings : $default;
    };
}

describe('CommentAntispam gate', function () {
    it('blocks the reported spam sample with a 403 for wp-comments-post.php', function () {
        $result = (new CommentAntispam)->filterPreCommentApproved(1, antispam_spam_sample());

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('comment_antispam_rejected');
        expect((int) $result->get_error_data())->toBe(403);
        expect($result->get_error_message())->not->toBeEmpty();
    });

    it('logs the blocked attempt with the ip, score and reason', function () {
        global $wpdb;

        (new CommentAntispam)->filterPreCommentApproved(1, antispam_spam_sample());

        expect($wpdb->inserts)->toHaveCount(1);

        $row = $wpdb->inserts[0];

        expect($row['ip'])->toBe('203.0.113.77');
        expect($row['score'])->toBeGreaterThanOrEqual(3);
        expect($row['threshold'])->toBe(3);
        expect($row['reason'])->toStartWith('score:');
        expect($row['reason'])->toContain(CommentAntispamScoringService::REASON_TEXT_TOKEN);
        expect($row['content'])->toBe('IjtRwXHmcvybfzgmAiM');
        expect($row['user_agent'])->toBe('Mozilla/5.0');
    });

    it('passes real reader comments without logging anything', function () {
        global $wpdb;

        $comments = [
            ['comment_author' => 'Алексей', 'comment_content' => 'Спасибо, статья очень полезная!'],
            ['comment_author' => 'Мария', 'comment_author_email' => 'user.name@gmail.com', 'comment_content' => 'Добавьте пример с Docker.'],
            ['comment_author' => 'John', 'comment_content' => 'Thanks for the article, very helpful!'],
            ['comment_author' => 'Иван', 'comment_content' => 'Проверил на Bitrix 24, всё работает.'],
            ['comment_author' => 'Пётр', 'comment_content' => 'А можно подробнее про submodules?'],
            ['comment_author' => 'kama', 'comment_content' => 'Interesting article about WordPress security.'],
        ];

        foreach ($comments as $index => $comment) {
            $result = (new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment($comment));

            expect($result)->toBe(1, 'comment #'.($index + 1).' must pass');
        }

        expect($wpdb->inserts)->toBeEmpty();
    });

    it('blocks a filled honeypot and logs it as such', function () {
        global $wpdb;
        $_POST[CommentAntispam::HONEYPOT_FIELD] = 'http://buy-cheap-stuff.example';

        $result = (new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment());

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($wpdb->inserts[0]['reason'])->toBe(CommentAntispam::REASON_HONEYPOT);
        expect($wpdb->inserts[0]['score'])->toBe(0);
    });

    it('ignores a honeypot that only holds whitespace', function () {
        $_POST[CommentAntispam::HONEYPOT_FIELD] = "  \n ";

        expect((new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment()))->toBe(1);
    });

    it('blocks a form submitted faster than the allowed time', function () {
        global $wpdb;
        $_POST[CommentAntispam::TIMESTAMP_FIELD] = (string) (time() - 1);

        $result = (new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment());

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($wpdb->inserts[0]['reason'])->toBe(CommentAntispam::REASON_TOO_FAST);
    });

    it('accepts a form filled after the allowed time', function () {
        $_POST[CommentAntispam::TIMESTAMP_FIELD] = (string) (time() - 60);

        expect((new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment()))->toBe(1);
    });

    it('treats a missing timestamp as no javascript, not as a bot', function () {
        expect((new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment()))->toBe(1);
    });

    it('ignores a timestamp that is not a number', function () {
        $_POST[CommentAntispam::TIMESTAMP_FIELD] = 'not-a-number';

        expect((new CommentAntispam)->filterPreCommentApproved(1, antispam_real_comment()))->toBe(1);
    });

    it('lets users who can moderate comments through', function () {
        global $mock_user_capabilities, $wpdb;
        $mock_user_capabilities = ['moderate_comments'];
        $_POST[CommentAntispam::HONEYPOT_FIELD] = 'bot filled it';

        expect((new CommentAntispam)->filterPreCommentApproved(1, antispam_spam_sample()))->toBe(1);
        expect($wpdb->inserts)->toBeEmpty();
    });

    it('keeps a decision that the disallowed keys list already made', function () {
        global $wpdb;

        expect((new CommentAntispam)->filterPreCommentApproved('spam', antispam_spam_sample()))->toBe('spam');
        expect((new CommentAntispam)->filterPreCommentApproved('trash', antispam_spam_sample()))->toBe('trash');
        expect($wpdb->inserts)->toBeEmpty();
    });

    it('passes an error that another plugin has already raised', function () {
        $existing = new WP_Error('some_other_plugin', 'Nope');

        expect((new CommentAntispam)->filterPreCommentApproved($existing, antispam_real_comment()))->toBe($existing);
    });

    it('leaves comment types it does not own alone', function () {
        foreach (['review', 'order_note', 'contact', 'resume', 'job'] as $type) {
            $data = antispam_real_comment(['comment_type' => $type]);

            expect((new CommentAntispam)->filterPreCommentApproved(1, $data))
                ->toBe(1, "type {$type} must pass untouched");
        }
    });

    it('blocks a pingback and a trackback without a source url', function () {
        global $wpdb;

        foreach (['pingback', 'trackback'] as $type) {
            $data = antispam_spam_sample([
                'comment_type' => $type,
                'comment_author_url' => '',
                'comment_content' => '',
            ]);

            $result = (new CommentAntispam)->filterPreCommentApproved(1, $data);

            expect($result)->toBeInstanceOf(WP_Error::class);
            expect($wpdb->inserts[count($wpdb->inserts) - 1]['reason'])
                ->toBe(CommentAntispam::REASON_NO_BACKLINK);
        }
    });

    it('blocks a pingback whose source page cannot be fetched', function () {
        global $wpdb;
        $data = antispam_spam_sample([
            'comment_type' => 'pingback',
            'comment_author_url' => 'https://example.org/unreachable',
        ]);

        expect((new CommentAntispam)->filterPreCommentApproved(1, $data))->toBeInstanceOf(WP_Error::class);
        expect($wpdb->inserts[0]['reason'])->toBe(CommentAntispam::REASON_NO_BACKLINK);
    });

    it('accepts a pingback whose source page links back to the site', function () {
        global $mock_http_bodies, $wpdb;

        $mock_http_bodies = [
            'https://example.org/good' => '<div><a href="https://localhost/hello/">hello</a></div>',
        ];

        $data = antispam_spam_sample([
            'comment_type' => 'pingback',
            'comment_author_url' => 'https://example.org/good',
            'comment_content' => 'https://example.org/good',
        ]);

        expect((new CommentAntispam)->filterPreCommentApproved(1, $data))->toBe(1);
        expect($wpdb->inserts)->toBeEmpty();
    });

    it('blocks a pingback whose source page has no link back', function () {
        global $mock_http_bodies, $wpdb;

        $mock_http_bodies = [
            'https://example.org/spammy' => '<div><a href="https://spam.example/buy/">buy</a></div>',
        ];

        $data = antispam_spam_sample([
            'comment_type' => 'pingback',
            'comment_author_url' => 'https://example.org/spammy',
            'comment_content' => 'https://example.org/spammy',
        ]);

        expect((new CommentAntispam)->filterPreCommentApproved(1, $data))->toBeInstanceOf(WP_Error::class);
        expect($wpdb->inserts[0]['reason'])->toBe(CommentAntispam::REASON_NO_BACKLINK);
    });
});

describe('CommentAntispam rest gate', function () {
    it('returns a 403 status array that the rest server understands', function () {
        $result = (new CommentAntispam)->filterRestPreInsertComment(antispam_spam_sample());

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('comment_antispam_rejected');
        expect($result->get_error_data())->toBe(['status' => 403]);
    });

    it('returns the prepared comment untouched when it is fine', function () {
        $comment = antispam_real_comment();

        expect((new CommentAntispam)->filterRestPreInsertComment($comment))->toBe($comment);
    });

    it('passes an error from another filter through unchanged', function () {
        $existing = new WP_Error('rest_comment_invalid_id', 'Invalid post ID.', ['status' => 403]);

        expect((new CommentAntispam)->filterRestPreInsertComment($existing))->toBe($existing);
    });
});

describe('CommentAntispam cron schedule', function () {
    it('adds a monthly interval', function () {
        $schedules = (new CommentAntispam)->addMonthlySchedule([]);

        expect($schedules)->toHaveKey('wp_addon_monthly');
        expect($schedules['wp_addon_monthly']['interval'])->toBe(30 * DAY_IN_SECONDS);
        expect($schedules['wp_addon_monthly']['display'])->not->toBeEmpty();
    });

    it('keeps schedules that other modules already registered', function () {
        $existing = ['hourly' => ['interval' => HOUR_IN_SECONDS, 'display' => 'Hourly']];

        $schedules = (new CommentAntispam)->addMonthlySchedule($existing);

        expect($schedules)->toHaveKey('hourly');
        expect($schedules)->toHaveKey('wp_addon_monthly');
    });

    it('does not overwrite the interval when it is already registered', function () {
        $existing = ['wp_addon_monthly' => ['interval' => DAY_IN_SECONDS, 'display' => 'Daily']];

        $schedules = (new CommentAntispam)->addMonthlySchedule($existing);

        expect($schedules['wp_addon_monthly']['interval'])->toBe(DAY_IN_SECONDS);
    });
});

describe('CommentAntispam honeypot markup', function () {
    it('renders a hidden field that carries no value', function () {
        $module = new CommentAntispam;

        ob_start();
        $module->renderHoneypot();
        $markup = (string) ob_get_clean();

        expect($markup)->toContain('name="'.CommentAntispam::HONEYPOT_FIELD.'"');
        expect($markup)->toContain('id="'.CommentAntispam::HONEYPOT_FIELD.'"');
        expect($markup)->toContain('type="text"');
        expect($markup)->toContain('value=""');
    });

    it('keeps the field out of the tab order and out of the page', function () {
        $module = new CommentAntispam;

        ob_start();
        $module->renderHoneypot();
        $markup = (string) ob_get_clean();

        expect($markup)->toContain('tabindex="-1"');
        expect($markup)->toContain('aria-hidden="true"');
        expect($markup)->toContain('left:-9999px');
    });

    it('renders nothing when the honeypot is switched off', function () {
        antispam_set_settings(['antispam_honeypot' => false]);

        $module = new CommentAntispam;

        ob_start();
        $module->renderHoneypot();

        expect(ob_get_clean())->toBe('');
    });
});

describe('CommentAntispam admin panel', function () {
    it('stays empty outside the admin', function () {
        global $mock_is_admin;
        $mock_is_admin = false;

        expect(CommentAntispam::renderAdminPanel())->toBe('');
    });

    it('explains an empty log', function () {
        global $wpdb;
        $wpdb->total = 0;

        expect(CommentAntispam::renderAdminPanel())->toContain('No spam comments blocked');
    });

    it('shows the total and the retention window once something was blocked', function () {
        global $wpdb;
        $wpdb->total = 12;

        $panel = CommentAntispam::renderAdminPanel();

        expect($panel)->toContain('12');
        expect($panel)->toContain('months');
    });

    it('never emits raw script tags', function () {
        global $wpdb;
        $wpdb->total = 5;

        expect(CommentAntispam::renderAdminPanel())->not->toMatch('/<script/i');
    });
});
