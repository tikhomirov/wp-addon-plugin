<?php

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false)
    {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $string);
        $string = strip_tags($string);

        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}

if (! function_exists('get_post')) {
    function get_post($post = null, $output = 'OBJECT', $filter = 'raw')
    {
        global $mock_posts;

        if (is_object($post)) {
            return $post;
        }

        $post_id = (int) $post;

        return $mock_posts[$post_id] ?? null;
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

if (! function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
    {
        global $mock_metadata;
        $mock_metadata['post'][$post_id][$meta_key] = $meta_value;

        return true;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        global $mock_metadata;
        if (! empty($key)) {
            return $mock_metadata['post'][$post_id][$key] ?? '';
        }

        return $mock_metadata['post'][$post_id] ?? [];
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postarr)
    {
        global $mock_updated_posts, $mock_posts;
        $mock_updated_posts[] = $postarr;

        if (isset($postarr['ID'], $postarr['post_content'])) {
            $id = (int) $postarr['ID'];
            if (! isset($mock_posts[$id])) {
                $mock_posts[$id] = markdown_test_post($postarr['post_content'], 'post', $id);
            } else {
                $mock_posts[$id]->post_content = $postarr['post_content'];
            }
        }

        return $postarr['ID'];
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true)
    {
        return true;
    }
}

if (! function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null)
    {
        throw new Exception('JSON_SUCCESS: '.json_encode($data));
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null)
    {
        throw new Exception('JSON_ERROR: '.json_encode($data));
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
        $GLOBALS['mock_posts'] = [];

        $_POST['markdown_nonce'] = 'test_nonce_save_markdown_content';
        $_POST['markdown_content'] = '';
        $_POST['markdown_edited'] = '0';
        unset($_POST['wp_addon_editor_mode']);

        $this->editor = new MarkdownEditor;
    });

    afterEach(function () {
        unset($GLOBALS['mock_functions']['get_option']);
        unset($GLOBALS['mock_metadata'], $GLOBALS['mock_updated_posts'], $GLOBALS['mock_posts']);
        unset($_POST['markdown_nonce'], $_POST['markdown_content'], $_POST['markdown_edited'], $_POST['wp_addon_editor_mode']);
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
        $_POST['markdown_edited'] = '1';

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

    it('does not overwrite html edits even if markdown field still differs', function () {
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Edited in HTML editor</p>');
        // Устаревший MD в форме, но без флага правки — HTML должен победить.
        $_POST['markdown_content'] = "# Stale markdown\n\nShould not win";
        $_POST['markdown_edited'] = '0';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('applies markdown when the edited flag is set even if html also changed', function () {
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Also touched in HTML</p>');
        $_POST['markdown_content'] = '# From markdown';
        $_POST['markdown_edited'] = '1';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toHaveCount(1);
        expect($GLOBALS['mock_updated_posts'][0]['post_content'])->toContain('<h1>From markdown</h1>');
    });

    it('removes stale markdown meta even when the field was not edited', function () {
        $GLOBALS['mock_metadata']['post'][1]['_markdown_content'] = 'stale markdown';
        $before = markdown_test_post('<p>Original</p>');
        $after = markdown_test_post('<p>Original</p>');
        $_POST['markdown_content'] = 'Original';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect(metadata_exists('post', 1, '_markdown_content'))->toBeFalse();
    });

    it('stores markdown meta value when saved in markdown mode', function () {
        $_POST['wp_addon_editor_mode'] = 'markdown';
        $_POST['markdown_content'] = "# Fresh title\n\nBody";
        $_POST['markdown_edited'] = '1';
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_metadata']['post'][1]['_markdown_content'] ?? null)->toBe("# Fresh title\n\nBody");
        expect($GLOBALS['mock_metadata']['post'][1]['_wp_addon_editor_mode'] ?? null)->toBe('markdown');
    });

    it('clears markdown meta and preserves html when saved in classic mode', function () {
        $GLOBALS['mock_metadata']['post'][1]['_markdown_content'] = '# Old markdown';
        $_POST['wp_addon_editor_mode'] = 'classic';
        $_POST['markdown_content'] = '';
        $_POST['markdown_edited'] = '1';
        $before = markdown_test_post('<p>Old html</p>');
        $after = markdown_test_post('<p>New html from classic editor</p>');

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect(metadata_exists('post', 1, '_markdown_content'))->toBeFalse();
        expect($GLOBALS['mock_metadata']['post'][1]['_wp_addon_editor_mode'] ?? null)->toBe('classic');
        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('returns classic mode for existing post with html content', function () {
        $post = markdown_test_post('<p>Existing post</p>');
        $mode = $this->editor->getPostEditorMode(1, $post);
        expect($mode)->toBe('classic');
    });

    it('returns empty markdown content when in classic mode so it does not falsely restore', function () {
        $post = markdown_test_post('<h2>Title</h2><p>Content</p>');
        $content = $this->editor->getPostMarkdownContent(1, $post, 'classic');
        expect($content)->toBe('');
    });

    it('returns stored markdown content when in markdown mode', function () {
        $GLOBALS['mock_metadata']['post'][1]['_markdown_content'] = '# Hello markdown';
        $post = markdown_test_post('<h1>Hello markdown</h1>');
        $content = $this->editor->getPostMarkdownContent(1, $post, 'markdown');
        expect($content)->toBe('# Hello markdown');
    });

    it('does nothing when markdown is disabled', function () {
        $GLOBALS['mock_functions']['get_option'] = function ($key, $default = []) {
            return $key === 'wp-addon' ? ['wp_addon_markdown_enabled' => '0'] : $default;
        };
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';
        $_POST['markdown_edited'] = '1';

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
        $_POST['markdown_edited'] = '1';

        $this->editor->saveMarkdownContent(1, $after, $before);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
        expect(metadata_exists('post', 1, '_markdown_content'))->toBeTrue();
    });

    it('does nothing when the nonce is invalid', function () {
        $_POST['markdown_nonce'] = 'invalid_nonce';
        $before = markdown_test_post('<p>Old</p>');
        $after = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';
        $_POST['markdown_edited'] = '1';

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
        $_POST['markdown_edited'] = '1';

        $this->editor->saveMarkdownOnFirstPublish(3, $post, false);

        expect($GLOBALS['mock_updated_posts'])->toHaveCount(1);
        expect($GLOBALS['mock_updated_posts'][0]['post_content'])->toContain('<h1>Hello</h1>');
    });

    it('ignores the first publish handler for existing posts', function () {
        $post = markdown_test_post('<p>Old</p>');
        $_POST['markdown_content'] = '# Edited';
        $_POST['markdown_edited'] = '1';

        $this->editor->saveMarkdownOnFirstPublish(1, $post, true);

        expect($GLOBALS['mock_updated_posts'])->toBe([]);
    });

    it('converts html to markdown via ajax', function () {
        $_POST['direction'] = 'html_to_md';
        $_POST['content'] = '<h1>Test Title</h1><p>Test body</p>';

        try {
            $this->editor->ajaxConvertContent();
            expect(true)->toBeFalse();
        } catch (Exception $e) {
            expect($e->getMessage())->toContain('JSON_SUCCESS');
            expect($e->getMessage())->toContain('# Test Title');
        }
    });

    it('converts markdown to html via ajax', function () {
        $_POST['direction'] = 'md_to_html';
        $_POST['content'] = "# Test Title\n\nTest body";

        try {
            $this->editor->ajaxConvertContent();
            expect(true)->toBeFalse();
        } catch (Exception $e) {
            expect($e->getMessage())->toContain('JSON_SUCCESS');
            expect($e->getMessage())->toContain('Test Title');
            expect($e->getMessage())->toContain('<p>Test body');
        }
    });
});
