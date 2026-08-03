<?php

namespace WpAddon\Services;

/**
 * Service for optimizing CSS and JavaScript assets
 */
class AssetOptimizationService
{
    private string $cacheDir;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->cacheDir = rtrim($config['cache_dir'], '/') . '/';
        $this->ensureCacheDir();
    }

    private function ensureCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Minify CSS content
     */
    public function minifyCss(string $css): string
    {
        if (!$this->config['minify_css']) {
            return $css;
        }

        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);

        return trim($css);
    }

    /**
     * Minify JavaScript content
     */
    public function minifyJs(string $js): string
    {
        if (!$this->config['minify_js']) {
            return $js;
        }

        // Basic minification: remove comments and extra whitespace
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        $js = preg_replace('!//[^\n]*!', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}:;,=()+\-*\[\]\/&|<>!?~%^])\s*/', '$1', $js);

        return trim($js);
    }

    /**
     * Combine multiple CSS files
     */
    public function combineCss(array $files): string
    {
        if (!$this->config['combine_css']) {
            return '';
        }

        $combined = '';
        foreach ($files as $file) {
            if (file_exists($file)) {
                $combined .= file_get_contents($file) . "\n";
            }
        }

        return $this->minifyCss($combined);
    }

    /**
     * Combine multiple JS files
     */
    public function combineJs(array $files): string
    {
        if (!$this->config['combine_js']) {
            return '';
        }

        $combined = '';
        foreach ($files as $file) {
            if (file_exists($file)) {
                $combined .= file_get_contents($file) . ";\n";
            }
        }

        return $this->minifyJs($combined);
    }

    /**
     * Generate version hash for cache busting
     */
    public function generateVersion(string $content): string
    {
        return substr(md5($content . $this->config['version_salt']), 0, 8);
    }

    /**
     * Save optimized content to cache
     */
    public function saveToCache(string $key, string $content): string
    {
        $file = $this->cacheDir . $key . '.gz';
        file_put_contents($file, gzcompress($content, 6));
        return $key;
    }

    /**
     * Get cached content
     */
    public function getFromCache(string $key): ?string
    {
        $file = $this->cacheDir . $key . '.gz';
        if (!is_file($file)) {
            return null;
        }

        $compressed = file_get_contents($file);
        $content = $compressed === false ? false : @gzuncompress($compressed);
        if ($content === false) {
            unlink($file);

            return null;
        }

        return $content;
    }

    public function cleanupCache(int $maxAge = 604800, int $maxFiles = 500): void
    {
        $files = glob($this->cacheDir . '*.gz') ?: [];
        $now = time();

        foreach ($files as $file) {
            $modifiedAt = filemtime($file);
            if ($modifiedAt === false || ($now - $modifiedAt) > $maxAge) {
                unlink($file);
            }
        }

        $files = glob($this->cacheDir . '*.gz') ?: [];
        if (count($files) <= $maxFiles) {
            return;
        }

        usort($files, static fn(string $left, string $right): int => (filemtime($left) ?: 0) <=> (filemtime($right) ?: 0));
        foreach (array_slice($files, 0, count($files) - $maxFiles) as $file) {
            unlink($file);
        }
    }

    /**
     * Extract critical CSS (basic implementation)
     */
    public function extractCriticalCss(string $css, array $selectors = []): string
    {
        if (!$this->config['critical_css_enabled']) {
            return '';
        }

        $critical = '';
        $lines = explode("\n", $css);

        foreach ($lines as $line) {
            // Simple check for common critical selectors
            if (preg_match('/^(body|html|\.site|\.header|\.nav|\.main|\.footer)/i', trim($line))) {
                $critical .= $line . "\n";
            }
        }

        return $this->minifyCss($critical);
    }

    /**
     * Get exclude patterns
     */
    public function getExcludeCss(): array
    {
        return $this->config['exclude_css'];
    }

    public function getExcludeJs(): array
    {
        return $this->config['exclude_js'];
    }
}
