# WP Excellence Addon

[![Version](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://github.com/rwsite/wp-addon-plugin/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net/)
[![Tests](https://img.shields.io/badge/tests-180%20passed-green.svg)](https://github.com/rwsite/wp-addon-plugin/actions)

Transforms a standard WordPress site into a faster, safer, and easier-to-manage installation. The plugin bundles **WP Tweaker** (48 performance and security tweaks), **Redirects**, **Cookie Banner**, **Markdown editor**, **Plugin catalog**, and admin utilities in one place.

## Requirements

| Component | Minimum | Tested |
|-----------|---------|--------|
| **WordPress** | 6.6 | 6.6 – 6.8 |
| **PHP** | 8.2 | 8.2, 8.3, 8.4 |

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

## Development

```bash
composer install
composer test          # 180 unit tests
composer test:coverage
composer analyse       # PHPStan level 8
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
