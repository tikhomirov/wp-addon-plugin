<?php

namespace WpAddon\Tests\Factories;

/**
 * Factory for creating test asset data
 */
class AssetFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'handle' => $this->faker->word().'-asset',
            'src' => $this->faker->url().'/assets/'.$this->faker->word().'.'.$this->faker->randomElement(['css', 'js']),
            'deps' => [],
            'ver' => $this->faker->randomFloat(1, 1, 9),
            'media' => 'all',
            'type' => $this->attributes['type'] ?? $this->faker->randomElement(['style', 'script']),
            'content' => $this->generateAssetContent(),
            'size' => $this->faker->numberBetween(1000, 50000),
        ];
    }

    protected function createInstance(array $data): array
    {
        // Return the asset data array for testing
        return $data;
    }

    /**
     * Generate sample CSS or JS content
     */
    private function generateAssetContent(): string
    {
        $type = $this->attributes['type'] ?? $this->faker->randomElement(['style', 'script']);

        if ($type === 'script') {
            return $this->generateJsContent();
        }

        return $this->generateCssContent();
    }

    /**
     * Generate sample CSS content
     */
    private function generateCssContent(): string
    {
        return sprintf(
            ".%s {\n    color: %s;\n    font-size: %dpx;\n    margin: %dpx;\n}\n\n.%s:hover {\n    background-color: %s;\n}",
            $this->faker->word(),
            $this->faker->hexColor(),
            $this->faker->numberBetween(12, 24),
            $this->faker->numberBetween(0, 20),
            $this->faker->word(),
            $this->faker->hexColor()
        );
    }

    /**
     * Generate sample JS content
     */
    private function generateJsContent(): string
    {
        return sprintf(
            "(function() {\n    'use strict';\n\n    var %s = '%s';\n    var %s = %d;\n\n    function %s() {\n        console.log(%s + ' ' + %s);\n    }\n\n    %s();\n})();",
            $this->faker->word(),
            $this->faker->sentence(),
            $this->faker->word(),
            $this->faker->numberBetween(1, 100),
            $this->faker->word(),
            $this->faker->word(),
            $this->faker->word(),
            $this->faker->word()
        );
    }

    /**
     * Create a CSS asset
     */
    public function asCss(): self
    {
        return $this->state([
            'type' => 'style',
            'src' => $this->faker->url().'/assets/'.$this->faker->word().'.css',
            'content' => $this->generateCssContent(),
        ]);
    }

    /**
     * Create a JS asset
     */
    public function asJs(): self
    {
        return $this->state([
            'type' => 'script',
            'src' => $this->faker->url().'/assets/'.$this->faker->word().'.js',
            'content' => $this->generateJsContent(),
            'media' => null,
        ]);
    }

    /**
     * Create a minified asset
     */
    public function minified(): self
    {
        $content = $this->attributes['content'] ?? $this->generateAssetContent();
        $minifiedContent = str_replace(["\n", "\t", '  '], '', $content);

        return $this->state([
            'content' => $minifiedContent,
            'src' => str_replace('.css', '.min.css', $this->attributes['src'] ?? $this->faker->url().'/assets/'.$this->faker->word().'.css'),
        ]);
    }

    /**
     * Create a large asset
     */
    public function large(): self
    {
        return $this->state([
            'size' => $this->faker->numberBetween(50000, 200000),
            'content' => str_repeat($this->generateAssetContent(), 5),
        ]);
    }

    /**
     * Create a small asset
     */
    public function small(): self
    {
        return $this->state([
            'size' => $this->faker->numberBetween(100, 999),
            'content' => $this->faker->word().'{}',
        ]);
    }

    /**
     * Set custom state
     */
    protected function state(array $state): self
    {
        $this->attributes = array_merge($this->attributes, $state);

        return $this;
    }
}
