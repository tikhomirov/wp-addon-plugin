<?php

use WpAddon\Services\OptionService;

beforeEach(function () {
    if (! class_exists('WP_Admin_Bar')) {
        eval('class WP_Admin_Bar { public array $nodes = []; public function add_node(array $node): void { $this->nodes[$node["id"]] = $node; } }');
    }

    if (! function_exists('wp_nonce_url')) {
        eval('function wp_nonce_url($url, $action = -1) { return $url . "&_wpnonce=test_nonce_" . $action; }');
    }

    if (! defined('RW_PLUGIN_DIR')) {
        define('RW_PLUGIN_DIR', dirname(__DIR__, 2).'/');
    }
    if (! defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', sys_get_temp_dir().'/wp-addon-content');
    }

    require_once RW_PLUGIN_DIR.'functions/PageCache.php';
    $optionService = Mockery::mock(OptionService::class);
    $optionService->shouldReceive('getSettings')->andReturn([]);
    $optionService->shouldReceive('updateSettings')->andReturnTrue();
    $optionService->shouldReceive('getSetting')->andReturnUsing(static fn (string $key, $default = null) => $default);
    $this->pageCache = new PageCache($optionService);
});

it('adds a nonce-protected clear action to the admin bar', function () {
    $adminBar = new WP_Admin_Bar;

    $this->pageCache->addAdminBarMenu($adminBar);

    expect($adminBar->nodes)->toHaveKey('wp-addon-clear-page-cache');
    $node = $adminBar->nodes['wp-addon-clear-page-cache'];
    expect($node['title'])->toBe('Clear page cache')
        ->and($node['href'])->toContain('admin-post.php?action=wp_addon_clear_page_cache')
        ->and($node['href'])->toContain('_wpnonce=test_nonce_wp_addon_page_cache');
});

it('renders cache diagnostics and the ajax clear control', function () {
    $panel = PageCache::renderAdminPanel();

    expect($panel)->toContain('Server')
        ->toContain('Early delivery')
        ->toContain('Nginx config')
        ->toContain('Cached pages')
        ->toContain('wp_addon_clear_page_cache')
        ->toContain('Clear page cache');
});
