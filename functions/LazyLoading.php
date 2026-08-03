<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\OptionService;
use WpAddon\Traits\HookTrait;

/**
 * Simple Lazy Loading module for images
 */
class LazyLoading implements ModuleInterface
{
    use HookTrait;

    private OptionService $optionService;

    private bool $enabled;

    public function __construct(OptionService $optionService)
    {
        $this->optionService = $optionService;
        $this->enabled = $this->optionService->getSetting('enable_lazy_loading', false);
    }

    public function init(): void
    {
        if (! $this->enabled || (function_exists('is_admin') && is_admin())) {
            return;
        }

        $this->addHook('the_content', [$this, 'processContent']);
        $this->addHook('wp_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    public function processContent(string $content): string
    {
        return preg_replace_callback(
            '~<img\s+([^>]+)>~i',
            [$this, 'processImage'],
            $content
        );
    }

    private function processImage(array $matches): string
    {
        $img = $matches[0];
        $attrs = $this->parseAttributes($matches[1]);

        if (empty($attrs['src']) || isset($attrs['data-src'])) {
            return $img;
        }

        $src = $attrs['src'];

        // Skip SVG, data URLs, and no-lazy images
        if ($this->shouldSkip($src, $attrs)) {
            return $img;
        }

        // Build lazy attributes
        $newAttrs = $attrs;
        $newAttrs['data-src'] = $src;
        unset($newAttrs['src']);
        if (! empty($attrs['srcset'])) {
            $newAttrs['data-srcset'] = $attrs['srcset'];
            unset($newAttrs['srcset']);
        }
        $classes = preg_split('/\s+/', trim($attrs['class'] ?? '')) ?: [];
        $classes[] = 'lazy-img';
        $newAttrs['class'] = implode(' ', array_unique(array_filter($classes)));
        $newAttrs['loading'] = 'lazy';

        return '<img'.$this->buildAttributes($newAttrs).'>';
    }

    private function shouldSkip(string $src, array $attrs): bool
    {
        return (bool) preg_match('/\.svg(?:[?#]|$)/i', $src)
            || stripos($src, 'data:') === 0
            || (isset($attrs['class']) && in_array('no-lazy', preg_split('/\s+/', $attrs['class']) ?: [], true));
    }

    private function parseAttributes(string $attrString): array
    {
        $attrs = [];
        preg_match_all('/([^\s=\/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', $attrString, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] !== '' ? $match[4] : $name));
            $attrs[$name] = $value;
        }

        return $attrs;
    }

    private function buildAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            $escapedValue = function_exists('esc_attr')
                ? esc_attr($value)
                : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = $name.'="'.$escapedValue.'"';
        }

        return ' '.implode(' ', $parts);
    }

    public function enqueueScripts(): void
    {
        wp_enqueue_script(
            'lazy-loading',
            RW_PLUGIN_URL.'assets/js/lazy-loading.js',
            [],
            '1.0.0',
            true
        );
    }
}
