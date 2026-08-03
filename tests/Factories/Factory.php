<?php

namespace WpAddon\Tests\Factories;

use Faker\Factory as FakerFactory;
use Faker\Generator;

/**
 * Base factory class for test data generation
 */
abstract class Factory
{
    protected Generator $faker;

    protected array $attributes = [];

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    /**
     * Create a single instance
     */
    public function create(array $attributes = []): mixed
    {
        $data = array_merge($this->definition(), $attributes);

        return $this->createInstance($data);
    }

    /**
     * Create multiple instances
     */
    public function createMany(int $count, array $attributes = []): array
    {
        $instances = [];
        for ($i = 0; $i < $count; $i++) {
            $instances[] = $this->create($attributes);
        }

        return $instances;
    }

    /**
     * Get the default attributes for the model
     */
    abstract protected function definition(): array;

    /**
     * Create the actual instance
     */
    abstract protected function createInstance(array $data): mixed;
}
