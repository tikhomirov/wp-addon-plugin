<?php

namespace WpAddon\Core;

/**
 * Resolves, loads and, when needed, installs the CodeStar Framework (CSF).
 *
 * Resolution order:
 *  1. CSF already loaded (standalone codestar-framework plugin, theme bundle).
 *  2. Bundled copy in <plugin>/lib/codestar-framework/.
 *  3. Copy shipped as a sibling plugin in wp-content/plugins/codestar-framework/.
 *  4. Download of a pinned release from GitHub into lib/ (admin only, throttled).
 */
class CodeStarInstaller
{
    /**
     * Pinned framework release used for automatic installation.
     */
    public const ZIP_URL = 'https://github.com/Codestar/codestar-framework/archive/refs/tags/2.3.1.zip';

    /**
     * admin-post.php action used by the manual "Install now" button.
     */
    public const ACTION = 'wp_addon_install_csf';

    private const OPTION_LAST_ATTEMPT = 'wp_addon_csf_last_attempt';

    private const OPTION_LAST_ERROR = 'wp_addon_csf_last_error';

    private const TRANSIENT_NOTICE = 'wp_addon_csf_notice';

    /**
     * Minimum delay between automatic installation attempts, in seconds.
     */
    private const ATTEMPT_INTERVAL = 3600;

    /**
     * Plugin directory, with a trailing slash.
     */
    private readonly string $dir;

    /**
     * Entry file of the sibling codestar-framework plugin.
     */
    private readonly string $siblingFile;

    /**
     * @param  string  $pluginDir  Absolute path of the plugin directory
     * @param  string  $siblingFile  Entry file of the sibling codestar-framework plugin
     */
    public function __construct(string $pluginDir, string $siblingFile)
    {
        $this->dir = rtrim($pluginDir, '/\\').'/';
        $this->siblingFile = $siblingFile;
    }

    public function isLoaded(): bool
    {
        return class_exists('CSF');
    }

    public function bundledFile(): string
    {
        return $this->dir.'lib/codestar-framework/codestar-framework.php';
    }

    /**
     * Readable framework entry files, bundled copy first.
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        $candidates = [];

        foreach ([$this->bundledFile(), $this->siblingFile] as $file) {
            if (is_readable($file) && ! in_array($file, $candidates, true)) {
                $candidates[] = $file;
            }
        }

        return $candidates;
    }

    /**
     * Load the framework from the first available source.
     */
    public function bootstrap(): bool
    {
        if ($this->isLoaded()) {
            return true;
        }

        foreach ($this->candidates() as $file) {
            require_once $file;

            if ($this->isLoaded()) {
                return true;
            }
        }

        return $this->isLoaded();
    }

    /**
     * Whether an automatic installation should be attempted at $now.
     *
     * Attempts are skipped when the framework is already loaded, during AJAX,
     * and until the throttle interval has passed since the last attempt.
     */
    public function shouldAttemptAutoInstall(int $now): bool
    {
        if ($this->isLoaded() || wp_doing_ajax()) {
            return false;
        }

        $last = (int) get_option(self::OPTION_LAST_ATTEMPT, 0);

        return $last <= 0 || ($now - $last) >= self::ATTEMPT_INTERVAL;
    }

    /**
     * Download and load the framework, at most once per throttle interval.
     */
    public function maybeAutoInstall(): void
    {
        if (! $this->shouldAttemptAutoInstall(time()) || ! current_user_can('install_plugins')) {
            return;
        }

        update_option(self::OPTION_LAST_ATTEMPT, time(), false);
        $this->recordInstallResult($this->install());
    }

    /**
     * Manual installation endpoint: admin-post.php?action=wp_addon_install_csf.
     */
    public function handleInstallRequest(): void
    {
        check_admin_referer(self::ACTION);

        if (! current_user_can('install_plugins')) {
            wp_die(
                esc_html__('You are not allowed to install plugins.', 'wp-addon'),
                '',
                ['response' => 403]
            );
        }

        $this->recordInstallResult($this->install());

        $referer = wp_get_referer();
        wp_safe_redirect($referer ?: admin_url('/plugins.php'));
        exit;
    }

    /**
     * Download the pinned release into lib/ and load it.
     */
    public function install(): true|\WP_Error
    {
        if ($this->isLoaded()) {
            return true;
        }

        $adminFile = ABSPATH.'wp-admin/includes/file.php';
        if (! function_exists('download_url') && file_exists($adminFile)) {
            require_once $adminFile;
        }

        if (! function_exists('download_url') || ! function_exists('unzip_file') || ! function_exists('WP_Filesystem')) {
            return new \WP_Error(
                'csf_dependencies',
                __('WordPress download utilities are unavailable (wp-admin/includes/file.php could not be loaded).', 'wp-addon')
            );
        }

        $tempZip = download_url(self::ZIP_URL);

        if (is_wp_error($tempZip)) {
            return $tempZip;
        }

        if (! WP_Filesystem()) {
            @unlink($tempZip);

            return new \WP_Error(
                'csf_filesystem',
                __('Could not connect to the filesystem to install CodeStar Framework.', 'wp-addon')
            );
        }

        global $wp_filesystem;

        $libDir = $this->dir.'lib/';
        if (! is_dir($libDir) && ! wp_mkdir_p($libDir)) {
            @unlink($tempZip);

            return new \WP_Error(
                'csf_lib_dir',
                sprintf(
                    /* translators: %s: absolute directory path */
                    __('Could not create the directory %s for the CodeStar Framework.', 'wp-addon'),
                    $libDir
                )
            );
        }

        $target = $libDir.'codestar-framework/';
        if (is_dir($target)) {
            $wp_filesystem->delete($target, true);
        }

        $unzip = unzip_file($tempZip, $libDir);
        @unlink($tempZip);

        if (is_wp_error($unzip)) {
            return $unzip;
        }

        $extracted = glob($libDir.'codestar-framework-*', GLOB_ONLYDIR) ?: [];
        if ($extracted === []) {
            return new \WP_Error(
                'csf_extract',
                __('The CodeStar Framework archive did not extract as expected.', 'wp-addon')
            );
        }

        if (! $wp_filesystem->move(rtrim($extracted[0], '/\\'), rtrim($target, '/\\'))) {
            return new \WP_Error(
                'csf_move',
                __('Could not move the CodeStar Framework into the plugin directory.', 'wp-addon')
            );
        }

        if (! is_readable($this->bundledFile())) {
            return new \WP_Error(
                'csf_missing_file',
                __('The CodeStar Framework entry file is missing after installation.', 'wp-addon')
            );
        }

        require_once $this->bundledFile();

        return $this->isLoaded()
            ? true
            : new \WP_Error('csf_load', __('The CodeStar Framework could not be loaded after installation.', 'wp-addon'));
    }

    /**
     * Admin notice: success feedback when loaded, setup help when missing.
     */
    public function renderNotice(): void
    {
        if ($this->isLoaded()) {
            $notice = get_transient(self::TRANSIENT_NOTICE);

            if (is_array($notice) && ! empty($notice['message'])) {
                delete_transient(self::TRANSIENT_NOTICE);
                $type = ($notice['type'] ?? '') === 'success' ? 'success' : 'error';

                printf(
                    '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                    esc_attr($type),
                    esc_html((string) $notice['message'])
                );
            }

            return;
        }

        if (! current_user_can('activate_plugins')) {
            return;
        }

        $error = get_option(self::OPTION_LAST_ERROR, '');

        echo $this->noticeHtml(
            current_user_can('install_plugins'),
            $error === '' || $error === null ? null : (string) $error
        );
    }

    /**
     * Markup of the "CodeStar Framework is missing" warning.
     *
     * @param  bool  $canInstall  Whether the current user may install the framework
     * @param  string|null  $error  Last installation error to display
     */
    public function noticeHtml(bool $canInstall, ?string $error = null): string
    {
        $installUrl = wp_nonce_url(admin_url('/admin-post.php?action='.self::ACTION), self::ACTION);

        $html = '<div class="notice notice-warning"><p>'
            .esc_html__('WP Addon uses the CodeStar Framework for its settings screen, but the framework was not found.', 'wp-addon')
            .'</p>';

        if ($error !== null && $error !== '') {
            $html .= '<p><code>'.esc_html($error).'</code></p>';
        }

        if ($canInstall) {
            $html .= '<p><a class="button button-primary" href="'.esc_url($installUrl).'">'
                .esc_html__('Install CodeStar Framework now', 'wp-addon')
                .'</a></p>';
        }

        $html .= '<p><a href="https://github.com/Codestar/codestar-framework" target="_blank" rel="noopener noreferrer">'
            .esc_html__('Or install the framework manually from GitHub', 'wp-addon')
            .'</a></p>';

        return $html.'</div>';
    }

    /**
     * Persist the outcome of an installation attempt.
     */
    private function recordInstallResult(true|\WP_Error $result): void
    {
        if (is_wp_error($result)) {
            update_option(self::OPTION_LAST_ERROR, $result->get_error_message(), false);

            return;
        }

        delete_option(self::OPTION_LAST_ERROR);
        set_transient(
            self::TRANSIENT_NOTICE,
            [
                'type' => 'success',
                'message' => __('CodeStar Framework installed. Reload the page to open the settings.', 'wp-addon'),
            ],
            60
        );
    }
}
