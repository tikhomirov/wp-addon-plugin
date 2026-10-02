<?php

use WpAddon\Backup\Storage;
use WpAddon\Interfaces\ModuleInterface;

require_once dirname(__DIR__, 2).'/functions/Backup.php';

describe('Backup module', function () {
    it('implements ModuleInterface', function () {
        expect(class_exists('Backup'))->toBeTrue();
        expect(is_subclass_of('Backup', ModuleInterface::class))->toBeTrue();
    });

    it('declares five AJAX actions backed by existing handlers', function () {
        $actions = Backup::ajax_actions();

        expect($actions)->toHaveCount(5);

        foreach ($actions as $action => $handler) {
            expect($action)->toStartWith('wp_addon_backup_');
            expect(method_exists('Backup', $handler))->toBeTrue();
        }
    });

    it('keeps JS and PHP AJAX action names in sync', function () {
        $js = (string) file_get_contents(dirname(__DIR__, 2).'/assets/js/backup.js');

        preg_match_all('/wp_addon_backup_[a-z]+/', $js, $matches);
        $jsActions = array_values(array_unique($matches[0]));
        sort($jsActions);

        $phpActions = array_keys(Backup::ajax_actions());
        sort($phpActions);

        expect($jsActions)->toBe($phpActions);
    });

    it('renders settings content with every element ID used by JS', function () {
        global $mock_user_capabilities, $wpdb;

        $mock_user_capabilities = ['manage_options'];
        $wpdb = new class
        {
            public string $prefix = 'wp_';
        };

        $dir = sys_get_temp_dir().'/wp_addon_backup_test_'.uniqid();
        mkdir($dir.'/backup', 0777, true);
        file_put_contents($dir.'/backup/backup_test.sql', "-- test\n");

        try {
            $html = Backup::render_settings_content(new Storage($dir));

            foreach ([
                'wp-addon-backup-create',
                'wp-addon-backup-progress',
                'wp-addon-backup-status',
                'wp-addon-backup-list',
                'wp-addon-backup-select',
                'wp-addon-backup-restore',
                'wp-addon-backup-upload',
                'wp-addon-backup-upload-btn',
            ] as $id) {
                expect($html)->toContain('id="'.$id.'"');
            }

            expect($html)->toContain('backup_test.sql');
            expect($html)->toContain('wp-addon-backup-delete');
            expect($html)->toContain('wp_');
        } finally {
            unlink($dir.'/backup/backup_test.sql');
            rmdir($dir.'/backup');
            rmdir($dir);
        }
    });

    it('shows the empty-state message when no dumps exist', function () {
        $dir = sys_get_temp_dir().'/wp_addon_backup_empty_'.uniqid();
        mkdir($dir, 0777, true);

        try {
            $html = Backup::render_settings_content(new Storage($dir));

            expect($html)->toContain('Файлов дампов пока нет.');
        } finally {
            rmdir($dir);
        }
    });
});
