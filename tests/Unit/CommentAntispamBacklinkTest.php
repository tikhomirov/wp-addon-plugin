<?php

use WpAddon\Services\CommentAntispamBacklinkService;

describe('CommentAntispamBacklinkService', function () {
    beforeEach(function () {
        $this->service = new CommentAntispamBacklinkService;
    });

    it('accepts a page with a plain backlink', function () {
        $html = '<div><p>Read my article</p><a href="https://rwsite.ru/some-post/">here</a></div>';

        expect($this->service->hasBacklink($html, 'rwsite.ru'))->toBeTrue();
    });

    it('accepts a www backlink and a relative anchor', function () {
        expect($this->service->hasBacklink('<a href="//www.rwsite.ru/page/">x</a>', 'rwsite.ru'))->toBeTrue();
        expect($this->service->hasBacklink("<a href='http://rwsite.ru'>x</a>", 'rwsite.ru'))->toBeTrue();
    });

    it('rejects a page without a backlink', function () {
        expect($this->service->hasBacklink('<p>Just a text, no links at all</p>', 'rwsite.ru'))->toBeFalse();
    });

    it('rejects a link to a lookalike or unrelated domain', function () {
        expect($this->service->hasBacklink('<a href="https://not-rwsite.ru/x/">x</a>', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru.evil.com/x/">x</a>', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('rwsite.ru', 'rwsite.ru'))->toBeFalse();
    });

    it('rejects a host that only starts with the site host', function () {
        expect($this->service->hasBacklink('<a href="https://rwsite.rus/x/">x</a>', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('<a href="https://rwsite.rusp/x/">x</a>', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru\\evil">x</a>', 'rwsite.ru'))->toBeFalse();
    });

    it('accepts a host followed by a path, a fragment or whitespace', function () {
        expect($this->service->hasBacklink('<a href="https://rwsite.ru">x</a>', 'rwsite.ru'))->toBeTrue();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru/ok/">x</a>', 'rwsite.ru'))->toBeTrue();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru#top">x</a>', 'rwsite.ru'))->toBeTrue();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru" >x</a>', 'rwsite.ru'))->toBeTrue();
    });

    it('falls back to the site host when none is given', function () {
        expect($this->service->hasBacklink('<a href="http://localhost/hello/">x</a>'))->toBeTrue();
        expect($this->service->hasBacklink('<a href="http://example.com/x">x</a>'))->toBeFalse();
    });

    it('does not count plain text as a backlink', function () {
        expect($this->service->hasBacklink('go to rwsite.ru please', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('<p>rwsite.ru</p>', 'rwsite.ru'))->toBeFalse();
    });

    it('rejects an empty body and a missing host', function () {
        expect($this->service->hasBacklink('', 'rwsite.ru'))->toBeFalse();
        expect($this->service->hasBacklink('<a href="https://rwsite.ru/">x</a>', ''))->toBeFalse();
    });

    it('rejects a pingback source without a url', function () {
        expect($this->service->isPingbackSourceValid(''))->toBeFalse();
        expect($this->service->isPingbackSourceValid('   '))->toBeFalse();
    });

    it('rejects a pingback source it cannot reach', function () {
        global $mock_http_bodies;
        $mock_http_bodies = [];

        expect($this->service->isPingbackSourceValid('https://example.org/gone'))->toBeFalse();
    });

    it('rejects a reachable pingback source without a backlink', function () {
        global $mock_http_bodies;
        $mock_http_bodies = [
            'https://example.org/post' => '<p>no links at all here</p>',
        ];

        expect($this->service->isPingbackSourceValid('https://example.org/post'))->toBeFalse();
    });

    it('accepts a reachable pingback source that links back', function () {
        global $mock_http_bodies;
        $mock_http_bodies = [
            'https://example.org/post' => '<div><a href="http://localhost/blog/">read</a></div>',
        ];

        expect($this->service->isPingbackSourceValid('https://example.org/post'))->toBeTrue();
    });
});
