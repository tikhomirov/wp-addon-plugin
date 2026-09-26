<?php

/**
 * Integration smoke test for the Comment Spam module.
 *
 * The Pest suite runs against a mock bootstrap, so it cannot prove the things
 * that only a real WordPress can prove: that the log table is created by
 * dbDelta(), that the cron event is really scheduled, that comment_form()
 * renders the honeypot, and that wp_new_comment() writes nothing when the gate
 * rejects. This script covers those and is meant to run locally or in staging.
 *
 * Usage (from the project root):
 *   make up
 *   make art c="wp eval-file wp-content/plugins/wp-addon-plugin/tests/wp-cli/comment-antispam-smoke.php"
 *
 * It writes rows into wp_addon_antispam_log and ages a few of them to prove the
 * rotation works. It cleans up the comment it creates, but the log rows stay.
 */
$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = 0;

function t(string $name, bool $ok, string $extra = ''): void
{
    if ($ok) {
        $GLOBALS['t_pass']++;
        echo "  PASS  {$name}\n";
    } else {
        $GLOBALS['t_fail']++;
        echo "  FAIL  {$name}  [{$extra}]\n";
    }
}

function reason_of($result): string
{
    return is_wp_error($result) ? $result->get_error_message() : '(not blocked)';
}

global $wpdb, $wp;

$table = $wpdb->prefix.'wp_addon_antispam_log';
$saved = get_option('wp-addon', []);
$saved = is_array($saved) ? $saved : [];

echo "== wiring ==\n";
t('module class loaded', class_exists('CommentAntispam'));
t('scoring service loaded', class_exists('WpAddon\\Services\\CommentAntispamScoringService'));
t('backlink service loaded', class_exists('WpAddon\\Services\\CommentAntispamBacklinkService'));
t('log service loaded', class_exists('WpAddon\\Services\\CommentAntispamLogService'));
t('pre_comment_approved hooked', has_filter('pre_comment_approved') !== false);
t('rest_pre_insert_comment hooked', has_filter('rest_pre_insert_comment') !== false);
t('comment_form_after_fields hooked', has_action('comment_form_after_fields') !== false);
t('admin_notices hooked', has_action('admin_notices') !== false);

echo "== settings defaults ==\n";
t('antispam_enabled on by default', ! isset($saved['antispam_enabled']) || (bool) $saved['antispam_enabled'] === true, var_export($saved['antispam_enabled'] ?? null, true));
t('threshold is 3', ! isset($saved['antispam_threshold']) || (int) $saved['antispam_threshold'] === 3, var_export($saved['antispam_threshold'] ?? null, true));
t('hourly limit off', ! isset($saved['antispam_rate_hour']) || (int) $saved['antispam_rate_hour'] === 0, var_export($saved['antispam_rate_hour'] ?? null, true));
t('daily limit off', ! isset($saved['antispam_rate_day']) || (int) $saved['antispam_rate_day'] === 0, var_export($saved['antispam_rate_day'] ?? null, true));
t('honeypot on by default', ! isset($saved['antispam_honeypot']) || (bool) $saved['antispam_honeypot'] === true);
t('min_seconds is 3', ! isset($saved['antispam_min_seconds']) || (int) $saved['antispam_min_seconds'] === 3);

echo "== log table ==\n";
t('table created', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table, $table);
t('db version stored', (string) get_option('wp_addon_antispam_db_version') !== '', (string) get_option('wp_addon_antispam_db_version'));
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
t('has reason/ip/score columns', is_array($cols) && count(array_intersect(['reason', 'ip', 'score', 'user_agent'], $cols)) === 4, implode(',', (array) $cols));

echo "== cron ==\n";
$schedules = apply_filters('cron_schedules', []);
t('monthly schedule registered', isset($schedules['wp_addon_monthly']), implode(',', array_keys((array) $schedules)));
t('rotation scheduled', wp_next_scheduled('wp_addon_antispam_rotate') !== false, (string) wp_next_scheduled('wp_addon_antispam_rotate'));

echo "== blocking ==\n";
$post_id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='post' LIMIT 1");
t('found a published post', $post_id > 0, (string) $post_id);

$base = [
    'comment_post_ID' => $post_id,
    'comment_author_url' => '',
    'comment_author_IP' => '203.0.113.77',
    'comment_type' => 'comment',
    'comment_agent' => 'integration-test',
];

$_POST = [];
$spam = array_merge($base, [
    'comment_author' => 'ztpyuOkqNTwQsQKwwkC',
    'comment_author_email' => 'o.x.on.e.j.o.f.eza.96@gmail.com',
    'comment_content' => 'IjtRwXHmcvybfzgmAiM',
]);
$before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
$res = apply_filters('pre_comment_approved', 1, $spam);
t('real spam sample is blocked', is_wp_error($res), reason_of($res));
t('blocked with a plain 403 status', is_wp_error($res) && (int) $res->get_error_data() === 403, var_export($res instanceof WP_Error ? $res->get_error_data() : null, true));
$row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
t('spam attempt logged', is_array($row), 'no row');
t('log keeps the ip', is_array($row) && $row['ip'] === '203.0.113.77', is_array($row) ? $row['ip'] : '');
t('log keeps the score reason', is_array($row) && str_starts_with((string) $row['reason'], 'score:'), is_array($row) ? (string) $row['reason'] : '');
t('log keeps the content', is_array($row) && $row['content'] === 'IjtRwXHmcvybfzgmAiM');
t('log keeps the user agent', is_array($row) && $row['user_agent'] === 'integration-test');
$after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
t('blocked comment is NOT stored', $after === $before, "{$before} -> {$after}");

echo "== false positives ==\n";
$_POST = [];
$legit = [
    ['comment_author' => 'Алексей', 'comment_author_email' => 'a.leks@mail.ru', 'comment_content' => 'Спасибо, статья очень полезная!'],
    ['comment_author' => 'Мария', 'comment_author_email' => 'user.name@gmail.com', 'comment_content' => 'Добавьте, пожалуйста, пример с Docker.'],
    ['comment_author' => 'John', 'comment_author_email' => 'john@example.com', 'comment_content' => 'Thanks for the article, very helpful!'],
    ['comment_author' => 'Иван', 'comment_author_email' => 'ivan@gmail.com', 'comment_content' => 'Проверил на Bitrix 24, всё работает.'],
    ['comment_author' => 'Ольга', 'comment_author_email' => 'olga@yandex.ru', 'comment_content' => 'WP 7.1 и PHP 8.3 — ошибок нет, спасибо!'],
    ['comment_author' => 'Пётр', 'comment_author_email' => 'p.tr@gmail.com', 'comment_content' => 'А можно подробнее про Git submodules?'],
    ['comment_author' => 'kama-spam', 'comment_author_email' => 's.p.a.m@gmail.com', 'comment_content' => 'Interesting article about WordPress security and spam protection.'],
];
$ip = 198;
foreach ($legit as $i => $case) {
    $case['comment_author_IP'] = '198.51.100.'.$ip++;
    $case['comment_post_ID'] = $post_id;
    $case['comment_type'] = 'comment';
    $result = apply_filters('pre_comment_approved', 1, $case);
    t('real comment #'.($i + 1).' passes', ! is_wp_error($result), $case['comment_author'].' => '.reason_of($result));
}

echo "== honeypot and time trap ==\n";
$clean = [
    'comment_post_ID' => $post_id,
    'comment_author' => 'Гость',
    'comment_author_email' => 'guest@example.com',
    'comment_content' => 'Нормальный комментарий для проверки.',
    'comment_author_IP' => '198.51.100.50',
    'comment_type' => 'comment',
];

$_POST = [];
t('no honeypot value passes', ! is_wp_error(apply_filters('pre_comment_approved', 1, $clean)));

$_POST['antispam_website'] = 'http://buy-cheap-stuff.example';
t('filled honeypot is blocked', is_wp_error(apply_filters('pre_comment_approved', 1, $clean)), reason_of(apply_filters('pre_comment_approved', 1, $clean)));

$_POST = ['antispam_ts' => (string) (time() - 1)];
t('submission in under 3s is blocked', is_wp_error(apply_filters('pre_comment_approved', 1, $clean)), reason_of(apply_filters('pre_comment_approved', 1, $clean)));

$_POST = ['antispam_ts' => (string) (time() - 60)];
t('submission after 60s passes', ! is_wp_error(apply_filters('pre_comment_approved', 1, $clean)));

$_POST = ['antispam_ts' => 'not-a-number'];
t('garbage timestamp is not a rejection', ! is_wp_error(apply_filters('pre_comment_approved', 1, $clean)));

$_POST = [];

echo "== pingback / trackback ==\n";
$ping = [
    'comment_post_ID' => $post_id,
    'comment_author' => 'Pingback',
    'comment_author_email' => '',
    'comment_author_url' => 'https://example.org/no-link-here',
    'comment_content' => 'https://example.org/no-link-here',
    'comment_author_IP' => '203.0.113.99',
    'comment_type' => 'pingback',
];
t('pingback with unreachable source is blocked', is_wp_error(apply_filters('pre_comment_approved', 1, $ping)), reason_of(apply_filters('pre_comment_approved', 1, $ping)));

$pingEmpty = array_merge($ping, ['comment_author_url' => '']);
t('pingback without source url is blocked', is_wp_error(apply_filters('pre_comment_approved', 1, $pingEmpty)), reason_of(apply_filters('pre_comment_approved', 1, $pingEmpty)));

$track = array_merge($pingEmpty, ['comment_type' => 'trackback']);
t('trackback without source url is blocked', is_wp_error(apply_filters('pre_comment_approved', 1, $track)));

echo "== other comment types are untouched ==\n";
foreach (['review', 'order_note', 'contact'] as $type) {
    $data = array_merge($clean, ['comment_type' => $type, 'comment_author_IP' => '198.51.100.60']);
    t("type {$type} passes", ! is_wp_error(apply_filters('pre_comment_approved', 1, $data)));
}

echo "== WordPress short-circuit integration ==\n";
$before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
$_POST = [];
$err = wp_new_comment($spam, true);
t('wp_new_comment refuses spam', is_wp_error($err), is_wp_error($err) ? $err->get_error_code() : 'inserted id '.var_export($err, true));
$after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
t('nothing was written to wp_comments', $after === $before, "{$before} -> {$after}");

$new_id = wp_new_comment(array_merge($legit[0], ['comment_approved' => 1]));
t('wp_new_comment stores a real comment', is_int($new_id) && $new_id > 0, var_export($new_id, true));
if (is_int($new_id) && $new_id > 0) {
    wp_delete_comment($new_id, true);
    t('cleanup of the test comment', true);
}

echo "== rest payload guard ==\n";
$restSpam = apply_filters('rest_pre_insert_comment', $spam);
t('rest_pre_insert_comment blocks spam', is_wp_error($restSpam), reason_of($restSpam));
t('rest error carries a 403 status', is_wp_error($restSpam) && (int) ($restSpam->get_error_data()['status'] ?? 0) === 403, var_export($restSpam instanceof WP_Error ? $restSpam->get_error_data() : null, true));
t('rest_pre_insert_comment keeps real comments', is_array(apply_filters('rest_pre_insert_comment', $legit[0])));

echo "== honeypot markup ==\n";
ob_start();
do_action('comment_form_after_fields');
$markup = (string) ob_get_clean();
t('honeypot field rendered', str_contains($markup, 'name="antispam_website"'), substr($markup, 0, 120));
t('honeypot is hidden inline', str_contains($markup, 'left:-9999px'));
t('honeypot is aria-hidden', str_contains($markup, 'aria-hidden="true"'));
t('honeypot is not focusable', str_contains($markup, 'tabindex="-1"'));

echo "== assets ==\n";
$oldStatus = $wpdb->get_var($wpdb->prepare("SELECT comment_status FROM {$wpdb->posts} WHERE ID = %d", $post_id));
$wpdb->update($wpdb->posts, ['comment_status' => 'open'], ['ID' => $post_id]);
clean_post_cache($post_id);

$GLOBALS['wp_query'] = new WP_Query(['p' => $post_id, 'post_type' => 'post', 'posts_per_page' => 1]);
$GLOBALS['post'] = $GLOBALS['wp_query']->post;
setup_postdata($GLOBALS['post']);
t('comments are open for the test post', comments_open((int) $post_id), (string) $oldStatus);
do_action('wp_enqueue_scripts');
t('style enqueued on a singular view', wp_style_is('wp-addon-comment-antispam', 'enqueued'));
t('script enqueued on a singular view', wp_script_is('wp-addon-comment-antispam', 'enqueued'));
wp_reset_postdata();

echo "== real comment_form() markup ==\n";
$GLOBALS['wp_query'] = new WP_Query(['p' => $post_id, 'post_type' => 'post', 'posts_per_page' => 1]);
$GLOBALS['post'] = $GLOBALS['wp_query']->post;
setup_postdata($GLOBALS['post']);
ob_start();
comment_form();
$form = (string) ob_get_clean();
wp_reset_postdata();
$wpdb->update($wpdb->posts, ['comment_status' => (string) $oldStatus], ['ID' => $post_id]);
clean_post_cache($post_id);

t('form was rendered', $form !== '', 'len '.strlen($form));
t('form posts to wp-comments-post.php', str_contains($form, 'wp-comments-post.php'));
t('form contains the honeypot', str_contains($form, 'name="antispam_website"'));
t('form does not contain a prefilled timestamp', ! str_contains($form, 'name="antispam_ts"'));
t('honeypot sits inside the form tag', strpos($form, 'antispam_website') > strpos($form, '<form') && strpos($form, 'antispam_website') < strpos($form, '</form>'));
t('honeypot comes after the identity fields', strpos($form, 'antispam_website') > strpos($form, 'name="email"'));
t('honeypot comes before the submit button', strpos($form, 'antispam_website') < strpos($form, 'id="submit"'));

echo "== asset files ==\n";
$css = file_get_contents(RW_PLUGIN_DIR.'assets/css/comment-antispam.css');
t('css hides the honeypot', is_string($css) && str_contains($css, 'wp-addon-antispam-hp'));
$js = file_get_contents(RW_PLUGIN_DIR.'assets/js/comment-antispam.js');
t('js stamps the timestamp', is_string($js) && str_contains($js, 'antispam_ts'));
t('js removes the honeypot', is_string($js) && str_contains($js, 'antispam_website'));

echo "== admin panel ==\n";
set_current_screen('options-general.php');
t('is_admin context available', is_admin());
$panel = CommentAntispam::renderAdminPanel();
t('admin panel renders without fatal', is_string($panel) && $panel !== '');
t('admin panel shows the total', is_string($panel) && str_contains($panel, 'Blocked in 30 days'));
t('admin panel escaped', is_string($panel) && ! preg_match('/<script/i', $panel));
t('admin panel shows the honeypot count', is_string($panel) && preg_match('/honeypot/', $panel) === 1);
t('admin panel shows the ip', is_string($panel) && str_contains($panel, '203.0.113.77'));

echo "== rotation ==\n";
$rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$staleIds = $wpdb->get_col("SELECT id FROM {$table} ORDER BY id DESC LIMIT 5");
$aged = $staleIds === []
    ? 0
    : $wpdb->query("UPDATE {$table} SET created_gmt = created_gmt - INTERVAL 10 YEAR WHERE id IN (".implode(',', array_map('intval', $staleIds)).')');
t('stale rows prepared for the test', (int) $aged === 5 && $rows > 5, "aged={$aged} total={$rows}");
do_action('wp_addon_antispam_rotate');
t('rotation removed exactly the stale rows', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === $rows - 5, 'now '.$wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
t('rotation left the fresh rows in place', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_gmt > UTC_TIMESTAMP() - INTERVAL 10 YEAR") === $rows - 5);

echo "\n== totals ==\n";
echo "  passed: {$GLOBALS['t_pass']}  failed: {$GLOBALS['t_fail']}\n";
