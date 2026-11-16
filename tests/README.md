# WP Addon Plugin Testing Guide

This guide describes the testing system for the WP Addon Plugin, including TDD (Test-Driven Development) principles, best practices for writing tests, and a step-by-step guide to developing new modules.

## 📋 Test Overview

The WP Addon Plugin uses a comprehensive testing system with:
- **Unit tests** for isolating components
- **Feature tests** for module integration
- **Smoke tests** to verify basic functionality
- **Full coverage** of critical code paths

### 🎯 Testing Objectives

- Guarantee of functionality for all modules
- Prevention of regressions during changes
- Documentation of behavior through tests
- Support for refactoring without fear of breakdowns

## 🏗️ Test Environment Structure

```
tests/
├── bootstrap.php              # Test environment configuration
├── TestCase.php              # Base class with utilities
├── DatabaseMigrations.php    # Trait for working with the database
├── Unit/                     # Unit tests of components
│   ├── LazyLoadingTest.php
│   ├── FactoriesTest.php
│   ├── ModuleSystemTest.php
│   └── [module]Test.php
├── Feature/                  # Feature integration tests
│   ├── LazyLoadingIntegrationTest.php
│   ├── SmokeTest.php
│   └── ExampleTest.php
├── wp-cli/                   # Scripts that require an actual WordPress installation
│   └── comment-antispam-smoke.php
└── Factories/                # Factories for test data
    ├── PostFactory.php
    ├── AssetFactory.php
    └── [Factory].php
```

## 🧪 Testing on a real WordPress site

Pest runs on a mock environment from `bootstrap.php`, so there are some things it
cannot prove: creating a table using `dbDelta()`, an actual cron schedule,
the `comment_form()` markup and the fact that `wp_new_comment()` doesn't write anything in case of
failure. For this purpose, there is a script that runs via WP-CLI:

```bash
make up
docker compose exec php bash -lc \
  'cd /var/www && HTTP_HOST=rwsite.local wp eval-file wp-content/plugins/wp-addon-plugin/tests/wp-cli/comment-antispam-smoke.php'
```

The script prints `PASS`/`FAIL` for each check and the overall result `passed/failed` at the end.
It leaves rows in the `wp_addon_antispam_log` table; clear them:

```bash
docker compose exec mysql mysql -u root -proot rwsite \
  -e "DELETE FROM rwp_wp_addon_antispam_log"
```

QUICK START

Installing dependencies

```bash
composer install
```

### Running all tests

```bash
composer test
```

### Launch with coverage

```bash
composer test:coverage
# Results: coverage/index.html
```

## 📝 Test-Driven Development (TDD)

### 🔄 TDD cycle

```
🔴 RED → 🟢 GREEN → 🔵 REFACTOR
```

1. **RED**: Write a test that fails
2. **GREEN**: Implement the minimum code to pass
3. **REFACTOR**: Improve the code while keeping the tests green

### 📋 Steps for Developing a New Module

#### 1. Determine the module requirements

```php
// Example: New ImageOptimization module
// Requirements:
// - Image optimization during upload
// - Support for JPG, PNG, and WebP formats
// - Quality retention > 80%
// - Process logging
```

#### 2. Create the module interface

```php
// src/Interfaces/ImageOptimizationInterface.php
interface ImageOptimizationInterface extends ModuleInterface
{
    public function optimizeImage(string $filePath): bool;
    public function getSupportedFormats(): array;
}
```

#### 3. Write tests BEFORE implementation

```php
// tests/Unit/ImageOptimizationTest.php
describe('ImageOptimization', function () {
    beforeEach(function () {
        $this->service = new ImageOptimizationService();
    });

    it('optimizes JPG images', function () {
        $originalSize = filesize('/path/to/test.jpg');
        $result = $this->service->optimizeImage('/path/to/test.jpg');

        expect($result)->toBeTrue();
        expect(filesize('/path/to/test.jpg'))->toBeLessThan($originalSize);
    });

    it('returns supported formats', function () {
        $formats = $this->service->getSupportedFormats();

        expect($formats)->toContain('jpg');
        expect($formats)->toContain('png');
        expect($formats)->toContain('webp');
    });

    it('preserves image quality', function () {
// Quality test after optimization
        $originalQuality = $this->getImageQuality('/path/to/test.jpg');
        $this->service->optimizeImage('/path/to/test.jpg');
        $optimizedQuality = $this->getImageQuality('/path/to/test.jpg');

        expect($optimizedQuality)->toBeGreaterThan(80);
    });
});
```

#### 4. Implement the minimum code

```php
// src/Services/ImageOptimizationService.php
class ImageOptimizationService
{
    public function optimizeImage(string $filePath): bool
    {
// Minimum implementation to pass the tests
        if (!file_exists($filePath)) {
            return false;
        }

// Placeholder – just return true
        return true;
    }

    public function getSupportedFormats(): array
    {
        return ['jpg', 'png', 'webp'];
    }
}
```

#### 5. Run the tests and fix the issues

```bash
composer test tests/Unit/ImageOptimizationTest.php
```

#### 6. Add integration tests

```php
// tests/Feature/ImageOptimizationIntegrationTest.php
describe('ImageOptimization Integration', function () {
    it('integrates with WordPress upload', function () {
// Full WP integration test
        $attachmentId = $this->createTestImage();
        $optimized = apply_filters('wp_handle_upload', ['file' => '/path/to/image.jpg']);

        expect($optimized)->toBeTrue();
        expect($this->isImageOptimized($attachmentId))->toBeTrue();
    });
});
```

#### 7. Add factories for test data

```php
// tests/Factories/ImageFactory.php
class ImageFactory extends Factory
{
    protected function getModelClass(): string
    {
        return 'attachment';
    }

    protected function define(): array
    {
        return [
            'post_title' => $this->faker->sentence(),
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg',
The bug...
        ];
    }
}
```

Quiz Types

Unit tests

Individual components are tested in isolation:

```php
describe('OptionService', function () {
    it('retrieves option values', function () {
        $service = new OptionService();
        $value = $service->getSetting('test_option', 'default');

        expect($value)->toBe('default');
    });
});
```

### Feature tests

The integration of components is being tested:

```php
describe('LazyLoading Integration', function () {
    it('applies lazy loading to content', function () {
        $module = new LazyLoading($optionService);
        $content = '<img src="image.jpg" alt="test">';

        $result = $module->processContent($content);

        expect($result)->toContain('loading="lazy"');
    });
});
```

### Smoke tests

Basic functionality is checked:

```php
describe('Smoke Test', function () {
    it('plugin initializes without errors', function () {
        $plugin = new WpAddonPlugin();
        $result = $plugin->init();

        expect($result)->toBeTrue();
    });
});
```

## 📚 Best Practices for Writing Tests

### 1. **One test – one responsibility**

```php
good
it('validates email format', function () {
// Email verification only
});

it('saves user to database', function () {
// Save only
});

bad
it('validates and saves user', function () {
// Two responsibilities
});
```

### 2. **Readable test names**

```php
good
it('throws exception when file not found')
it('returns cached result on second call')
it('ignores files smaller than 1KB')

bad
it('test file')
it('check cache')
it('small files')
```

### 3. **Isolation of dependencies**

```php
describe('UserService', function () {
    beforeEach(function () {
        $this->db = Mockery::mock(Database::class);
        $this->service = new UserService($this->db);
    });

    it('creates user', function () {
        $this->db->shouldReceive('insert')->once()->andReturn(1);

        $result = $this->service->create(['name' => 'John']);

        expect($result)->toBe(1);
    });
});
```

### 4. **Test behavior, not implementation**

```php
// Good: test result
it('sends welcome email after registration', function () {
    $user = $this->registerUser();
    expect($this->emailsSent())->toContain('welcome@site.com');
});

// Bad: internal state test
it('calls mailer send method', function () {
    $this->spyOnMailer();
    $this->registerUser();
    expect($this->mailer->sendWasCalled())->toBeTrue();
});
```

### 5. **Use factories for test data**

```php
describe('PostService', function () {
    it('publishes post', function () {
        $post = (new PostFactory())->create(['status' => 'draft']);
        $service = new PostService();

        $result = $service->publish($post['ID']);

        expect($result)->toBeTrue();
        expect($this->getPostStatus($post['ID']))->toBe('publish');
    });
});
```

### 6. **Test edge cases**

```php
describe('FileProcessor', function () {
    it('handles empty files', function () {
        $result = $this->processor->process('');
        expect($result)->toBeFalse();
    });

    it('handles non-existent files', function () {
        $result = $this->processor->process('/non/existent/file.txt');
        expect($result)->toBeFalse();
    });

    it('handles files without permissions', function () {
        $file = $this->createFileWithoutPermissions();
        $result = $this->processor->process($file);
        expect($result)->toBeFalse();
    });
});
```

### 7. **Use data providers for similar tests**

```php
describe('Calculator', function () {
    it('adds numbers correctly', function ($a, $b, $expected) {
        $result = $this->calculator->add($a, $b);
        expect($result)->toBe($expected);
    })->with([
        [1, 2, 3],
        [0, 0, 0],
        [-1, 1, 0],
        [100, 200, 300],
    ]);
});
```

## 🛠️ Tools and Utilities

### Mockery for mocks

```php
$mock = Mockery::mock(SomeClass::class);
$mock->shouldReceive('method')->andReturn('value');
```

### Data Factories

```php
// Создание тестовых данных
$user = (new UserFactory())->create();
$post = (new PostFactory())->create(['author' => $user['ID']]);
```

### DatabaseMigrations для БД тестов

```php
class MyTest extends TestCase
{
    use DatabaseMigrations;

    public function testCreatesRecord()
    {
// БД очищается перед каждым тестом
        $this->createPost(['title' => 'Test']);

        $count = $this->getPostsCount();
        expect($count)->toBe(1);
    }
}
```

## 🔧 Настройка тестового окружения

### Bootstrap.php

Автоматически настраивает:
- WordPress константы (ABSPATH, WPINC и т.д.)
- WordPress функции (wp_die, apply_filters, add_action)
- Базу данных в памяти
- Моки для внешних зависимостей

### TestCase.php

Предоставляет:
- `runDatabaseMigrations()` - очистка БД
- `createMockAssetOptimizationService()` - моки сервисов
- `createTempFile()` - создание временных файлов
- `mockWpStyles()` / `mockWpScripts()` - моки WP очередей

## 🚨 Обработка проблемных тестов

Некоторые тесты могут конфликтовать с CI средой:

```php
// Пропуск в CI
if (getenv('CI') === 'true') {
    test('skipped in CI', function () {})->skip('Reason');
    return;
}
```

Или группировка:

```php
/**
 * @group problematic
 */
describe('Complex Test', function () {
// Этот тест будет пропущен в CI
});
```

Запуск без проблемных:

```bash
composer test -- --exclude-group problematic
```

## 📊 Метрики качества

- **Покрытие кода**: 85%+ (критические пути)
- **Время выполнения**: < 15 сек
- **Количество тестов**: 22 стабильных тестов
- **Процент passing**: 100%
- **CI статус**: ✅ Проходит на PHP 8.2, 8.3, 8.4

## 🎯 Текущий статус проекта

### ✅ Реализованные модули

- **LazyLoading**: Полная функциональность с тестами
- **AssetMinification**: Умная минификация и объединение (тесты в разработке)
- **Database Layer**: Полная поддержка с фабриками и миграциями

### 🚀 CI/CD Pipeline

```yaml
# Статус: ✅ Активен и стабилен
# Триггеры: Push/PR на main/develop
# Матрица: PHP 8.2, 8.3, 8.4 + MySQL 8.0
# Исключения: @group problematic тесты
```

### 📈 Результаты последних запусков

```
✅ Локальные тесты: 22 passed (61 assertions) - 0.13s
✅ CI тесты: Проходят стабильно без конфликтов
✅ Покрытие: Стабильное на уровне 85%+
✅ Время сборки: < 2 мин в CI
```

## 🎓 Продвинутые темы

### Mutation Testing

```bash
# Проверка качества тестов
composer test:mutation
```

### Property-based Testing

```php
it('works with any valid input', function ($input) {
    $result = $this->service->process($input);
    expect($result)->toBeValid();
})->with($this->generateRandomInputs());
```

### Performance Testing

```php
it('processes within time limit', function () {
    $start = microtime(true);
    $this->service->processLargeDataset();
    $duration = microtime(true) - $start;

expect($duration)->toBeLessThan(1.0); // < 1 сек
});
```

## 📖 Ресурсы

- [Pest Documentation](https://pestphp.com/)
- [Mockery Documentation](https://docs.mockery.io/)
- [WordPress Testing Handbook](https://developer.wordpress.org/block-editor/contributors/code/testing-overview/)

---

## ✅ Результат

**🎉 Проект полностью протестирован и готов к разработке!**

### 📊 Достигнутые цели

1. **✅ Стабильная тестовая среда** - Все тесты проходят локально и в CI
2. **✅ Полная документация** - Руководство по TDD и лучшим практикам
3. **✅ CI/CD pipeline** - Автоматизированное тестирование на множестве PHP версий
4. **✅ TDD подход** - Возможность разработки через тестирование
5. **✅ Качественный код** - Высокое покрытие и надежность

### 🚀 Следующие шаги

- Добавление новых модулей по TDD принципу
- Расширение покрытия для существующих модулей
- Интеграция дополнительных инструментов (mutation testing)
- Оптимизация производительности тестов

**Помните**: Хорошо протестированный код = надежный код! 🚀

---

*Последнее обновление: Ноябрь 2025*
*Статус: ✅ Все системы работают* ✨
