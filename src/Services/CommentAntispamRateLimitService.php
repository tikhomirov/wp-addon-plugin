<?php

namespace WpAddon\Services;

/**
 * Sliding window rate limit per IP, stored in transients.
 *
 * Disabled by default: both limits are 0 until the site owner opts in, because
 * a shared IP (family, office, mobile carrier) can legitimately comment
 * several times in a row.
 */
class CommentAntispamRateLimitService
{
    private int $perHour;

    private int $perDay;

    public function __construct(int $perHour = 0, int $perDay = 0)
    {
        $this->perHour = max(0, $perHour);
        $this->perDay = max(0, $perDay);
    }

    public function isEnabled(): bool
    {
        return $this->perHour > 0 || $this->perDay > 0;
    }

    public function isExceeded(string $ip): bool
    {
        if (! $this->isEnabled() || $ip === '') {
            return false;
        }

        if ($this->perHour > 0 && $this->count($ip, HOUR_IN_SECONDS) >= $this->perHour) {
            return true;
        }

        return $this->perDay > 0 && $this->count($ip, DAY_IN_SECONDS) >= $this->perDay;
    }

    public function register(string $ip): void
    {
        if (! $this->isEnabled() || $ip === '') {
            return;
        }

        if ($this->perHour > 0) {
            $this->push($ip, HOUR_IN_SECONDS);
        }

        if ($this->perDay > 0) {
            $this->push($ip, DAY_IN_SECONDS);
        }
    }

    public function count(string $ip, int $window): int
    {
        return count($this->read($ip, $window));
    }

    private function push(string $ip, int $window): void
    {
        $now = time();
        $timestamps = $this->trim($this->read($ip, $window), $now, $window);
        $timestamps[] = $now;

        set_transient($this->key($ip, $window), $timestamps, $window + MINUTE_IN_SECONDS);
    }

    /**
     * @return int[]
     */
    private function read(string $ip, int $window): array
    {
        $stored = get_transient($this->key($ip, $window));

        if (! is_array($stored)) {
            return [];
        }

        return $this->trim($stored, time(), $window);
    }

    /**
     * @param  mixed[]  $timestamps
     * @return int[]
     */
    private function trim(array $timestamps, int $now, int $window): array
    {
        $result = [];

        foreach ($timestamps as $timestamp) {
            if (is_numeric($timestamp) && (int) $timestamp > $now - $window) {
                $result[] = (int) $timestamp;
            }
        }

        return $result;
    }

    private function key(string $ip, int $window): string
    {
        return 'wp_addon_as_'.$window.'_'.md5($ip);
    }
}
