# WP Excellence Addon

[![Version](https://img.shields.io/badge/version-1.5.3-blue.svg)](https://github.com/rwsite/wp-addon-plugin/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net/)
[![Tests](https://img.shields.io/badge/tests-265%20passed-green.svg)](https://github.com/rwsite/wp-addon-plugin/actions)

Transforms a standard WordPress site into a faster, safer, and easier-to-manage installation. The plugin bundles **WP Tweaker** (48 performance and security tweaks), **Redirects**, **Cookie Banner**, **Markdown editor**, **Plugin catalog**, **Comment spam protection**, and admin utilities in one place.

## Requirements

| Component | Minimum | Tested |
|-----------|---------|--------|
| **WordPress** | 6.6 | 6.6 – 7.2 |
| **PHP** | 8.2 | 8.2, 8.3, 8.4 |
| **CodeStar Framework** | 2.3.1 | bundled copy, sibling plugin, or auto-installed |

The plugin does **not** support PHP 7.x or WordPress versions below 6.6. CI runs the test suite on PHP 8.2, 8.3, and 8.4.

## Features

### WP Tweaker — 48 toggles

Performance, security, and housekeeping tweaks grouped in admin settings. Each toggle is independent and can be enabled without touching code.

Categories include:

- **Performance** — disable emojis, dashicons on frontend, oEmbed, Heartbeat, query strings on static assets, and more
- **Security** — hide WP version, disable XML-RPC, block user enumeration, disable file editing, block Automattic tracking
- **Admin UX** — custom admin footer, dashboard cleanup, post list columns, duplicate posts, SVG upload support
- **SEO & URLs** — hierarchical tags, category base removal, slug transliteration, noindex for archives

### Redirects

Rule-based redirect manager with support for exact, prefix, and regex matches. Handles 301/302/307/308, query strings, and import/export.

### Cookie Banner

GDPR-oriented cookie consent banner with customizable text, button labels, and styling.

### Markdown Editor

Optional Markdown editing mode for posts and pages with live preview.

### Plugin Catalog

Built-in catalog of project plugins with GitHub metadata, search, and quick links — useful on multi-plugin setups.

### Comment Spam

Rejects spam **before** it reaches the database, so nothing is stored, no moderation email is sent, and the page cache is never purged.

- **Honeypot** — a hidden field that form-filling bots trip over
- **Time trap** — a timestamp written by the browser, because cached pages make a server-side check useless
- **Scoring** — additive signals (random body token, token-like author, no Cyrillic, dotted email, first visit) against a threshold
- **Backlink verification** — for pingbacks and trackbacks, as Kama SpamBlock did
- **Rate limits** — per IP, off by default
- **Logging** — blocked attempts with IP, reason, score, and user agent, pruned monthly

### Other modules

- Image optimization and media cleanup services
- Shortcodes, TinyMCE extensions, comment tweaks
- Integration with CodeStar Framework settings UI

## Installation

### From Git (submodule)

```bash
git submodule add git@github.com:rwsite/wp-addon-plugin.git wp-content/plugins/wp-addon-plugin
```

### Manual

1. Download the [latest release](https://github.com/rwsite/wp-addon-plugin/releases).
2. Upload to `wp-content/plugins/wp-addon-plugin/`.
3. Activate **WP Excellence Addon** in WordPress admin.

### Composer (inside the plugin)

```bash
cd wp-content/plugins/wp-addon-plugin
composer install
```

## CodeStar Framework

The admin settings screen is built on [CodeStar Framework](https://github.com/Codestar/codestar-framework) (`CSF`). The plugin resolves the framework automatically, in this order:

1. **Already loaded** — e.g. the standalone `codestar-framework` plugin is active (this repository ships it as a submodule in `wp-content/plugins/codestar-framework/`).
2. **Bundled copy** — `wp-addon-plugin/lib/codestar-framework/` (git-ignored; created by the installer).
3. **Automatic install** — on the first wp-admin page load, and at most once an hour afterwards, the plugin downloads the pinned `2.3.1` release from GitHub into `lib/`. Requires a user with the `install_plugins` capability and write access to `wp-content/plugins/`.
4. **Manual install** — click **Install CodeStar Framework now** in the admin notice, or download a release into `wp-content/plugins/codestar-framework/` and activate it.

If every source fails, an admin notice shows the underlying download or filesystem error, and the settings screen stays hidden until `CSF` is available.

## Development

```bash
composer install
composer test          # 265 unit tests
composer test:coverage
composer analyse       # PHPStan level 2
composer lint          # Pint
composer quality       # lint + analyse + test
```

## Architecture

```
wp-addon-plugin/
├── wp-addon-plugin.php      # Bootstrap
├── src/
│   ├── Core/Plugin.php      # Initialization, module loader
│   ├── ControllerWP.php     # Option-driven tweak activation
│   ├── Config/              # Settings field definitions
│   └── Services/            # Redirects, assets, media, etc.
├── functions/               # Procedural modules (auto-loaded)
│   ├── seo/
│   ├── posts/
│   ├── shortcodes/
│   └── ...
└── tests/Unit/
```

Modules under `functions/` are loaded automatically by `Plugin::loadModules()`. Tweaks registered in `src/Config/tweaks.php` are activated via `ControllerWP` based on saved settings.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later

## Author

Aleksey Tikhomirov — [rwsite.ru](https://rwsite.ru)

---

## Русский

**WP Excellence Addon** — плагин для оптимизации, безопасности и удобства администрирования WordPress.

### Совместимость

- **WordPress:** от 6.6, протестировано на 6.6 – 6.8
- **PHP:** от 8.2, протестировано на 8.2, 8.3, 8.4

PHP 7.x и WordPress ниже 6.6 **не поддерживаются**.

### Что входит

- **WP Tweaker** — 48 переключателей (производительность, безопасность, админка, SEO)
- **Редиректы** — правила с exact/prefix/regex, импорт/экспорт
- **Cookie Banner** — баннер согласия на cookies
- **Markdown-редактор** для записей и страниц
- **Каталог плагинов** с данными GitHub

Установка: скачать [релиз](https://github.com/rwsite/wp-addon-plugin/releases) или подключить как git submodule. После активации настройки доступны в админке WordPress.

### CodeStar Framework

Экран настроек построен на [CodeStar Framework](https://github.com/Codestar/codestar-framework). Фреймворк ищется и подключается автоматически, в таком порядке:

1. **Уже загружен** — например, активен отдельный плагин `codestar-framework` (в этом репозитории он идёт submodule'ом в `wp-content/plugins/codestar-framework/`).
2. **Копия в плагине** — `wp-addon-plugin/lib/codestar-framework/` (каталог создаётся установщиком, в git не входит).
3. **Автоматическая установка** — при первой загрузке админки, и далее не чаще раза в час, плагин скачивает закреплённый релиз `2.3.1` с GitHub в `lib/`. Нужен пользователь с capability `install_plugins` и права записи в `wp-content/plugins/`.
4. **Установка вручную** — кнопка **Install CodeStar Framework now** в админском уведомлении либо отдельный плагин `codestar-framework`.

Если ни один источник не сработал, в админке показывается уведомление с текстом ошибки загрузки или записи файлов, а экран настроек скрыт до тех пор, пока `CSF` не станет доступен.
