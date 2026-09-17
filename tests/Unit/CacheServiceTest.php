<?php

use WpAddon\Services\CacheService;

beforeEach(function () {
    $this->cacheDirectory = sys_get_temp_dir().'/wp-addon-cache-'.bin2hex(random_bytes(6)).'/';
});

afterEach(function () {
    if (! is_dir($this->cacheDirectory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->cacheDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($this->cacheDirectory);
});

it('stores apache-readable html and gzip page variants', function () {
    $cache = new CacheService($this->cacheDirectory, 3600);

    expect($cache->savePage('example.com', '/docs/cache/', '<!doctype html><p>cached</p>'))->toBeTrue();

    $html = $this->cacheDirectory.'example.com/docs/cache/index.html';
    expect(file_get_contents($html))->toBe('<!doctype html><p>cached</p>')
        ->and(gzdecode((string) file_get_contents($html.'.gz')))->toBe('<!doctype html><p>cached</p>')
        ->and($cache->getCachedPage('example.com', '/docs/cache/'))->toBe('<!doctype html><p>cached</p>');
});

it('rejects unsafe hosts and traversal paths', function () {
    $cache = new CacheService($this->cacheDirectory, 3600);

    expect($cache->getRelativePath('bad/host', '/'))->toBeNull()
        ->and($cache->getRelativePath('example.com', '/../secret/'))->toBeNull();
});

it('clears nested page cache files', function () {
    $cache = new CacheService($this->cacheDirectory, 3600);
    $cache->savePage('example.com', '/one/two/', '<html>cached</html>');

    $cache->clearCache();

    expect(glob($this->cacheDirectory.'example.com/*'))->toBe([]);
});
