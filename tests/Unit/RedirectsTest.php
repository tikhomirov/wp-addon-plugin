<?php

require_once dirname(__DIR__, 2).'/functions/Redirects.php';

describe('Redirects', function () {
    it('imports csv rules on settings save', function () {
        $redirects = new Redirects;

        $data = $redirects->importCsvOnSave([
            'redirects_rules' => [
                ['request' => '/existing', 'destination' => '/target/'],
            ],
            'redirects_csv_import' => "/new-page/,/destination/\n# ignored\n/old/,/new/",
        ], null);

        expect($data['redirects_csv_import'])->toBe('');
        expect($data['redirects_rules'])->toBe([
            ['request' => '/existing', 'destination' => '/target/'],
            ['request' => '/new-page/', 'destination' => '/destination/'],
            ['request' => '/old/', 'destination' => '/new/'],
        ]);
    });

    it('normalizes invalid redirects_rules on save', function () {
        $redirects = new Redirects;

        $data = $redirects->importCsvOnSave([
            'redirects_rules' => '',
            'redirects_csv_import' => '',
        ], null);

        expect($data['redirects_rules'])->toBe([]);
    });
});
