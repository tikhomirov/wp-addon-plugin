<?php

namespace WpAddon\Services;

class RedirectRulesService
{
    /**
     * @return array<int, array{request: string, destination: string}>
     */
    public static function normalizeRules(mixed $rules): array
    {
        if (! is_array($rules)) {
            return [];
        }

        $normalized = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $request = mb_trim((string) ($rule['request'] ?? ''));
            $destination = mb_trim((string) ($rule['destination'] ?? ''));
            $line = mb_trim((string) ($rule['rule'] ?? ''));

            if (($request === '' || $destination === '') && $line !== '') {
                $parts = self::parseCsvLine($line);

                if (count($parts) >= 2) {
                    $request = $parts[0];
                    $destination = $parts[1];
                }
            }

            if ($request === '' || $destination === '') {
                continue;
            }

            $normalized[] = [
                'request'     => self::normalizeRequestPath($request),
                'destination' => $destination,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function toMap(mixed $rules): array
    {
        $map = [];

        foreach (self::normalizeRules($rules) as $rule) {
            $map[$rule['request']] = $rule['destination'];
        }

        return $map;
    }

    /**
     * @return array<int, array{request: string, destination: string}>
     */
    public static function parseCsv(string $csv): array
    {
        $rules = [];

        foreach (preg_split('/\R/', $csv) ?: [] as $line) {
            if (self::isCommentOrEmptyLine($line)) {
                continue;
            }

            $line = mb_trim($line);

            if (preg_match('/^(request|source|from)\s*[,;\t]/i', $line)) {
                continue;
            }

            $parts = self::parseCsvLine($line);

            if (count($parts) < 2) {
                continue;
            }

            $request = self::normalizeRequestPath($parts[0]);
            $destination = mb_trim($parts[1]);

            if ($request === '' || $destination === '') {
                continue;
            }

            $rules[] = [
                'request'     => $request,
                'destination' => $destination,
            ];
        }

        return $rules;
    }

    /**
     * @param  array<int, array{request: string, destination: string}> $existing
     * @param  array<int, array{request: string, destination: string}> $imported
     * @return array<int, array{request: string, destination: string}>
     */
    public static function mergeRules(array $existing, array $imported): array
    {
        $map = [];

        foreach (self::normalizeRules($existing) as $rule) {
            $map[$rule['request']] = $rule;
        }

        foreach (self::normalizeRules($imported) as $rule) {
            $map[$rule['request']] = $rule;
        }

        return array_values($map);
    }

    /**
     * @param array<string, mixed> $rule
     */
    public static function formatRuleLine(array $rule): string
    {
        $line = mb_trim((string) ($rule['rule'] ?? ''));

        if ($line !== '') {
            return $line;
        }

        $request = mb_trim((string) ($rule['request'] ?? ''));
        $destination = mb_trim((string) ($rule['destination'] ?? ''));

        if ($request === '' || $destination === '') {
            return '';
        }

        return $request . ',' . $destination;
    }

    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function prepareOptionsForRedirectsAdmin(array $options): array
    {
        if (empty($options['redirects_rules']) || ! is_array($options['redirects_rules'])) {
            return $options;
        }

        $options['redirects_rules'] = array_values(array_map(
            static function (mixed $rule): array {
                if (! is_array($rule)) {
                    return ['rule' => ''];
                }

                $line = self::formatRuleLine($rule);

                return ['rule' => $line];
            },
            $options['redirects_rules'],
        ));

        return $options;
    }

    private static function isCommentOrEmptyLine(string $line): bool
    {
        $trimmed = mb_ltrim($line);

        if ($trimmed === '') {
            return true;
        }

        return str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, ';');
    }

    /**
     * @return array<int, string>
     */
    private static function parseCsvLine(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line, 2));
        }

        if (str_contains($line, ';') && ! str_contains($line, ',')) {
            return array_map('trim', explode(';', $line, 2));
        }

        $parsed = str_getcsv($line, ',', '"', '\\');

        if (! is_array($parsed)) {
            return [];
        }

        return array_map('trim', $parsed);
    }

    private static function normalizeRequestPath(string $path): string
    {
        $path = mb_trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            $parsedPath = wp_parse_url($path, PHP_URL_PATH);

            return self::normalizeRequestPath((string) ($parsedPath ?? ''));
        }

        return '/' . mb_ltrim($path, '/');
    }
}
