<?php

namespace WpAddon\Services;

use WpAddon\Interfaces\CacheInterface;

class CacheService implements CacheInterface
{
    private string $cacheDir;
    private int $ttl;

    public function __construct(string $cacheDir = '', int $ttl = 3600)
    {
        $this->cacheDir = $cacheDir ?: WP_CONTENT_DIR . '/cache/pages/';
        $this->ttl = $ttl;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function generateCacheKey(string $url): string
    {
        return md5($url);
    }

    public function getCachedContent(string $key): ?string
    {
        $file = $this->cacheDir . $key . '.gz';
        if (!is_file($file)) {
            return null;
        }

        $modifiedAt = filemtime($file);
        if ($modifiedAt === false || (time() - $modifiedAt) > $this->ttl) {
            $this->deleteFile($file);

            return null;
        }

        $compressed = file_get_contents($file);
        $content = $compressed === false ? false : @gzuncompress($compressed);
        if ($content === false) {
            $this->deleteFile($file);

            return null;
        }

        return $content;
    }

    public function cleanup(int $maxEntries, int $maxAge, int $batchSize): void
    {
        $files = glob($this->cacheDir . '*.gz') ?: [];
        $now = time();
        $removed = 0;

        foreach ($files as $file) {
            if ($removed >= $batchSize) {
                break;
            }

            $modifiedAt = filemtime($file);
            if ($modifiedAt === false || ($now - $modifiedAt) > $maxAge) {
                $this->deleteFile($file);
                $removed++;
            }
        }

        $files = glob($this->cacheDir . '*.gz') ?: [];
        if (count($files) <= $maxEntries) {
            return;
        }

        usort($files, static fn(string $left, string $right): int => (filemtime($left) ?: 0) <=> (filemtime($right) ?: 0));
        $entriesToRemove = min(count($files) - $maxEntries, max(0, $batchSize - $removed));
        foreach (array_slice($files, 0, $entriesToRemove) as $file) {
            $this->deleteFile($file);
        }
    }

    private function deleteFile(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function saveCachedContent(string $key, string $content): void
    {
        $file = $this->cacheDir . $key . '.gz';
        file_put_contents($file, gzcompress($content, 6));
    }

    public function clearCache(): void
    {
        $files = glob($this->cacheDir . '*.gz');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
