# AGENTS.md

## Контекст
- Это главный функциональный плагин проекта.
- Namespace: `WpAddon\`.
- Точка входа: `wp-addon-plugin.php`.
- Основная инициализация: `src/Core/Plugin.php`.

## Как искать код
- OOP-часть и сервисы: `src/`.
- Настройки CodeStar: `src/Config/`.
- Модули и procedural-функции: `functions/`.
- Тесты: `tests/`.
- Документация по модульной системе: `MODULES_GUIDE.md`, `MODULE_DEVELOPMENT_GUIDE.md`, `SETTINGS.md`.

## Ключевая архитектура
- `Plugin::loadModules()` автоматически подключает `functions/*.php` и `functions/*/*.php`.
- Классы, реализующие `WpAddon\Interfaces\ModuleInterface`, инстанцируются и вызывают `init()` автоматически.
- Часть модулей создается с зависимостями через специальные ветки в `Plugin::loadModules()`; перед новым конструктором проверь эту логику.
- `ControllerWP` включает функции по именам настроек через `add_action('init', ...)`, поэтому имя опции и имя функции часто связаны напрямую.

## Практические правила
- Не добавляй ручные `require_once` для файлов из `functions/`, если они уже должны грузиться модульной системой.
- Если модуль должен управляться настройкой, проверь `src/Config/*.php` и фактическое имя callback-функции.
- Для SEO и маршрутов сначала смотри `functions/seo/`.
- Для админских улучшений чаще всего нужные файлы в `functions/posts/`, `functions/comments/`, `functions/users/`, `functions/widgets/`.

## Важный кейс
- Иерархические URL тегов реализованы в `functions/seo/HierarchicalTagsRewrite.php`.
- Rewrite rule должна вести на конечный slug тега, иначе WordPress вернет пустую выборку и 404.

## Проверка изменений
- Локальные тесты плагина: `composer test` из `wp-content/plugins/wp-addon-plugin/`.
- При изменениях rewrite/permalink логики нужен flush rewrite rules в WordPress.
