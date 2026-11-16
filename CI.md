# GitHub Actions CI/CD Pipeline - WP Addon Plugin

## SUMMARY ##

This document describes the setup and operation of the GitHub Actions CI/CD pipeline for the WP Addon Plugin. All the steps, dependencies, and configurations required to restore CI operation in the event of a failure are recorded here.

## 🎯 CI Pipeline Objectives

- **Automated testing** on multiple PHP versions
- **Code style check** via Laravel Pint with Laravel preset
- **Static analysis** via PHPStan and WordPress stubs
- **Compatibility check** with the WordPress test suite
- **Code coverage analysis** and report uploads
- **Regression prevention** before merging into main/develop

## 📁 File structure

```
.github/
└── workflows/
└── test.yml          # Main CI workflow
```

## ⚙️ Workflow Configuration

Specifications

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

### Services (Databases)

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

## 🚀 Step-by-step CI implementation

### Step 1: Code checkout

```yaml
- uses: actions/checkout@v4
```

**What it does:** Clones the repository to the runner.

**Requirements:** Access to the repository.

### Step 2: Configuring PHP

```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: ${{ matrix.php }}
    extensions: pdo, pdo_mysql, mbstring, intl, zip
    coverage: xdebug
    tools: composer:v2
```

**What it does:**
- Installs the specified PHP version
- Enables the required extensions: `pdo`, `pdo_mysql`, `mbstring`, `intl`, `zip`
- Installs Xdebug for code coverage
- Installs Composer v2

REQUIREMENTS
- shivammathur/setup-php@v2 action
- PHP versions 8.2, 8.3, or 8.4

### Step 3: Caching Composer Dependencies

```yaml
- name: Cache Composer dependencies
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ matrix.php }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: |
      ${{ runner.os }}-php-${{ matrix.php }}-composer-
```

**What it does:**
- Caches the `vendor` folder to speed up the build
- The cache key is based on the OS, PHP version, and hash composer.lock
- Restores the cache if an exact match is not found

**Requirements:** actions/cache@v3

### Step 4: Installing Composer Dependencies

```yaml
- name: Install Composer dependencies
  run: composer install --no-progress --prefer-dist --optimize-autoloader
```

**What it does:**
- Installs PHP dependencies from composer.json
- Uses distributions instead of source code (--prefer-dist)
- Optimizes the autoloader for production

**Requirements:**
- composer.json and composer.lock in the project root
- Access to Packagist

### Step 5: Setting up the WordPress test environment

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

**What it does:**
1. Clones the WordPress develop repository (--depth=1 for speed)
2. Copies the sample config to the working one
3. Replaces placeholders with actual database values
4. Changes the host to 127.0.0.1 (for Docker networking)
5. Sets the table prefix to 'wp_'
6. Adds debug settings

**Requirements:**
- Access to github.com/WordPress/wordpress-develop.git
- The MySQL service is running and accessible

### Step 6: Setting the environment variable

```yaml
- name: Set WP_TESTS_DIR environment variable
  run: echo "WP_TESTS_DIR=/tmp/wordpress-develop" >> $GITHUB_ENV
```

**What it does:** Sets the WP_TESTS_DIR environment variable for tests.

**Requirements:** $GITHUB_ENV is available in GitHub Actions.

### Step 7: Create a test database

```yaml
- name: Create test database
  run: |
    mysql -h 127.0.0.1 -u root -proot -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
```

**What it does:** Creates the wordpress_test database in MySQL.

**Requirements:**
- The MySQL service is running on 127.0.0.1:3306
- Root password: 'root'
- The user 'wordpress' with the password 'wordpress' has been created

### Step 8: Installing the WordPress test suite

```yaml
- name: Install WordPress test suite
  run: |
    cd /tmp/wordpress-develop
    php tests/phpunit/includes/install.php wp-tests-config.php
```

**What it does:**
- Runs the WordPress install script
- Creates all the necessary tables in the database
- Configures WordPress for testing

**Requirements:**
- PHP with the pdo_mysql extension
- wp-tests-config.php is configured correctly
- The database is accessible

### Step 9: Launching the quality gate

```yaml
- name: Run quality checks
  run: composer quality
```

**What it does:**
- Checks `composer.json`, Laravel Pint formatting, and PHPStan
- Runs all tests via the Composer script

**Requirements:**
- composer.json with the test script configured
- All dependencies are installed
- WP_TESTS_DIR is configured

### Local quality checks

```bash
composer format        # Fix the formatting of the Laravel preset
composer format:check  # check formatting without making changes
composer static-analysis
composer quality       # full quality gate
```

### Step 10: Running Coverage Tests (PHP 8.2 Only)

```yaml
- name: Run tests with coverage
  run: composer test:coverage
  if: matrix.php == '8.2'
```

**What it does:**
- Runs tests with code coverage analysis
- Generates a clover.xml report

**Requirements:**
- Xdebug is installed and configured
- composer.json with the test:coverage script

### Step 11: Uploading Coverage Reports (PHP 8.2 Only)

```yaml
- name: Upload coverage reports
  uses: codecov/codecov-action@v3
  if: matrix.php == '8.2'
  with:
    file: ./coverage/clover.xml
    fail_ci_if_error: false
```

**What it does:**
- Uploads clover.xml to Codecov
- The build does not fail if there is an upload error

Requirements
- codecov/codecov-action@v3
- CODECOV_TOKEN in secrets (optional)

Troubleshooting

### Problem: "Could not open input file: bin/install.php"

**Symptoms:** Error in the "Install WordPress test suite" step

**Solution:**
- Check the path to install.php
- It should be: `tests/phpunit/includes/install.php`
- NOT: `bin/install.php` or `tests/bin/install.php`

### Problem: "Failed opening required 'wp-tests-config.php'"

**Symptoms:** require_once error in install.php

**Solution:**
- Make sure that wp-tests-config.php was created in the previous step.
- Check that all sed commands have been executed correctly.
- Check the file permissions

### Problem: "SQLSTATE[HY000] [2002] Connection refused"

**Symptoms:** Tests cannot connect to MySQL

**Solution:**
- Check that the MySQL service is running
- Check health checks in services.mysql.options
- Make sure that 127.0.0.1 is used instead of localhost

### Problem: "Class 'Patchwork\... ' not found"

**Symptoms:** Errors related to Patchwork in tests

**Solution:**
- Check that Brain Monkey has been removed from composer.json
- Check bootstrap.php for Patchwork mocks
- Run tests with --exclude-group problematic

### Issue: Tests hang or time out

**Symptoms:** The build is interrupted due to a timeout.

**Solution:**
- Check the composer test script for correctness
- Make sure that all dependencies are installed
- Check that WP_TESTS_DIR is set correctly

## 📊 Metrics and Monitoring

### Execution time
- **Expected build time:** 1.5–2 minutes
- **Critical steps:** WordPress setup (30–45 sec), Tests (10–20 sec)

### Code coverage
- **Target coverage:** > 80%
- **Reports:** clover.xml -> Codecov
- **Analysis:** Only on PHP 8.2 for optimization

## 🚀 Scaling and Optimization

### Adding new PHP versions

```yaml
strategy:
  matrix:
    php: ['8.1', '8.2', '8.3', '8.4']
```

### Adding other databases

```yaml
services:
  mysql:
    image: mysql:8.0
  postgres:
    image: postgres:13
Configuration
```

### Parallel jobs

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

Safety

### Secrets and variables

- **CODECOV_TOKEN:** For uploading coverage (optional)
- **Other secrets:** Not required for basic operation

### Access

- **GitHub tokens:** Used automatically
- **External services:** Codecov (optional)

## 📝 Logs and debugging

View Logs

```bash
# In GitHub Actions UI
# Actions -> Workflow runs -> Select run -> View logs
```

### Local debugging

```bash
# Running locally in Docker
docker run -it --rm \
  -v $(pwd):/app \
  -w /app \
  php:8.2-cli \
  bash

containerised
composer install
# ... repeat the CI steps
```

## 🎯 Recovery after a breakdown

### Step 1: Check the workflow file
- Make sure that .github/workflows/test.yml exists
- Check the YAML syntax

### Step 2: Check dependencies
- All actions must be available (checkout@v4, cache@v3, etc.)
- Check the versions of actions for compatibility

### Step 3: Testing the steps
- Run each step manually locally
- Check the logs for errors

### Step 4: Check the environment
- Make sure that the MySQL service is configured correctly.
- Check the environment variables

### Step 5: Recovery
- If the workflow is broken, restore it from the Git history.
- If external services are unavailable, find alternatives.

---

## 📋 Recovery Checklist

- [ ] .github/workflows/test.yml exists
- [ ] All actions are available and up to date
- [ ] The MySQL service is configured correctly
- [ ] composer.json is correct
- [ ] tests/bootstrap.php is configured
- [ ] WordPress develop is available for cloning
- [ ] Codecov token is set (optional)

**Last Updated:** November 2025
**Status:** ✅ Operating stably 🚀
