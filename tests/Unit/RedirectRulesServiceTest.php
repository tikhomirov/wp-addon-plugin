<?php

use WpAddon\Services\RedirectRulesService;

describe('RedirectRulesService', function () {
    it('parseCsv skips comments and blank lines', function () {
        $csv = <<<'CSV'
# header comment
// also comment
; semicolon comment

/old-a/,/new-a/
/old-b/;/new-b/
CSV;

        $rules = RedirectRulesService::parseCsv($csv);

        expect($rules)->toHaveCount(2);
        expect($rules[0])->toBe(['request' => '/old-a/', 'destination' => '/new-a/']);
        expect($rules[1])->toBe(['request' => '/old-b/', 'destination' => '/new-b/']);
    });

    it('normalizeRules parses compact rule line field', function () {
        $rules = RedirectRulesService::normalizeRules([
            ['rule' => '/old-a/, /new-a/'],
            ['rule' => '/old-b/;/new-b/'],
            ['request' => '/legacy/', 'destination' => '/still/'],
        ]);

        expect($rules)->toHaveCount(3);
        expect($rules[0])->toBe(['request' => '/old-a/', 'destination' => '/new-a/']);
        expect($rules[2])->toBe(['request' => '/legacy/', 'destination' => '/still/']);
    });

    it('prepareOptionsForRedirectsAdmin maps stored rules to rule lines', function () {
        $options = RedirectRulesService::prepareOptionsForRedirectsAdmin([
            'redirects_rules' => [
                ['request' => '/a/', 'destination' => '/b/'],
            ],
        ]);

        expect($options['redirects_rules'][0])->toBe(['rule' => '/a/,/b/']);
    });

    it('mergeRules overwrites duplicate sources', function () {
        $existing = [
            ['request' => '/a/', 'destination' => '/b/'],
        ];
        $imported = [
            ['request' => '/a/', 'destination' => '/c/'],
            ['request' => '/x/', 'destination' => '/y/'],
        ];

        $merged = RedirectRulesService::mergeRules($existing, $imported);

        expect($merged)->toHaveCount(2);
        $first = array_values(array_filter($merged, fn (array $r): bool => $r['request'] === '/a/'))[0];
        expect($first['destination'])->toBe('/c/');
    });
});