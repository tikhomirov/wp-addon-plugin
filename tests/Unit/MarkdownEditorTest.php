<?php

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('metadata_exists')) {
    function metadata_exists($meta_type, $object_id, $meta_key)
    {
        global $mock_metadata;

        return isset($mock_metadata[$meta_type][$object_id][$meta_key]);
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '')
    {
        global $mock_metadata;
        unset($mock_metadata['post'][$post_id][$meta_key]);

        return true;
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postarr)
    {
        global $mock_updated_posts;
        $mock_updated_posts[] = $postarr;

        return $postarr['ID'];
    }
}

function markdown_test_post(string $content, string $postType = 'post', int $id = 1): stdClass
{
    return (object) [
        'ID' => $id,
        'post_type' => $postType,
        'post_content' => $content,
    ];
}

describe('MarkdownEditor Unit Tests', function () {
    beforeEach(function () {
        $settings = [
            'wp_addon_markdown_enabled' => '1',
            'markdown_replace_tinymce' => '1',
            'markdown_post_types' => ['post', 'page'],
            'markdown_enable_shortcuts' => '1',
            'markdown_enable_preview' => '1',
        ];

        $GLOBALS['mock_functions']['get_option'] = function ($key, $default = []) use ($settings) {
            return $key === 'wp-addon' ? $settings : $default;
        };

        $GLOBALS['mock_metadata'] = [];
        $GLOBALS['mock_updated_posts'] = [];

        $_POST['markdown_nonce'] = 'test_nonce_save_markdown_content';
        $_POST['markdown_content'] = '';

        $this->editor = new MarkdownEditor;
    });

    afterEach(function () {
        unset($GLOBALS['mock_functions']['get_option']);
        unset($GLOBALS['mock_metadata'], $GLOBALS['mock_updated_posts']);
        unset($_POST['markdown_nonce'], $_POST['markdown_content']);
    });

    it('converts markdown to html with the fallback parser', function () {
        $html = $this->editor->parseMarkdown("# Heading\n\n**bold** and *italic* text");

        expect($html)->toContain('<h1>Heading</h1>');
        expect($html)->toContain('<strong>bold</strong>');
        expect($html)->toContain('<em>italic</em>');
    });

    it('overwrites post_content with parsed html when markdown was edited', function () {
        $before = markdown_test_post('<p>Old content</p>');
        $after = markdown_test_post('<p>Old content</p>');
        $_POST['markdown_content'] = "# New title\n\nNew text";

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toHaveCount(1);
        $updated = $GLOBALS['mock_updated_posts'][0];
        expect($updated['ID'])->toBe(1);
        expect($updated['post_content'])->toContain('<h1>New title</h1>');
        expect($updated['post_content'])->toContain('New text');
    });

    it('does not overwrite html when markdown was not touched', function () {
        $before = markdown_test_post('<p>Original</p>');
        $after = markdown_test_post('<p>Original edited in the standard editor</p>');
        $_POST['markdown_content'] = 'Original';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('keeps tinyMCE-only edits when markdown matches the old content', function () {
        $oldHtml = '<h2>Title</h2><p>Body</p>';
        $htmlToMarkdown = new ReflectionMethod(MarkdownEditor::class, 'htmlToMarkdown');
        $before = markdown_test_post($oldHtml);
        $after = markdown_test_post('<h2>Title</h2><p>Body changed</p>');
        $_POST['markdown_content'] = $htmlToMarkdown->invoke($this->editor, $oldHtml);

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('removes stale markdown meta even when the field was not edited', function () {
        $GLOBALS['mock_metadata']['post'][1]['_markdown_content'] = 'stale markdown';
        $before = markdown_test_post('<p>Original</p>');
        $after = markdown_test_post('<p>Original</p>');
        $_POST['markdown_content'] = 'Original';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect(metadata_exists('post', 1, '_markdown_content'))->toBeFalse();
    });

    it('does not store a new markdown meta value on save', function () {
        $_POST['markdown_content'] = "# Fresh title\n\nBody";
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_metadata']['post'][1] ?? [])->toBe([]);
    });

    it('does nothing when markdown is disabled', function () {
        $GLOBALS['mock_functions']['get_option'] = function ($key, $default = []) {
            return $key === 'wp-addon' ? ['wp_addon_markdown_enabled' => '0'] : $default;
        };
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('does nothing for post types not enabled in settings', function () {
        $GLOBALS['mock_functions']['get_option'] = function ($key, $default = []) {
            return $key === 'wp-addon'
                ? ['wp_addon_markdown_enabled' => '1', 'markdown_post_types' => ['page']]
                : $default;
        };
        $GLOBALS['mock_metadata']['post'][1]['_markdown_content'] = 'stale markdown';
        $before = markdown_test_post('<p>Old</p>', 'post');
        $after = markdown_test_post('<p>Old</p>', 'post');
        $_POST['markdown_content'] = '# Edited';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
        expect(metadata_exists('post', 1, '_markdown_content'))->toBeTrue();
    });

    it('does nothing when the nonce is invalid', function () {
        $_POST['markdown_nonce'] = 'invalid_nonce';
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('does not wipe html when the markdown field is sent empty', function () {
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('converts markdown to html on the first publish', function () {
        $post = markdown_test_post('', 'post', 3);
        $_POST['markdown_content'] = '# Hello';

        $this->editor->saveMarkdownOnFirstPublish(3, $post, false);

        expect($GLOBALS['mock_updated_posts'])->toHaveCount(1);
        expect($GLOBALS['mock_updated_posts'][0]['post_content'])->toContain('<h1>Hello</h1>');
    });

    it('ignores the first publish handler for existing posts', function () {
        $post = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';

        $this->editor->saveMarkdownOnFirstPublish(1, $post, true);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });
});
