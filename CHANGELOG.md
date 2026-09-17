# Changelog

All notable changes to this project are documented in this file.

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
