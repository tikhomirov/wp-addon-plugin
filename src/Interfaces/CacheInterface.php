<?php

namespace WpAddon\Interfaces;

interface CacheInterface
{
    public function generateCacheKey(string $url): string;

    public function getCachedContent(string $key): ?string;

    public function saveCachedContent(string $key, string $content): void;

    public function clearCache(): void;
}
