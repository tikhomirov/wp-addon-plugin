<?php

use WpAddon\Core\CodeStarInstaller;

beforeEach(function () {
    $this->pluginDir = sys_get_temp_dir().'/wp-addon-csf-'.bin2hex(random_bytes(6)).'/';
    mkdir($this->pluginDir, 0777, true);
    $this->bundledFile = $this->pluginDir.'lib/codestar-framework/codestar-framework.php';
    $this->siblingFile = $this->pluginDir.'sibling/codestar-framework/codestar-framework.php';
});

afterEach(function () {
    if (! is_dir($this->pluginDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->pluginDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($this->pluginDir);
});

it('resolves candidates in order: bundled copy, then sibling plugin', function () {
    mkdir(dirname($this->bundledFile), 0777, true);
    mkdir(dirname($this->siblingFile), 0777, true);
    file_put_contents($this->bundledFile, '<?php');
    file_put_contents($this->siblingFile, '<?php');

    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    expect($installer->candidates())->toBe([$this->bundledFile, $this->siblingFile]);
});

it('falls back to the sibling plugin when no bundled copy exists', function () {
    mkdir(dirname($this->siblingFile), 0777, true);
    file_put_contents($this->siblingFile, '<?php');

    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    expect($installer->candidates())->toBe([$this->siblingFile]);
});

it('reports no candidates when the framework is missing everywhere', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    expect($installer->candidates())->toBe([])
        ->and($installer->bootstrap())->toBeFalse()
        ->and($installer->isLoaded())->toBeFalse();
});

it('throttles automatic installation attempts to once per hour', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    update_option('wp_addon_csf_last_attempt', 0, false);
    expect($installer->shouldAttemptAutoInstall(1000000))->toBeTrue();

    update_option('wp_addon_csf_last_attempt', 1000000, false);
    expect($installer->shouldAttemptAutoInstall(1000000 + 3599))->toBeFalse()
        ->and($installer->shouldAttemptAutoInstall(1000000 + 3600))->toBeTrue();
});

it('returns a WP_Error when WordPress download utilities are unavailable', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    expect($installer->install())->toBeInstanceOf(WP_Error::class);
})->skip(function () {
    if (function_exists('download_url')) {
        return true;
    }

    return file_exists((defined('ABSPATH') ? ABSPATH : '').'wp-admin/includes/file.php');
}, 'WordPress download utilities are available in this environment');

it('renders a one-click install button for users allowed to install plugins', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);
    $html = $installer->noticeHtml(true);

    expect($html)->toContain('notice-warning')
        ->toContain('admin-post.php?action=wp_addon_install_csf')
        ->toContain('_wpnonce')
        ->toContain('Install CodeStar Framework now')
        ->toContain('https://github.com/Codestar/codestar-framework');
});

it('hides the install button for users without the install_plugins capability', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);
    $html = $installer->noticeHtml(false);

    expect($html)->not->toContain('Install CodeStar Framework now')
        ->toContain('https://github.com/Codestar/codestar-framework');
});

it('surfaces the last installation error in the notice', function () {
    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);
    $html = $installer->noticeHtml(true, 'Could not create the directory /srv/www/lib/');

    expect($html)->toContain('Could not create the directory /srv/www/lib/');
});

// Keep this test last in the file: it defines the global CSF class for the process.
it('loads the framework from the bundled copy', function () {
    mkdir(dirname($this->bundledFile), 0777, true);
    file_put_contents($this->bundledFile, "<?php if (! class_exists('CSF')) { class CSF {} }");

    $installer = new CodeStarInstaller($this->pluginDir, $this->siblingFile);

    expect($installer->isLoaded())->toBeFalse()
        ->and($installer->bootstrap())->toBeTrue()
        ->and($installer->isLoaded())->toBeTrue()
        ->and(class_exists('CSF'))->toBeTrue()
        ->and($installer->candidates())->toBe([$this->bundledFile]);
});
