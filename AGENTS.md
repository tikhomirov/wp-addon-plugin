# AGENTS.md

## Context
- Main functional plugin for the project.
- Namespace: `WpAddon\`.
- Entry point: `wp-addon-plugin.php`.
- Core bootstrap: `src/Core/Plugin.php`.

## Where to look
- OOP and services: `src/`.
- CodeStar settings: `src/Config/`.
- Modules and procedural code: `functions/`.
- Tests: `tests/`.
- Module docs: `MODULES_GUIDE.md`, `MODULE_DEVELOPMENT_GUIDE.md`, `SETTINGS.md`.

## Architecture
- `Plugin::loadModules()` auto-loads `functions/*.php` and `functions/*/*.php`.
- Classes implementing `WpAddon\Interfaces\ModuleInterface` are constructed and `init()` is called automatically.
- Some modules are constructed with dependencies in `Plugin::loadModules()`; check that logic before adding constructors.
- `ControllerWP` enables functions by option name via `add_action('init', ...)`, so option names often match callback function names.

## Practical rules
- Do not add manual `require_once` for `functions/` files that already load via the module system.
- If a module is option-driven, check `src/Config/*.php` and the actual callback function name.
- SEO and routing: start in `functions/seo/`.
- Admin tweaks: `functions/posts/`, `functions/comments/`, `functions/users/`, `functions/widgets/`.

## Important case
- Hierarchical tag URLs: `functions/seo/HierarchicalTagsRewrite.php`.
- The rewrite rule must map to the leaf tag slug, or WordPress returns an empty query and 404.

## Verifying changes
- Plugin tests: `composer test` from `wp-content/plugins/wp-addon-plugin/`.
- After rewrite/permalink changes, flush rewrite rules in WordPress.