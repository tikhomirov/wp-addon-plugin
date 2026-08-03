<?php

namespace WpAddon\Tests\Factories;

/**
 * Factory for creating test posts
 */
class PostFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'post_author' => 1,
            'post_date' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            'post_date_gmt' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            'post_content' => $this->faker->paragraphs(3, true),
            'post_title' => $this->faker->sentence(),
            'post_excerpt' => $this->faker->paragraph(),
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => $this->faker->slug(),
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            'post_modified_gmt' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => $this->faker->url(),
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0,
        ];
    }

    protected function createInstance(array $data): int
    {
        global $db;

        $stmt = $db->prepare('
            INSERT INTO wp_posts (
                post_author, post_date, post_date_gmt, post_content, post_title,
                post_excerpt, post_status, comment_status, ping_status, post_password,
                post_name, to_ping, pinged, post_modified, post_modified_gmt,
                post_content_filtered, post_parent, guid, menu_order, post_type,
                post_mime_type, comment_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute(array_values($data));

        return $db->lastInsertId();
    }

    /**
     * Create a page instead of post
     */
    public function asPage(): self
    {
        return $this->state(['post_type' => 'page']);
    }

    /**
     * Create a draft post
     */
    public function asDraft(): self
    {
        return $this->state(['post_status' => 'draft']);
    }

    /**
     * Create a post with specific author
     */
    public function withAuthor(int $authorId): self
    {
        return $this->state(['post_author' => $authorId]);
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
