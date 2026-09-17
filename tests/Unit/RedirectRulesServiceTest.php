<?php

use WpAddon\Services\RedirectRulesService;

describe('RedirectRulesService', function () {
    it('normalizes invalid rules storage to an empty array', function () {
        expect(RedirectRulesService::normalizeRules(''))->toBe([]);
        expect(RedirectRulesService::normalizeRules(null))->toBe([]);
        expect(RedirectRulesService::normalizeRules(['not-an-array']))->toBe([]);
    });

    it('normalizes repeater rules and request paths', function () {
        $rules = RedirectRulesService::normalizeRules([
            ['request' => 'old-page', 'destination' => '/new-page/'],
            ['request' => '', 'destination' => '/skip-me/'],
            ['request' => '/valid', 'destination' => ''],
        ]);

        expect($rules)->toBe([
            ['request' => '/old-page', 'destination' => '/new-page/'],
        ]);
    });

    it('parses csv lines with different separators', function () {
        $csv = <<<'CSV'
# comment
/old-a/,/new-a/
/old-b/;/new-b/
/old-c/	/new-c/
request,destination
/old-d/,/new-d/
CSV;

        $rules = RedirectRulesService::parseCsv($csv);

        expect($rules)->toBe([
            ['request' => '/old-a/', 'destination' => '/new-a/'],
            ['request' => '/old-b/', 'destination' => '/new-b/'],
            ['request' => '/old-c/', 'destination' => '/new-c/'],
            ['request' => '/old-d/', 'destination' => '/new-d/'],
        ]);
    });

    it('merges imported rules and overwrites duplicates by request', function () {
        $merged = RedirectRulesService::mergeRules(
            [
                ['request' => '/old-page', 'destination' => '/first/'],
                ['request' => '/keep-me', 'destination' => '/same/'],
            ],
            [
                ['request' => '/old-page', 'destination' => '/second/'],
                ['request' => '/new-page', 'destination' => '/target/'],
            ]
        );

        expect($merged)->toBe([
            ['request' => '/old-page', 'destination' => '/second/'],
            ['request' => '/keep-me', 'destination' => '/same/'],
            ['request' => '/new-page', 'destination' => '/target/'],
        ]);
    });

    it('builds redirect map from mixed storage', function () {
        expect(RedirectRulesService::toMap(''))->toBe([]);
        expect(RedirectRulesService::toMap([
            ['request' => '/from', 'destination' => '/to'],
        ]))->toBe([
            '/from' => '/to',
        ]);
    });
});
