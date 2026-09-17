<?php

namespace WpAddon\Services;

use WpAddon\Interfaces\CacheInterface;

class CacheService implements CacheInterface
{
    private string $cacheDir;

    private int $ttl;

    public function __construct(string $cacheDir = '', int $ttl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir ?: WP_CONTENT_DIR.'/cache/pages/', '/\\').'/';
        $this->ttl = $ttl;

        if (! is_dir($this->cacheDir) && ! $this->createDirectory($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to create cache directory: %s', $this->cacheDir));
        }
    }

    public function generateCacheKey(string $url): string
    {
        return md5($url);
    }

    public function getCachedContent(string $key): ?string
    {
        $file = $this->cacheDir.$key.'.html';
        if (! $this->isFresh($file)) {
            return null;
        }

        $content = file_get_contents($file);

        return $content === false ? null : $content;
    }

    public function getRelativePath(string $host, string $path): ?string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
        if (! preg_match('/\A[a-z0-9.-]+\z/', $host)) {
            return null;
        }

        $path = rawurldecode($path);
        $segments = array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== '');
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                return null;
            }
        }

        $encodedPath = implode('/', array_map('rawurlencode', $segments));

        return $host.'/'.($encodedPath === '' ? '' : $encodedPath.'/').'index.html';
    }

    public function getCachedPage(string $host, string $path): ?string
    {
        $relativePath = $this->getRelativePath($host, $path);

        return $relativePath === null ? null : $this->readFile($this->cacheDir.$relativePath);
    }

    public function savePage(string $host, string $path, string $content): bool
    {
        $relativePath = $this->getRelativePath($host, $path);
        if ($relativePath === null || $content === '') {
            return false;
        }

        $file = $this->cacheDir.$relativePath;
        if (! $this->atomicWrite($file, $content)) {
            return false;
        }

        $compressed = gzencode($content, 6);
        if ($compressed !== false) {
            $this->atomicWrite($file.'.gz', $compressed);
        }

        return true;
    }

    public function saveCachedContent(string $key, string $content): void
    {
        $this->atomicWrite($this->cacheDir.$key.'.html', $content);
    }

    public function cleanup(int $maxEntries, int $maxAge, int $batchSize): void
    {
        $files = $this->findCacheFiles();
        $now = time();
        $removed = 0;

        foreach ($files as $file) {
            if ($removed >= $batchSize) {
                break;
            }

            $modifiedAt = filemtime($file);
            if ($modifiedAt === false || ($now - $modifiedAt) > $maxAge) {
                $this->deleteCachePair($file);
                $removed++;
            }
        }

        $files = $this->findCacheFiles();
        if (count($files) <= $maxEntries || $removed >= $batchSize) {
            return;
        }

        usort($files, static fn (string $left, string $right): int => (filemtime($left) ?: 0) <=> (filemtime($right) ?: 0));
        $entriesToRemove = min(count($files) - $maxEntries, $batchSize - $removed);
        foreach (array_slice($files, 0, $entriesToRemove) as $file) {
            $this->deleteCachePair($file);
        }
    }

    public function clearCache(): void
    {
        if (! is_dir($this->cacheDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }

    private function isFresh(string $file): bool
    {
        if (! is_file($file)) {
            return false;
        }

        $modifiedAt = filemtime($file);
        if ($modifiedAt !== false && (time() - $modifiedAt) <= $this->ttl) {
            return true;
        }

        $this->deleteCachePair($file);

        return false;
    }

    private function readFile(string $file): ?string
    {
        if (! $this->isFresh($file)) {
            return null;
        }

        $content = file_get_contents($file);

        return $content === false ? null : $content;
    }

    private function atomicWrite(string $file, string $content): bool
    {
        $directory = dirname($file);
        if (! is_dir($directory) && ! $this->createDirectory($directory)) {
            return false;
        }

        $temporaryFile = tempnam($directory, '.page-cache-');
        if ($temporaryFile === false) {
            return false;
        }

        $written = file_put_contents($temporaryFile, $content, LOCK_EX);
        if ($written === false || ! rename($temporaryFile, $file)) {
            @unlink($temporaryFile);

            return false;
        }

        @chmod($file, 0644);

        return true;
    }

    private function createDirectory(string $directory): bool
    {
        return function_exists('wp_mkdir_p') ? wp_mkdir_p($directory) : mkdir($directory, 0755, true);
    }

    private function findCacheFiles(): array
    {
        if (! is_dir($this->cacheDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && str_ends_with($item->getFilename(), '.html')) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    private function deleteCachePair(string $file): void
    {
        @unlink($file);
        @unlink($file.'.gz');
    }
}
