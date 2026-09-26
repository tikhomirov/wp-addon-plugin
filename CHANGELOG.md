# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added

- Comment Spam module: honeypot field, client-side time trap, additive content
  scoring, optional per-IP rate limits, and pingback/trackback backlink
  verification
- Blocked attempts log with IP, reason, score, user agent, and author details;
  admin statistics with a reason breakdown, top IPs, and the last attempts
- Monthly `wp_addon_antispam_rotate` cron event that prunes the log, plus
  opportunistic pruning every 50 writes
- `CommentAntispam` module, `CommentAntispamScoringService`,
  `CommentAntispamBacklinkService`, `CommentAntispamRateLimitService`, and
  `CommentAntispamLogService`
- New `Comment Spam` settings section and assets
  `assets/js/comment-antispam.js`, `assets/css/comment-antispam.css`
- Unit tests for scoring, backlink verification, rate limits, the log store, and
  the gate itself (`CommentAntispamScoringTest`, `CommentAntispamBacklinkTest`,
  `CommentAntispamRateLimitTest`, `CommentAntispamLogServiceTest`,
  `CommentAntispamModuleTest`)
- `tests/wp-cli/comment-antispam-smoke.php`, a 79-check script for a real
  WordPress, covering the log table, cron scheduling, `comment_form()` markup,
  asset enqueue, the admin panel, and that `wp_new_comment()` writes nothing
  when the gate rejects
- Missing WordPress mocks in `tests/bootstrap.php`: `wp_parse_url`,
  `get_site_option`, `number_format_i18n`, `ARRAY_A`, `wp_safe_remote_get`,
  `wp_remote_get`, `wp_remote_retrieve_body`, `wp_remote_retrieve_response_code`

### Changed

- Spam is now rejected with a `WP_Error` and HTTP 403 instead of being stored
  as `spam`, so no moderation queue entries, emails, or cache purges are
  produced
- REST comment creation is filtered through `rest_pre_insert_comment`, which
  the comments controller uses instead of `pre_comment_approved`

### Fixed

- Rotation schedule now registers the custom `wp_addon_monthly` interval before
  scheduling, so the cron event is actually created
- REST rejections carry a `['status' => 403]` error payload, matching what
  `rest_convert_error_to_response()` expects
- The backlink pattern no longer contains a stray `\\s` in its lookahead, which
  made a host that merely starts with the site host (`rwsite.rus` for
  `rwsite.ru`) count as a valid backlink
- The honeypot is hidden with inline styles as well as CSS, so it stays invisible
  even if the stylesheet fails to load
- Comments whose type is neither `comment`, `pingback` nor `trackback` are left
  alone, and a comment already marked `spam` or `trash` by the disallowed keys
  list keeps its original status
- `CommentAntispam` builds its backlink and rate limit services on first use
  instead of in `init()`, so the gate no longer depends on the initialisation
  order

### Removed

- Kama SpamBlock (`wpackagist-plugin/kama-spamblock`) — its checks are now part
  of the Comment Spam module

## [1.4.0] — 2026-09-17

### Requirements

- **WordPress:** 6.6+ (tested up to 6.8)
- **PHP:** 8.2+ (tested on 8.2, 8.3, 8.4)

### Added

- Redirects service with exact, prefix, and regex rules; import/export
- Cookie Banner module with customizable copy and styling
- Markdown editor mode for posts and pages
- Plugin catalog UI with GitHub metadata and search
- 12 new WP Tweaker toggles (48 total)
- `sanitize_cached_catalog()` to prevent PHP warnings from stale plugin catalog transients

### Changed

- Full tweaks audit: fixed naming, removed dead setting callbacks
- Updated README with accurate system requirements and feature list
- Raised minimum PHP from 7.4 to **8.2**
- Raised minimum WordPress to **6.6**

### Fixed

- Undefined array key `name` warning on Plugins admin page when GitHub catalog cache was corrupted
- Incorrect tweak labels and orphaned callback references after settings refactor

## [1.3.6] — earlier

Previous stable release with 36 WP Tweaker toggles and core admin/SEO modules.

[1.4.0]: https://github.com/rwsite/wp-addon-plugin/compare/1.3.6...1.4.0
[1.3.6]: https://github.com/rwsite/wp-addon-plugin/releases/tag/1.3.6
