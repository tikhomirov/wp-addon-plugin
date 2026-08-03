<?php

namespace WpAddon\Tests;

/**
 * Database migrations trait for test isolation
 */
trait DatabaseMigrations
{
    /**
     * Run database migrations before each test
     */
    protected function runDatabaseMigrations(): void
    {
        global $db;

        if (! $db) {
            return; // Skip if database not initialized
        }

        // Clear all tables
        $db->exec('DELETE FROM wp_options');
        $db->exec('DELETE FROM wp_posts');
        $db->exec('DELETE FROM wp_postmeta');
        $db->exec('DELETE FROM wp_users');
        $db->exec('DELETE FROM wp_usermeta');

        // Reset auto-increment counters
        $db->exec('DELETE FROM sqlite_sequence');

        // Insert default WordPress options
        $this->seedDefaultOptions();
    }

    /**
     * Clean up database after each test
     */
    protected function cleanupDatabase(): void
    {
        // The in-memory database will be recreated for each test
        // so no explicit cleanup is needed
    }

    /**
     * Seed default WordPress options
     */
    private function seedDefaultOptions(): void
    {
        global $db;

        if (! $db) {
            return; // Skip if database not initialized
        }

        $defaultOptions = [
            ['option_name' => 'siteurl', 'option_value' => 'http://localhost'],
            ['option_name' => 'home', 'option_value' => 'http://localhost'],
            ['option_name' => 'blogname', 'option_value' => 'Test Site'],
            ['option_name' => 'blogdescription', 'option_value' => 'Just another test site'],
            ['option_name' => 'admin_email', 'option_value' => 'admin@test.com'],
            ['option_name' => 'template', 'option_value' => 'twentytwentyone'],
            ['option_name' => 'stylesheet', 'option_value' => 'twentytwentyone'],
        ];

        $stmt = $db->prepare('INSERT INTO wp_options (option_name, option_value) VALUES (?, ?)');
        foreach ($defaultOptions as $option) {
            $stmt->execute([$option['option_name'], $option['option_value']]);
        }
    }

    protected function createPost(array $attributes = []): int
    {
        global $db;

        if (! $db) {
            throw new \Exception('Database not initialized');
        }

        $defaults = [
            'post_author' => 1,
            'post_date' => date('Y-m-d H:i:s'),
            'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            'post_content' => 'Test post content',
            'post_title' => 'Test Post',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'test-post',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => date('Y-m-d H:i:s'),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => 'http://localhost/?p=1',
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0,
        ];

        $data = array_merge($defaults, $attributes);

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
     * Create a test user
     */
    protected function createUser(array $attributes = []): int
    {
        global $db;

        if (! $db) {
            throw new \Exception('Database not initialized');
        }

        $defaults = [
            'user_login' => 'testuser',
            'user_pass' => 'password',
            'user_nicename' => 'testuser',
            'user_email' => 'test@example.com',
            'user_url' => '',
            'user_registered' => date('Y-m-d H:i:s'),
            'user_activation_key' => '',
            'user_status' => 0,
            'display_name' => 'Test User',
        ];

        $data = array_merge($defaults, $attributes);

        $stmt = $db->prepare('
            INSERT INTO wp_users (
                user_login, user_pass, user_nicename, user_email, user_url,
                user_registered, user_activation_key, user_status, display_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute(array_values($data));

        return $db->lastInsertId();
    }
}
