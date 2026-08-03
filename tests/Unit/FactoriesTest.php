<?php

use WpAddon\Tests\Factories\AssetFactory;
use WpAddon\Tests\Factories\PostFactory;

describe('Factories', function () {
    beforeEach(function () {
        global $db;
        if (! isset($db)) {
            $db = new PDO('sqlite::memory:');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    });
    it('creates posts with PostFactory', function () {
        $factory = new PostFactory;
        $post = $factory->create(['post_title' => 'Test Post']);

        expect($post)->toBeInt();
        expect($post)->toBeGreaterThan(0);
    });

    it('creates multiple posts', function () {
        $factory = new PostFactory;
        $posts = $factory->createMany(3);

        expect($posts)->toBeArray();
        expect($posts)->toHaveCount(3);

        foreach ($posts as $postId) {
            expect($postId)->toBeInt();
            expect($postId)->toBeGreaterThan(0);
        }
    });

    it('creates assets with AssetFactory', function () {
        $factory = new AssetFactory;
        $asset = $factory->create(['handle' => 'test-asset']);

        expect($asset)->toBeArray();
        expect($asset)->toHaveKey('handle');
        expect($asset['handle'])->toBe('test-asset');
    });

    it('creates CSS assets', function () {
        $factory = new AssetFactory;
        $factory->asCss();
        $asset = $factory->create();

        expect($asset)->toBeArray();
        expect($asset)->toHaveKey('type');
        expect($asset['type'])->toBe('style');
    });

    it('creates JS assets', function () {
        $factory = new AssetFactory;
        $factory->asJs();
        $asset = $factory->create();

        expect($asset)->toBeArray();
        expect($asset)->toHaveKey('type');
        expect($asset['type'])->toBe('script');
    });
});
