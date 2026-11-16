<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\RedirectRulesService;
use WpAddon\Traits\HookTrait;

class Redirects implements ModuleInterface
{
    use HookTrait;

    public function init(): void
    {
        $this->addHook('csf_wp-addon_save', [$this, 'sanitizeRulesOnSave'], 10, 2);
        $this->addHook('wp_ajax_wp_addon_redirects_import', [$this, 'importRulesAjax']);
        $this->addHook('option_wp-addon', [$this, 'prepareRulesForAdminForm'], 5);

        if (! $this->isEnabled()) {
            return;
        }

        $this->addHook('init', [$this, 'processRedirects'], 1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeRulesOnSave(array $data, mixed $instance): array
    {
        return $this->importCsvOnSave($data, $instance);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function importCsvOnSave(array $data, mixed $instance): array
    {
        $data['redirects_rules'] = RedirectRulesService::normalizeRules($data['redirects_rules'] ?? null);

        $csv = trim((string) ($data['redirects_csv_import'] ?? ''));
        if ($csv !== '') {
            $imported = RedirectRulesService::parseCsv($csv);
            $data['redirects_rules'] = RedirectRulesService::mergeRules($data['redirects_rules'], $imported);
            $data['redirects_csv_import'] = '';
        }

        return $data;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function prepareRulesForAdminForm($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! is_admin() || ! isset($_GET['page']) || $_GET['page'] !== 'wp-addon') {
            return $value;
        }

        return RedirectRulesService::prepareOptionsForRedirectsAdmin($value);
    }

    public function importRulesAjax(): void
    {
        check_ajax_referer('wp_addon_redirects_import');

        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to import redirect rules.', 'wp-addon'),
            ], 403);
        }

        $csv = isset($_POST['csv']) ? trim(wp_unslash((string) $_POST['csv'])) : '';

        if ($csv === '') {
            wp_send_json_error([
                'message' => __('Paste rules into the field or choose a file.', 'wp-addon'),
            ]);
        }

        $existingJson = isset($_POST['rules']) ? wp_unslash((string) $_POST['rules']) : '';
        $existing = [];

        if ($existingJson !== '') {
            $decoded = json_decode($existingJson, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $imported = RedirectRulesService::parseCsv($csv);

        if ($imported === []) {
            wp_send_json_error([
                'message' => __('No valid rules found. Check the format: source,destination — one rule per line.', 'wp-addon'),
            ]);
        }

        $merged = RedirectRulesService::mergeRules($existing, $imported);

        $options = get_option('wp-addon', []);
        if (! is_array($options)) {
            $options = [];
        }

        $options['redirects_rules'] = $merged;
        $options['redirects_csv_import'] = '';
        update_option('wp-addon', $options);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: imported count, 2: total count */
                __('Imported %1$d rule(s). Total in list: %2$d.', 'wp-addon'),
                count($imported),
                count($merged)
            ),
            'imported' => count($imported),
            'total' => count($merged),
        ]);
    }

    public function processRedirects(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $options = get_option('wp-addon', []);
        $redirects = RedirectRulesService::toMap($options['redirects_rules'] ?? null);

        if ($redirects === []) {
            return;
        }

        $userrequest = $this->getRequestPath();
        $wildcard = ! empty($options['redirects_wildcard']);

        foreach ($redirects as $storedrequest => $destination) {
            $matched = $this->matchRedirect($userrequest, $storedrequest, $destination, $wildcard);

            if ($matched === null) {
                continue;
            }

            if (trim($matched, '/') === trim($userrequest, '/')) {
                continue;
            }

            $location = $this->buildRedirectLocation($matched);
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: '.$location);
            exit;
        }
    }

    private function matchRedirect(string $userrequest, string $storedrequest, string $destination, bool $wildcard): ?string
    {
        if ($wildcard && str_contains($storedrequest, '*')) {
            if (str_starts_with($userrequest, '/wp-login') || str_starts_with($userrequest, '/wp-admin')) {
                return null;
            }

            $patternRequest = str_replace('*', '(.*)', $storedrequest);
            $pattern = '/^'.str_replace('/', '\/', rtrim($patternRequest, '/')).'/';
            $target = str_replace('*', '$1', $destination);
            $output = preg_replace($pattern, $target, $userrequest);

            if (is_string($output) && $output !== $userrequest) {
                return $output;
            }

            return null;
        }

        $normalizedRequest = rtrim(urldecode($userrequest), '/') ?: '/';
        $normalizedStored = rtrim($storedrequest, '/') ?: '/';

        if ($normalizedRequest === $normalizedStored) {
            return $destination;
        }

        return null;
    }

    private function buildRedirectLocation(string $destination): string
    {
        if (preg_match('#^https?://#i', $destination)) {
            return $destination;
        }

        if (str_starts_with($destination, '/')) {
            return home_url($destination);
        }

        return home_url('/'.ltrim($destination, '/'));
    }

    private function getRequestPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = wp_parse_url($requestUri, PHP_URL_PATH) ?? '/';

        $homePath = wp_parse_url(home_url(), PHP_URL_PATH) ?? '';
        $homePath = rtrim((string) $homePath, '/');

        if ($homePath !== '' && str_starts_with($path, $homePath)) {
            $path = substr($path, strlen($homePath));
        }

        $path = '/'.ltrim((string) $path, '/');

        return rtrim($path, '/') ?: '/';
    }

    private function isEnabled(): bool
    {
        $options = get_option('wp-addon', []);

        return ! array_key_exists('redirect_enable', $options) || $this->isTruthy($options['redirect_enable']);
    }

    private function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
