# GitHub Actions CI/CD Pipeline - WP Addon Plugin

## 📋 Обзор

Этот документ описывает настройку и работу GitHub Actions CI/CD pipeline для WP Addon Plugin. Здесь зафиксированы все шаги, зависимости и конфигурации, необходимые для восстановления работы CI в случае поломки.

## 🎯 Цели CI Pipeline

- **Автоматизированное тестирование** на множестве версий PHP
- **Проверка стиля кода** через Laravel Pint с Laravel preset
- **Статический анализ** через PHPStan и WordPress stubs
- **Проверка совместимости** с WordPress test suite
- **Анализ покрытия кода** и загрузка отчетов
- **Предотвращение регрессий** перед merge в main/develop

## 📁 Структура файлов

```
.github/
└── workflows/
    └── test.yml          # Основной CI workflow
```

## ⚙️ Конфигурация Workflow

### Основные параметры

```yaml
name: Tests
on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
```

### Services (Базы данных)

```yaml
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
    ports:
      - 3306:3306
    options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

## 🚀 Пошаговое выполнение CI

### Шаг 1: Checkout кода

```yaml
- uses: actions/checkout@v4
```

**Что делает:** Клонирует репозиторий в runner.

**Требования:** Доступ к репозиторию.

### Шаг 2: Настройка PHP

```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: ${{ matrix.php }}
    extensions: pdo, pdo_mysql, mbstring, intl, zip
    coverage: xdebug
    tools: composer:v2
```

**Что делает:**
- Устанавливает указанную версию PHP
- Включает необходимые расширения: `pdo`, `pdo_mysql`, `mbstring`, `intl`, `zip`
- Устанавливает Xdebug для покрытия кода
- Устанавливает Composer v2

**Требования:**
- shivammathur/setup-php@v2 action
- PHP версии 8.2, 8.3, или 8.4

### Шаг 3: Кэширование Composer зависимостей

```yaml
- name: Cache Composer dependencies
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ matrix.php }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: |
      ${{ runner.os }}-php-${{ matrix.php }}-composer-
```

**Что делает:**
- Кэширует папку `vendor` для ускорения сборки
- Ключ кэша основан на OS, PHP версии и hash composer.lock
- Восстанавливает кэш если точное совпадение не найдено

**Требования:** actions/cache@v3

### Шаг 4: Установка Composer зависимостей

```yaml
- name: Install Composer dependencies
  run: composer install --no-progress --prefer-dist --optimize-autoloader
```

**Что делает:**
- Устанавливает PHP зависимости из composer.json
- Использует дистрибутивы вместо исходников (--prefer-dist)
- Оптимизирует автолоадер для production

**Требования:**
- composer.json и composer.lock в корне проекта
- Доступ к Packagist

### Шаг 5: Настройка WordPress test environment

```yaml
- name: Setup WordPress test environment
  run: |
    git clone --depth=1 https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop
    cd /tmp/wordpress-develop
    cp wp-tests-config-sample.php wp-tests-config.php
    sed -i "s/youremptytestdbnamehere/wordpress_test/" wp-tests-config.php
    sed -i "s/yourusernamehere/wordpress/" wp-tests-config.php
    sed -i "s/yourpasswordhere/wordpress/" wp-tests-config.php
    sed -i "s|localhost|127.0.0.1|" wp-tests-config.php
    # Add proper table prefix and other settings
    sed -i "s/\$table_prefix  = 'wptests_';/\$table_prefix  = 'wp_';/" wp-tests-config.php
    echo "define('WP_DEBUG', true);" >> wp-tests-config.php
    echo "define('WP_DEBUG_LOG', false);" >> wp-tests-config.php
    echo "define('WP_DEBUG_DISPLAY', true);" >> wp-tests-config.php
```

**Что делает:**
1. Клонирует WordPress develop репозиторий (--depth=1 для скорости)
2. Копирует sample конфиг в рабочий
3. Заменяет placeholders на реальные значения БД
4. Изменяет хост на 127.0.0.1 (для Docker networking)
5. Устанавливает префикс таблиц на 'wp_'
6. Добавляет debug настройки

**Требования:**
- Доступ к github.com/WordPress/wordpress-develop.git
- MySQL сервис запущен и доступен

### Шаг 6: Установка переменной окружения

```yaml
- name: Set WP_TESTS_DIR environment variable
  run: echo "WP_TESTS_DIR=/tmp/wordpress-develop" >> $GITHUB_ENV
```

**Что делает:** Устанавливает переменную окружения WP_TESTS_DIR для тестов.

**Требования:** $GITHUB_ENV доступен в GitHub Actions.

### Шаг 7: Создание тестовой базы данных

```yaml
- name: Create test database
  run: |
    mysql -h 127.0.0.1 -u root -proot -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
```

**Что делает:** Создает базу данных wordpress_test в MySQL.

**Требования:**
- MySQL сервис запущен на 127.0.0.1:3306
- Root пароль: 'root'
- Пользователь 'wordpress' с паролем 'wordpress' создан

### Шаг 8: Установка WordPress test suite

```yaml
- name: Install WordPress test suite
  run: |
    cd /tmp/wordpress-develop
    php tests/phpunit/includes/install.php wp-tests-config.php
```

**Что делает:**
- Запускает WordPress install script
- Создает все необходимые таблицы в БД
- Настраивает WordPress для тестирования

**Требования:**
- PHP с pdo_mysql расширением
- wp-tests-config.php правильно настроен
- База данных доступна

### Шаг 9: Запуск quality gate

```yaml
- name: Run quality checks
  run: composer quality
```

**Что делает:**
- Проверяет `composer.json`, форматирование Laravel Pint и PHPStan
- Запускает все тесты через Composer скрипт

**Требования:**
- composer.json с настроенным скриптом test
- Все зависимости установлены
- WP_TESTS_DIR настроена

### Локальные проверки качества

```bash
composer format        # исправить форматирование Laravel preset
composer format:check  # проверить форматирование без изменений
composer static-analysis
composer quality       # полный quality gate
```

### Шаг 10: Запуск тестов с покрытием (только PHP 8.2)

```yaml
- name: Run tests with coverage
  run: composer test:coverage
  if: matrix.php == '8.2'
```

**Что делает:**
- Запускает тесты с анализом покрытия кода
- Генерирует clover.xml отчет

**Требования:**
- Xdebug установлен и настроен
- composer.json с скриптом test:coverage

### Шаг 11: Загрузка отчетов покрытия (только PHP 8.2)

```yaml
- name: Upload coverage reports
  uses: codecov/codecov-action@v3
  if: matrix.php == '8.2'
  with:
    file: ./coverage/clover.xml
    fail_ci_if_error: false
```

**Что делает:**
- Загружает clover.xml в Codecov
- Не падает сборка при ошибке загрузки

**Требования:**
- codecov/codecov-action@v3
- CODECOV_TOKEN в secrets (опционально)

## 🔧 Диагностика и устранение неисправностей

### Проблема: "Could not open input file: bin/install.php"

**Симптомы:** Ошибка в шаге "Install WordPress test suite"

**Решение:**
- Проверить путь к install.php
- Должен быть: `tests/phpunit/includes/install.php`
- А НЕ: `bin/install.php` или `tests/bin/install.php`

### Проблема: "Failed opening required 'wp-tests-config.php'"

**Симптомы:** Ошибка require_once в install.php

**Решение:**
- Убедиться что wp-tests-config.php создан в предыдущем шаге
- Проверить что все sed команды выполнились корректно
- Проверить права на файл

### Проблема: "SQLSTATE[HY000] [2002] Connection refused"

**Симптомы:** Тесты не могут подключиться к MySQL

**Решение:**
- Проверить что MySQL сервис запущен
- Проверить health checks в services.mysql.options
- Убедиться что используется 127.0.0.1 вместо localhost

### Проблема: "Class 'Patchwork\...' not found"

**Симптомы:** Ошибки связанные с Patchwork в тестах

**Решение:**
- Проверить что Brain Monkey удален из composer.json
- Проверить bootstrap.php на предмет mock'ов Patchwork
- Запускать тесты с --exclude-group problematic

### Проблема: Тесты висят или таймаутятся

**Симптомы:** Сборка прерывается по таймауту

**Решение:**
- Проверить composer test скрипт на корректность
- Убедиться что все зависимости установлены
- Проверить что WP_TESTS_DIR правильно установлена

## 📊 Метрики и мониторинг

### Время выполнения
- **Ожидаемое время сборки:** 1.5-2 минуты
- **Критические шаги:** WordPress setup (30-45 сек), Tests (10-20 сек)

### Покрытие кода
- **Целевое покрытие:** > 80%
- **Отчеты:** clover.xml -> Codecov
- **Анализ:** Только на PHP 8.2 для оптимизации

## 🚀 Масштабирование и оптимизация

### Добавление новых PHP версий

```yaml
strategy:
  matrix:
    php: ['8.1', '8.2', '8.3', '8.4']
```

### Добавление других баз данных

```yaml
services:
  mysql:
    image: mysql:8.0
  postgres:
    image: postgres:13
    # ... конфигурация
```

### Параллельные jobs

```yaml
jobs:
  test:
    # ... existing config
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run PHPStan
        run: composer lint
```

## 🔒 Безопасность

### Secrets и переменные

- **CODECOV_TOKEN:** Для загрузки покрытия (опционально)
- **Другие секреты:** Не требуются для базовой работы

### Доступы

- **GitHub токены:** Используются автоматически
- **Внешние сервисы:** Codecov (опционально)

## 📝 Логи и отладка

### Просмотр логов

```bash
# В GitHub Actions UI
# Actions -> Workflow runs -> Выбрать run -> View logs
```

### Локальная отладка

```bash
# Запуск локально в Docker
docker run -it --rm \
  -v $(pwd):/app \
  -w /app \
  php:8.2-cli \
  bash

# В контейнере
composer install
# ... повторить шаги CI
```

## 🎯 Восстановление после поломки

### Шаг 1: Проверить workflow файл
- Убедиться что .github/workflows/test.yml существует
- Проверить синтаксис YAML

### Шаг 2: Проверить зависимости
- Все actions должны быть доступны (checkout@v4, cache@v3 и т.д.)
- Проверить версии actions на совместимость

### Шаг 3: Тестирование шагов
- Запустить каждый шаг вручную локально
- Проверить логи на ошибки

### Шаг 4: Проверка окружения
- Убедиться что MySQL сервис корректно настроен
- Проверить переменные окружения

### Шаг 5: Восстановление
- Если workflow сломан - восстановить из git истории
- Если внешние сервисы недоступны - найти альтернативы

---

## 📋 Чек-лист восстановления

- [ ] .github/workflows/test.yml существует
- [ ] Все actions доступны и актуальны
- [ ] MySQL сервис правильно настроен
- [ ] composer.json корректен
- [ ] tests/bootstrap.php настроен
- [ ] WordPress develop доступен для клонирования
- [ ] Codecov токен установлен (опционально)

**Последнее обновление:** Ноябрь 2025
**Статус:** ✅ Работает стабильно 🚀
