<?php

use WpAddon\Services\CommentAntispamRateLimitService;

if (! function_exists('get_transient')) {
    function get_transient($key)
    {
        global $mock_transients;

        return $mock_transients[$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0)
    {
        global $mock_transients;
        $mock_transients[$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient($key)
    {
        global $mock_transients;
        unset($mock_transients[$key]);

        return true;
    }
}

describe('CommentAntispamRateLimitService', function () {
    beforeEach(function () {
        global $mock_transients;
        $mock_transients = [];
    });

    it('is disabled when both limits are zero', function () {
        global $mock_transients;
        $service = new CommentAntispamRateLimitService(0, 0);

        expect($service->isEnabled())->toBeFalse();
        expect($service->isExceeded('1.2.3.4'))->toBeFalse();

        $service->register('1.2.3.4');

        expect($mock_transients)->toBeEmpty();
    });

    it('blocks only after the hourly limit is reached', function () {
        $service = new CommentAntispamRateLimitService(3, 0);

        for ($i = 0; $i < 3; $i++) {
            expect($service->isExceeded('1.2.3.4'))->toBeFalse();
            $service->register('1.2.3.4');
        }

        expect($service->isExceeded('1.2.3.4'))->toBeTrue();
    });

    it('keeps counters per ip', function () {
        $service = new CommentAntispamRateLimitService(2, 0);
        $service->register('1.1.1.1');
        $service->register('1.1.1.1');

        expect($service->isExceeded('1.1.1.1'))->toBeTrue();
        expect($service->isExceeded('2.2.2.2'))->toBeFalse();
    });

    it('ignores an empty ip', function () {
        $service = new CommentAntispamRateLimitService(1, 0);

        expect($service->isExceeded(''))->toBeFalse();
    });

    it('drops timestamps that fell out of the window', function () {
        $service = new CommentAntispamRateLimitService(1, 0);
        $key = 'wp_addon_as_'.HOUR_IN_SECONDS.'_'.md5('9.9.9.9');

        set_transient($key, [time() - (2 * HOUR_IN_SECONDS)], HOUR_IN_SECONDS);

        expect($service->count('9.9.9.9', HOUR_IN_SECONDS))->toBe(0);
        expect($service->isExceeded('9.9.9.9'))->toBeFalse();
    });
});
