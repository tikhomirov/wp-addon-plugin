<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\AjaxTrait;
use WpAddon\Traits\WidgetTrait;

describe('Module System', function () {
    it('has ModuleInterface', function () {
        expect(interface_exists('WpAddon\Interfaces\ModuleInterface'))->toBeTrue();
    });

    it('has traits', function () {
        expect(trait_exists('WpAddon\Traits\AjaxTrait'))->toBeTrue();
        expect(trait_exists('WpAddon\Traits\WidgetTrait'))->toBeTrue();
        expect(trait_exists('WpAddon\Traits\HookTrait'))->toBeTrue();
    });

    it('AjaxTrait has methods', function () {
        $mock = new class implements ModuleInterface
        {
            use AjaxTrait;

            public function init(): void {}

            public function handleAjax(): void {}
        };

        expect(method_exists($mock, 'registerAjax'))->toBeTrue();
    });

    it('WidgetTrait has methods', function () {
        $mock = new class implements ModuleInterface
        {
            use WidgetTrait;

            public function init(): void {}

            public function widget($args, $instance): void {}
        };

        expect(method_exists($mock, 'registerWidget'))->toBeTrue();
    });

    it('Redirects module works', function () {
        require_once dirname(__DIR__, 2).'/functions/Redirects.php';

        expect(class_exists('Redirects'))->toBeTrue();
        expect(is_subclass_of('Redirects', 'WpAddon\Interfaces\ModuleInterface'))->toBeTrue();

        $redirects = new Redirects;
        expect($redirects)->toBeInstanceOf(ModuleInterface::class);
        expect(method_exists($redirects, 'init'))->toBeTrue();
    });
});
