<p align="center">
    <a href="https://github.com/rwsite/wp-addon-plugin"><img alt="GitHub release" src="https://img.shields.io/github/release/rwsite/wp-addon-plugin.svg?style=for-the-badge"></a>
    <a href="https://php.net"><img alt="PHP Version" src="https://img.shields.io/badge/PHP-7.4+-blue.svg?style=for-the-badge&logo=php"></a>
    <a href="https://wordpress.org"><img alt="WordPress Version" src="https://img.shields.io/badge/WordPress-6.6+-blue.svg?style=for-the-badge&logo=wordpress"></a>
    <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge"></a>
</p>

<p align="center">
    <strong>Transforms your standard WordPress installation into an excellent, optimized website with comprehensive performance, security, and usability enhancements.</strong>
</p>

---

## 📋 Overview

WP Addon Plugin is a comprehensive WordPress optimization solution that combines multiple features into a single plugin. The plugin uses a modular architecture with PSR-4 autoloading, allowing for easy functionality expansion.

## ✨ Implemented Modules and Features

### 🚀 Performance and Optimization
- **PerformanceTweaks**: 36 different performance optimizations (header cleanup, revision limits, HTTP request blocking, heartbeat disable, jQuery migrate removal, etc.)
- **AssetMinification**: Minification and concatenation of CSS and JavaScript files with smart caching
- **LazyLoading**: Lazy loading of images, iframes, and videos with blur placeholders
- **PageCache**: Full-fledged file-based page caching to replace plugins like W3 Total Cache or WP Rocket
- **MediaCleanup**: Cleanup of unused media files

### 🔒 Security and Maintenance
- **MaintenanceMode**: Maintenance mode with custom page
- **DisableAutoUpdate**: Management of WordPress automatic updates
- **DisableComments**: Complete disabling of comments
- **Debug**: Debugging tools and error handling

### 📝 Content and Editor
- **TinyMCE Extensions**: Additional plugins for the editor (buttons, styles, media insertion)
- **Redirects**: Redirect management
- **Shortcodes**: Custom shortcodes for content
- **DuplicatePost**: Post duplication functionality
- **ShowThumbnail**: Display post thumbnails in admin
- **ShowID**: Show post IDs in admin lists
- **PostExcerpt**: Excerpt length management
- **CategoriesFilter**: Category exclusion from frontend
- **FixGUID**: GUID fixing for posts

### 🔧 Administration
- **Dashboard Widgets**: Admin dashboard widgets
- **SEO Modules**: Basic SEO optimizations
- **User Management**: User-related functions
- **Term Management**: Taxonomy term functions
- **Widget Enhancements**: Additional widget features

### 📞 Contact Form 7 Integrations
- **DateTimePicker**: Enhanced date/time picker for Contact Form 7
- **CodeEditor**: Code editor integration for forms
- **ContactForm7**: Core Contact Form 7 enhancements

### 📢 Advertising Management
- **Advertising**: Comprehensive advertising management in posts and pages

## 📁 Plugin Structure

```
wp-addon-plugin/
├── src/                          # Core code (PSR-4)
│   ├── Autoloader.php           # Class autoloader
│   ├── Core/                    # Plugin core
│   ├── Interfaces/              # Module interfaces
│   ├── Services/                # Services
│   ├── Traits/                  # Traits for modules
│   └── Config/                  # Configuration
├── functions/                   # Functionality modules
│   ├── PerformanceTweaks.php   # Performance optimizations
│   ├── AssetMinification.php    # Asset minification
│   ├── PageCache.php            # Page caching
│   ├── MaintenanceMode.php      # Maintenance mode
│   ├── DisableComments.php      # Comment disabling
│   ├── TinyMCE/                 # TinyMCE extensions
│   ├── shortcodes/              # Shortcodes
│   ├── seo/                     # SEO functions
│   ├── cf7/                     # Contact Form 7 integrations
│   ├── posts/                   # Post-related functions
│   ├── comments/                # Comment functions
│   ├── dashboard-widget/        # Dashboard widgets
│   ├── users/                   # User management
│   ├── terms/                   # Term management
│   ├── widgets/                 # Widget enhancements
│   ├── vc/                      # Visual Composer integrations
│   └── ...                      # Other modules
├── assets/                      # Static resources
│   ├── css/                     # Styles (SCSS with compilation)
│   ├── js/                      # JavaScript (with minification)
│   ├── images/                  # Images
│   └── gulpfile.js              # Build system
├── languages/                   # Translations
├── tests/                       # Unit tests (Pest)
└── composer.json                # PHP dependencies
```

## ⚙️ Plugin Settings

The plugin provides a detailed settings panel in **WordPress Admin > Settings > WP Addon** with sections:

### General Settings
- Maintenance mode
- Show custom fields in admin
- Disable auto-updates

### Posts and Pages
- Show post IDs and thumbnails
- Post duplication
- Remove category from URL
- Excerpt length limit
- Exclude categories from frontend
- Advertising settings

### Comments
- Disable comments
- Remove nofollow from links
- Remove website field from comments

### Editor
- Disable Gutenberg
- TinyMCE settings

### Contact Form 7
- DateTime picker settings
- Form enhancements

### Performance
- Asset minification
- Page caching
- Performance optimizations (36 settings)
- Lazy loading of media files

## 📦 Installation

1. Download the plugin
2. Upload to `wp-content/plugins/`
3. Activate through WordPress admin
4. Configure settings in the **WP Addon** menu

## 🛠️ Asset Building

### Asset Structure
- **CSS**: SCSS compilation with minification to `assets/css/min/`
- **JS**: JavaScript minification to `assets/js/min/`
- **Images**: Optimization to WebP/SVG

### Building
```bash
cd assets/
npm install
npm run build    # Build for production
npm run dev      # Development mode with watch
```

### Asset Loading
- Main CSS: `wp-addon.min.css` (automatic loading)
- TinyMCE plugins: Conditional loading
- Icons: FontAwesome from CDN for editor

## ✅ Requirements

- WordPress 6.6+
- PHP 8.2+

## 🧪 Testing

Unit testing with Pest framework:

```bash
composer install
composer test  # Run tests
composer test:coverage  # With coverage
```

## 🤝 Module Development

The plugin supports modular architecture. To create a new module:

1. Create a file in `functions/` implementing `ModuleInterface`
2. Use `HookTrait` to register hooks
3. The module will be automatically loaded

Detailed documentation: [MODULES_GUIDE.md](MODULES_GUIDE.md)

## 📄 License

See LICENSE file for details.

## 📚 Documentation

- **[Testing](tests/README.md)** - Complete testing and TDD guide
- **[CI/CD Pipeline](CI.md)** - Detailed GitHub Actions workflow description
- **[Settings](SETTINGS.md)** - Plugin configuration and modules
- **[Module Development](MODULES_GUIDE.md)** - Guide for creating new modules

## 🆘 Support

- [Settings Guide](SETTINGS.md)
- [Module Development](MODULES_GUIDE.md)

---

## 🇷🇺 Русский перевод / Russian Translation

<p align="center">
    <a href="https://github.com/rwsite/wp-addon-plugin"><img alt="GitHub release" src="https://img.shields.io/github/release/rwsite/wp-addon-plugin.svg?style=for-the-badge"></a>
    <a href="https://php.net"><img alt="PHP Version" src="https://img.shields.io/badge/PHP-7.4+-blue.svg?style=for-the-badge&logo=php"></a>
    <a href="https://wordpress.org"><img alt="WordPress Version" src="https://img.shields.io/badge/WordPress-6.6+-blue.svg?style=for-the-badge&logo=wordpress"></a>
    <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge"></a>
</p>

<p align="center">
    <strong>Трансформирует стандартную установку WordPress в отличный, оптимизированный сайт с комплексными улучшениями производительности, безопасности и удобства использования.</strong>
</p>

---

## 📋 Обзор плагина

WP Addon Plugin - это комплексное решение для оптимизации WordPress, объединяющее множество функций в одном плагине. Плагин использует модульную архитектуру с PSR-4 автозагрузкой, что позволяет легко расширять функциональность.

## ✨ Реализованные модули и функции

### 🚀 Производительность и оптимизация
- **PerformanceTweaks**: 36 различных оптимизаций производительности (очистка заголовков, ограничение ревизий, блокировка HTTP-запросов, отключение heartbeat, удаление jQuery migrate и др.)
- **AssetMinification**: Минификация и объединение CSS и JavaScript файлов с умным кэшированием
- **LazyLoading**: Ленивая загрузка изображений, iframe и видео с blur placeholder'ами
- **PageCache**: Полноценный файловый кэш страниц для замены плагинов типа W3 Total Cache или WP Rocket
- **MediaCleanup**: Очистка неиспользуемых медиафайлов

### 🔒 Безопасность и обслуживание
- **MaintenanceMode**: Режим обслуживания с кастомной страницей
- **DisableAutoUpdate**: Управление автоматическими обновлениями WordPress
- **DisableComments**: Полное отключение комментариев
- **Debug**: Инструменты отладки и обработки ошибок

### 📝 Контент и редактор
- **TinyMCE расширения**: Дополнительные плагины для редактора (кнопки, стили, вставка медиа)
- **Redirects**: Управление редиректами
- **Shortcodes**: Пользовательские шорткоды для контента
- **DuplicatePost**: Функциональность дублирования постов
- **ShowThumbnail**: Отображение миниатюр постов в админке
- **ShowID**: Показ ID постов в списках админки
- **PostExcerpt**: Управление длиной отрывков
- **CategoriesFilter**: Исключение категорий из фронтенда
- **FixGUID**: Исправление GUID для постов

### 🔧 Администрирование
- **Dashboard widgets**: Виджеты панели администратора
- **SEO модули**: Базовые оптимизации SEO
- **Управление пользователями**: Функции, связанные с пользователями
- **Управление терминами**: Функции таксономий
- **Улучшения виджетов**: Дополнительные функции виджетов

### 📞 Интеграции Contact Form 7
- **DateTimePicker**: Улучшенный выбор даты/времени для Contact Form 7
- **CodeEditor**: Интеграция редактора кода для форм
- **ContactForm7**: Основные улучшения Contact Form 7

### 📢 Управление рекламой
- **Advertising**: Комплексное управление рекламой в постах и страницах

## 📁 Структура плагина

```
wp-addon-plugin/
├── src/                          # Основной код (PSR-4)
│   ├── Autoloader.php           # Автозагрузчик классов
│   ├── Core/                    # Ядро плагина
│   ├── Interfaces/              # Интерфейсы модулей
│   ├── Services/                # Сервисы
│   ├── Traits/                  # Трейты для модулей
│   └── Config/                  # Конфигурация
├── functions/                   # Модули функциональности
│   ├── PerformanceTweaks.php   # Оптимизации производительности
│   ├── AssetMinification.php    # Минификация ресурсов
│   ├── PageCache.php            # Кэш страниц
│   ├── MaintenanceMode.php      # Режим обслуживания
│   ├── DisableComments.php      # Отключение комментариев
│   ├── TinyMCE/                 # Расширения TinyMCE
│   ├── shortcodes/              # Шорткоды
│   ├── seo/                     # SEO функции
│   ├── cf7/                     # Интеграции Contact Form 7
│   ├── posts/                   # Функции, связанные с постами
│   ├── comments/                # Функции комментариев
│   ├── dashboard-widget/        # Виджеты дашборда
│   ├── users/                   # Управление пользователями
│   ├── terms/                   # Управление терминами
│   ├── widgets/                 # Улучшения виджетов
│   ├── vc/                      # Интеграции Visual Composer
│   └── ...                      # Другие модули
├── assets/                      # Статические ресурсы
│   ├── css/                     # Стили (SCSS с компиляцией)
│   ├── js/                      # JavaScript (с минификацией)
│   ├── images/                  # Изображения
│   └── gulpfile.js              # Система сборки
├── languages/                   # Переводы
├── tests/                       # Модульные тесты (Pest)
└── composer.json                # Зависимости PHP
```

## ⚙️ Настройки плагина

Плагин предоставляет подробную панель настроек в **WordPress Admin > Settings > WP Addon** с разделами:

### Общие настройки
- Режим обслуживания
- Показ кастомных полей в админке
- Отключение автообновлений

### Посты и страницы
- Показ ID постов и миниатюр
- Дублирование постов
- Удаление категории из URL
- Ограничение длины отрывков
- Исключение категорий из фронтенда
- Настройки рекламы

### Комментарии
- Отключение комментариев
- Удаление nofollow с ссылок
- Удаление поля сайта в комментариях

### Редактор
- Отключение Gutenberg
- TinyMCE настройки

### Contact Form 7
- Настройки DateTime picker
- Улучшения форм

### Производительность
- Минификация ресурсов
- Кэширование страниц
- Оптимизации производительности (36 настроек)
- Ленивая загрузка медиафайлов

## 📦 Установка

1. Скачайте плагин
2. Загрузите в `wp-content/plugins/`
3. Активируйте через админку WordPress
4. Настройте параметры в меню **WP Addon**

## 🛠️ Сборка ресурсов

### Структура ресурсов
- **CSS**: Компиляция SCSS с минификацией в `assets/css/min/`
- **JS**: Минификация JavaScript в `assets/js/min/`
- **Изображения**: Оптимизация в WebP/SVG

### Сборка
```bash
cd assets/
npm install
npm run build    # Сборка для продакшена
npm run dev      # Режим разработки с watch
```

### Загрузка ресурсов
- Основной CSS: `wp-addon.min.css` (автоматическая загрузка)
- TinyMCE плагины: Условная загрузка
- Иконки: FontAwesome из CDN для редактора

## ✅ Требования

- WordPress 6.6+
- PHP 8.2+

## 🧪 Тестирование

Модульное тестирование с фреймворком Pest:

```bash
composer install
composer test  # Запуск тестов
composer test:coverage  # С покрытием
```

## 🤝 Разработка модулей

Плагин поддерживает модульную архитектуру. Для создания нового модуля:

1. Создайте файл в `functions/` реализующий `ModuleInterface`
2. Используйте `HookTrait` для регистрации хуков
3. Модуль автоматически загрузится

Подробная документация: [MODULES_GUIDE.md](MODULES_GUIDE.md)

## 📄 Лицензия

Смотрите файл LICENSE для деталей.

## 📚 Документация

- **[Тестирование](tests/README.md)** - Полное руководство по тестированию и TDD
- **[CI/CD Pipeline](CI.md)** - Подробное описание GitHub Actions workflow
- **[Настройки](SETTINGS.md)** - Конфигурация и модули плагина
- **[Разработка модулей](MODULES_GUIDE.md)** - Руководство по созданию новых модулей

## 🆘 Поддержка

- [Руководство по настройкам](SETTINGS.md)
- [Разработка модулей](MODULES_GUIDE.md)
