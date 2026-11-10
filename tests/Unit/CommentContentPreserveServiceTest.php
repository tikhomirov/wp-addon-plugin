<?php

use WpAddon\Services\CommentContentPreserveService;

describe('CommentContentPreserveService', function () {
    it('leaves plain text unchanged', function () {
        $input = 'x-text="location" and a < b';

        expect(CommentContentPreserveService::escapeDisallowedTags($input))->toBe($input);
    });

    it('keeps allowed formatting tags', function () {
        $input = 'Use <code>x-text="location"</code> in Alpine.';

        expect(CommentContentPreserveService::escapeDisallowedTags($input))->toBe($input);
    });

    it('escapes disallowed tags as entities for safe storage', function () {
        $raw = '<p x-text="\'count\'"></p>';
        $escaped = CommentContentPreserveService::escapeDisallowedTags($raw);

        expect($escaped)->toBe('&lt;p x-text=&quot;&#039;count&#039;&quot;&gt;&lt;/p&gt;');
    });

    it('preserves alpine-style attribute quotes in plain text', function () {
        $raw = 'Alpine uses x-text="location" not {{ name }}.';

        expect(CommentContentPreserveService::escapeDisallowedTags($raw))->toBe($raw);
    });
});
