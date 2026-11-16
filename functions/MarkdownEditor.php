<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\HookTrait;

class MarkdownEditor implements ModuleInterface
{
    use HookTrait;

    private string $settings_prefix = 'wp-addon';

    private bool $saving = false;

    public function init(): void
    {
        if (! $this->isMarkdownEnabled()) {
            return;
        }

        $this->addHook('edit_form_after_title', [$this, 'renderEditorSwitcher'], 9999);
        $this->addHook('post_updated', [$this, 'saveMarkdownContent'], 10, 3);
        $this->addHook('save_post', [$this, 'saveMarkdownOnFirstPublish'], 10, 3);
        $this->addHook('admin_enqueue_scripts', [$this, 'enqueueMarkdownAssets']);
        $this->addHook('wp_ajax_markdown_convert', [$this, 'ajaxConvertContent']);
    }

    /**
     * Read setting from CSF
     */
    private function getSetting($key, $default = null)
    {
        $settings = get_option($this->settings_prefix, []);

        return $settings[$key] ?? $default;
    }

    /**
     * Whether Markdown is enabled
     */
    private function isMarkdownEnabled(): bool
    {
        return (bool) $this->getSetting('wp_addon_markdown_enabled', false);
    }

    /**
     * Kept for backward compatibility.
     */
    public function maybeDisableRichEdit($can_richedit)
    {
        return $can_richedit;
    }

    /**
     * Kept for backward compatibility.
     */
    public function disableVisualEditor(): void {}

    /**
     * Editor mode for a post ('classic' or 'markdown').
     *
     * Detection order:
     * 1. Saved `_wp_addon_editor_mode` meta.
     * 2. `_markdown_content` meta (post was created in Markdown).
     * 3. Existing HTML content opens in Classic to avoid breaking legacy posts.
     * 4. New empty posts use `markdown_replace_tinymce`.
     */
    public function getPostEditorMode(int $post_id, $post = null): string
    {
        if ($post_id > 0) {
            $mode = get_post_meta($post_id, '_wp_addon_editor_mode', true);
            if (! empty($mode) && in_array($mode, ['classic', 'markdown'], true)) {
                return $mode;
            }

            $markdown_meta = get_post_meta($post_id, '_markdown_content', true);
            if (! empty($markdown_meta)) {
                return 'markdown';
            }
        }

        if ($post && ! empty($post->post_content)) {
            return 'classic';
        }

        return $this->getSetting('markdown_replace_tinymce', false) ? 'markdown' : 'classic';
    }

    /**
     * Markdown source for the editor.
     *
     * IMPORTANT: in Classic (HTML) mode return empty string so Markdown does not
     * restore content and overwrite HTML on save.
     */
    public function getPostMarkdownContent(int $post_id, $post = null, string $mode = 'classic'): string
    {
        if ($mode === 'classic') {
            return '';
        }

        if ($post_id > 0) {
            $markdown_meta = get_post_meta($post_id, '_markdown_content', true);
            if (! empty($markdown_meta)) {
                return $markdown_meta;
            }
        }

        // Fallback when switching legacy posts to Markdown:
        if ($post && ! empty($post->post_content)) {
            return $this->htmlToMarkdown($post->post_content);
        }

        return '';
    }

    /**
     * Renders Classic/Markdown tab switcher (page-builder style) and Markdown editor area.
     */
    public function renderEditorSwitcher($post): void
    {
        if (! $this->isMarkdownEnabled() || ! $post) {
            return;
        }

        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);
        if (! in_array($post->post_type, $enabled_post_types, true)) {
            return;
        }

        $current_mode = $this->getPostEditorMode((int) $post->ID, $post);
        $markdown_content = $this->getPostMarkdownContent((int) $post->ID, $post, $current_mode);

        wp_nonce_field('save_markdown_content', 'markdown_nonce');
        ?>
        <div id="wp-addon-editor-switcher-wrap" class="wp-addon-editor-switcher-wrap">
            <style id="wp-addon-editor-switcher-style">
                .wp-addon-editor-switcher-wrap {
                    margin: 16px 0 0 0 !important;
                    position: relative !important;
                    box-sizing: border-box !important;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
                }
                .wp-addon-editor-switcher-wrap * {
                    box-sizing: border-box !important;
                }
                .wp-addon-builder-tabs-bar {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    background: #f6f7f7 !important;
                    border: 1px solid #c3c4c7 !important;
                    border-bottom: 1px solid #dcdcde !important;
                    border-radius: 6px 6px 0 0 !important;
                    padding: 6px 12px !important;
                    min-height: 46px !important;
                    gap: 12px !important;
                    flex-wrap: nowrap !important;
                }
                .wp-addon-builder-tabs {
                    display: inline-flex !important;
                    align-items: center !important;
                    background: #e2e4e7 !important;
                    border-radius: 6px !important;
                    padding: 3px !important;
                    gap: 3px !important;
                    flex-shrink: 0 !important;
                }
                .wp-addon-builder-tab {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 7px !important;
                    padding: 6px 16px !important;
                    font-size: 13px !important;
                    font-weight: 500 !important;
                    line-height: 20px !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    border: none !important;
                    outline: none !important;
                    background: transparent !important;
                    color: #50575e !important;
                    transition: all 0.15s ease-in-out !important;
                    box-shadow: none !important;
                    text-decoration: none !important;
                    vertical-align: middle !important;
                    user-select: none !important;
                    -webkit-user-select: none !important;
                }
                .wp-addon-builder-tab:hover {
                    background: rgba(255, 255, 255, 0.7) !important;
                    color: #1d2327 !important;
                }
                .wp-addon-builder-tab.is-active {
                    background: #2271b1 !important;
                    color: #ffffff !important;
                    font-weight: 600 !important;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15) !important;
                    cursor: default !important;
                }
                .wp-addon-builder-tab .dashicons {
                    font-size: 17px !important;
                    width: 17px !important;
                    height: 17px !important;
                    line-height: 17px !important;
                    color: inherit !important;
                }
                .wp-addon-builder-tab .tab-md-icon {
                    width: 16px !important;
                    height: 16px !important;
                    fill: currentColor !important;
                    display: inline-block !important;
                    vertical-align: middle !important;
                }
                .wp-addon-builder-actions {
                    display: flex !important;
                    align-items: center !important;
                    margin-left: auto !important;
                    gap: 8px !important;
                }
                .wp-addon-convert-btn {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 5px !important;
                    font-size: 12px !important;
                    font-weight: 500 !important;
                    height: 28px !important;
                    line-height: 26px !important;
                    padding: 0 10px !important;
                    color: #50575e !important;
                    background: #ffffff !important;
                    border: 1px solid #c3c4c7 !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    transition: all 0.15s ease-in-out !important;
                    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04) !important;
                    text-decoration: none !important;
                }
                .wp-addon-convert-btn:hover {
                    color: #2271b1 !important;
                    border-color: #2271b1 !important;
                    background: #f6f7f7 !important;
                }
                .wp-addon-convert-btn .dashicons {
                    font-size: 14px !important;
                    width: 14px !important;
                    height: 14px !important;
                    line-height: 14px !important;
                    color: inherit !important;
                }
                .wp-addon-markdown-container {
                    background: #ffffff !important;
                    border: 1px solid #c3c4c7 !important;
                    border-top: none !important;
                    padding: 0 !important;
                    margin-bottom: 20px !important;
                }
                .wp-addon-markdown-container .editor-toolbar {
                    border-top: none !important;
                    border-left: none !important;
                    border-right: none !important;
                    border-color: #c3c4c7 !important;
                    background: #fafafa !important;
                }
                .wp-addon-markdown-container .CodeMirror {
                    border-left: none !important;
                    border-right: none !important;
                    border-bottom: none !important;
                    border-color: #c3c4c7 !important;
                }
                #postdivrich {
                    margin-top: 0 !important;
                }
                #postdivrich #wp-content-wrap {
                    border-top: 1px solid #c3c4c7 !important;
                }
            </style>
            <div class="wp-addon-builder-tabs-bar">
                <div class="wp-addon-builder-tabs">
                    <button type="button" class="wp-addon-builder-tab <?php echo $current_mode === 'classic' ? 'is-active' : ''; ?>" data-mode="classic">
                        <span class="dashicons dashicons-edit-page"></span>
                        <span class="tab-text"><?php esc_html_e('Classic editor', 'wp-addon'); ?></span>
                    </button>
                    <button type="button" class="wp-addon-builder-tab <?php echo $current_mode === 'markdown' ? 'is-active' : ''; ?>" data-mode="markdown">
                        <svg class="tab-md-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
                            <path d="M14.5 2H1.5C.7 2 0 2.7 0 3.5v9c0 .8.7 1.5 1.5 1.5h13c.8 0 1.5-.7 1.5-1.5v-9c0-.8-.7-1.5-1.5-1.5zM7 11.5H5.5V7.2L3.8 9.3 2 7.2v4.3H.8V4.5h1.2l2 2.5 2-2.5h1.2v7zM14 8.5h-2V4.5h-1.5v4h-2L11 12l3-3.5z"/>
                        </svg>
                        <span class="tab-text"><?php esc_html_e('Markdown editor', 'wp-addon'); ?></span>
                    </button>
                </div>
                <div class="wp-addon-builder-actions">
                    <button type="button" class="wp-addon-convert-btn" id="wp-addon-btn-convert-to-md" title="<?php esc_attr_e('Convert HTML from the classic editor to Markdown', 'wp-addon'); ?>" style="<?php echo $current_mode === 'markdown' ? '' : 'display: none;'; ?>">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Import from HTML', 'wp-addon'); ?></span>
                    </button>
                    <button type="button" class="wp-addon-convert-btn" id="wp-addon-btn-convert-to-html" title="<?php esc_attr_e('Convert Markdown to HTML for the classic editor', 'wp-addon'); ?>" style="<?php echo $current_mode === 'classic' ? '' : 'display: none;'; ?>">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Import from Markdown', 'wp-addon'); ?></span>
                    </button>
                </div>
            </div>
            <input type="hidden" name="wp_addon_editor_mode" id="wp_addon_editor_mode" value="<?php echo esc_attr($current_mode); ?>">
            <input type="hidden" name="markdown_edited" id="markdown_edited" value="0">
            <div id="markdown-editor-container" class="wp-addon-markdown-container" style="<?php echo $current_mode === 'markdown' ? '' : 'display: none;'; ?>">
                <textarea id="markdown-textarea" name="markdown_content" rows="20" style="width: 100%; font-family: monospace; font-size: 14px;"><?php echo esc_textarea($markdown_content); ?></textarea>
            </div>
        </div>
        <?php if ($current_mode === 'markdown') { ?>
        <style id="wp-addon-hide-tinymce">
            #postdivrich { display: none !important; }
        </style>
        <?php } ?>
        <?php
    }

    /**
     * Kept for backward compatibility.
     */
    public function addMarkdownMetaBox(): void {}

    /**
     * Kept for backward compatibility.
     */
    public function renderMarkdownMetaBox($post): void
    {
        $this->renderEditorSwitcher($post);
    }

    /**
     * AJAX conversion HTML <-> Markdown
     */
    public function ajaxConvertContent(): void
    {
        check_ajax_referer('markdown_preview', 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $direction = isset($_POST['direction']) ? sanitize_key($_POST['direction']) : '';
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        if ($direction === 'html_to_md') {
            $markdown = $this->htmlToMarkdown($content);
            wp_send_json_success($markdown);
        } elseif ($direction === 'md_to_html') {
            $html = $this->parseMarkdown($content);
            wp_send_json_success($html);
        }

        wp_send_json_error(['message' => 'Invalid direction'], 400);
    }

    /**
     * First save of a new post: post_updated does not run,
     * so Markdown-to-HTML conversion happens here.
     */
    public function saveMarkdownOnFirstPublish(int $post_id, $post, bool $update): void
    {
        if ($update) {
            return;
        }

        $this->handleMarkdownSave($post_id, $post, null);
    }

    /**
     * Save post content according to the active editor mode (Markdown or Classic).
     *
     * @param  int  $post_id  Post ID
     * @param  WP_Post  $post_after  Post after update
     * @param  WP_Post|null  $post_before  Post before update
     */
    public function saveMarkdownContent(int $post_id, $post_after, $post_before): void
    {
        $this->handleMarkdownSave($post_id, $post_after, $post_before);
    }

    private function handleMarkdownSave(int $post_id, $post_after, $post_before = null): void
    {
        if (! $this->isMarkdownEnabled() || $this->saving) {
            return;
        }

        if (! isset($_POST['markdown_nonce']) || ! wp_verify_nonce($_POST['markdown_nonce'], 'save_markdown_content')) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);
        if (! in_array($post_after->post_type, $enabled_post_types, true)) {
            return;
        }

        // Editor mode: classic or markdown
        $mode = isset($_POST['wp_addon_editor_mode'])
            ? sanitize_text_field($_POST['wp_addon_editor_mode'])
            : null;

        // Fallback for direct calls / unit tests
        if ($mode === null) {
            $markdown_edited = isset($_POST['markdown_edited']) && (string) $_POST['markdown_edited'] === '1';
            $new_markdown = isset($_POST['markdown_content']) ? wp_unslash($_POST['markdown_content']) : '';
            $expected_markdown = (is_object($post_before) && ! empty($post_before->post_content))
                ? $this->htmlToMarkdown((string) $post_before->post_content)
                : '';
            $html_changed = is_object($post_before)
                && isset($post_before->post_content, $post_after->post_content)
                && $post_before->post_content !== $post_after->post_content;

            if ($markdown_edited || (! $html_changed && $new_markdown !== '' && $new_markdown !== $expected_markdown)) {
                $mode = 'markdown';
            } else {
                $mode = 'classic';
            }
        }

        update_post_meta($post_id, '_wp_addon_editor_mode', $mode);

        if ($mode === 'markdown') {
            $new_markdown = isset($_POST['markdown_content']) ? wp_unslash($_POST['markdown_content']) : '';

            // Store source Markdown in meta to avoid drift on reopen
            update_post_meta($post_id, '_markdown_content', $new_markdown);

            $parsed_html = $new_markdown !== '' ? $this->parseMarkdown($new_markdown) : '';

            // Update post_content only when converted HTML differs
            if ($post_after->post_content !== $parsed_html) {
                $this->saving = true;

                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $parsed_html,
                ]);

                $this->saving = false;
            }
        } else {
            // Classic mode: drop stored Markdown; HTML is canonical
            $this->purgeStoredMarkdown($post_id);
            // Do not touch post_content — WordPress already saved classic HTML
        }
    }

    /**
     * Remove stored Markdown when switching to HTML or on request.
     */
    private function purgeStoredMarkdown(int $post_id, string $html_content = ''): void
    {
        delete_post_meta($post_id, '_markdown_content');
        delete_post_meta($post_id, 'markdown_content');
    }

    /**
     * Parse Markdown to HTML using Parsedown
     */
    public function parseMarkdown(string $markdown): string
    {
        // Check presence autoloader Composer
        if (file_exists(ABSPATH.'vendor/autoload.php')) {
            require_once ABSPATH.'vendor/autoload.php';
        } elseif (file_exists(dirname(ABSPATH).'/vendor/autoload.php')) {
            require_once dirname(ABSPATH).'/vendor/autoload.php';
        }

        // Use Parsedown when available
        if (class_exists('Parsedown')) {
            $parsedown = new Parsedown;

            // Security settings
            $parsedown->setSafeMode(false); // Allow HTML for WordPress
            $parsedown->setMarkupEscaped(false);
            $parsedown->setUrlsLinked(true);
            $parsedown->setBreaksEnabled(false);

            $html = $parsedown->text($markdown);

            // WordPress-specific post-processing
            $html = $this->processWordPressElements($html);

            return $html;
        }

        // Fallback parser when Parsedown is unavailable
        return $this->fallbackMarkdownParser($markdown);
    }

    /**
     * WordPress-specific element processing
     */
    private function processWordPressElements(string $html): string
    {
        // Add table classes
        $html = str_replace('<table>', '<table class="markdown-table">', $html);

        // Task list checkboxes
        $html = preg_replace('/\[x\]/', '<input type="checkbox" checked disabled>', $html);
        $html = preg_replace('/\[ \]/', '<input type="checkbox" disabled>', $html);

        // External links: target="_blank"
        $html = preg_replace('/<a href="(https?:\/\/[^"]*)"/', '<a href="$1" target="_blank" rel="noopener"', $html);

        // Image styling
        $html = preg_replace('/<img([^>]*src="[^"]*"[^>]*)>/', '<img$1 style="max-width: 100%; height: auto;">', $html);

        return $html;
    }

    /**
     * Simple fallback parser when Parsedown is missing
     */
    private function fallbackMarkdownParser(string $markdown): string
    {
        $html = $markdown;

        // Basic element handling
        // Headings
        $html = preg_replace('/^###### (.*$)/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^##### (.*$)/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^#### (.*$)/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);

        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Links and images
        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width: 100%; height: auto;">', $html);
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);

        // Paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $html);
        $html_paragraphs = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (! empty($paragraph)) {
                if (! preg_match('/^<(h[1-6]|hr)/', $paragraph)) {
                    $paragraph = '<p>'.$paragraph.'</p>';
                }
                $html_paragraphs[] = $paragraph;
            }
        }

        return implode("\n\n", $html_paragraphs);
    }

    /**
     * Simple HTML-to-Markdown conversion (for migration)
     */
    private function htmlToMarkdown(string $html): string
    {
        $markdown = $html;

        // Headings
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', '# $1', $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', '## $1', $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/i', '### $1', $markdown);
        $markdown = preg_replace('/<h4[^>]*>(.*?)<\/h4>/i', '#### $1', $markdown);
        $markdown = preg_replace('/<h5[^>]*>(.*?)<\/h5>/i', '##### $1', $markdown);
        $markdown = preg_replace('/<h6[^>]*>(.*?)<\/h6>/i', '###### $1', $markdown);

        // Bold and italic
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $markdown);
        $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/i', '*$1*', $markdown);

        // Links
        $markdown = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', '[$2]($1)', $markdown);

        // Images
        $markdown = preg_replace('/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i', '![$2]($1)', $markdown);
        $markdown = preg_replace('/<img[^>]*src="([^"]*)"[^>]*>/i', '![]($1)', $markdown);

        // Code blocks
        $markdown = preg_replace('/<code[^>]*>(.*?)<\/code>/i', '`$1`', $markdown);
        $markdown = preg_replace('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/s', "```\n$1\n```", $markdown);

        // Lists
        $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/i', '* $1', $markdown);
        $markdown = preg_replace('/<ul[^>]*>(.*?)<\/ul>/s', '$1', $markdown);
        $markdown = preg_replace('/<ol[^>]*>(.*?)<\/ol>/s', '$1', $markdown);

        // Blockquotes
        $markdown = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/s', '> $1', $markdown);

        // Paragraphs
        $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/i', '$1'."\n\n", $markdown);

        // Strip remaining HTML tags
        $markdown = strip_tags($markdown);

        return trim($markdown);
    }

    /**
     * Enqueue styles and scripts for the Markdown editor
     */
    public function enqueueMarkdownAssets($hook): void
    {
        if (! $this->isMarkdownEnabled()) {
            return;
        }

        if (! in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post, $typenow;
        $post_type = $typenow;
        if (! $post_type && $post) {
            $post_type = $post->post_type;
        } elseif (! $post_type && isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        } elseif (! $post_type && isset($_GET['post'])) {
            $post_type = get_post_type((int) $_GET['post']);
        }
        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);
        if ($post_type && ! in_array($post_type, $enabled_post_types, true)) {
            return;
        }

        // EasyMDE
        wp_enqueue_script(
            'easymde',
            'https://unpkg.com/easymde/dist/easymde.min.js',
            [],
            '2.18.0',
            true
        );

        wp_enqueue_style(
            'easymde',
            'https://unpkg.com/easymde/dist/easymde.min.css',
            [],
            '2.18.0'
        );

        // WordPress media uploader
        wp_enqueue_media();

        wp_enqueue_script(
            'markdown-editor',
            RW_PLUGIN_URL.'assets/js/markdown-editor.js',
            ['jquery', 'easymde'],
            '1.3.0',
            true
        );

        wp_localize_script('markdown-editor', 'markdownAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('markdown_preview'),
            'enable_shortcuts' => (bool) $this->getSetting('markdown_enable_shortcuts', true),
            'enable_preview' => (bool) $this->getSetting('markdown_enable_preview', true),
            'i18n' => [
                'import_html_confirm' => __('Import current HTML content into the Markdown editor?', 'wp-addon'),
                'import_md_confirm' => __('Convert Markdown to HTML for the classic editor?', 'wp-addon'),
                'overwrite_md_confirm' => __('Replace current Markdown with HTML conversion result?', 'wp-addon'),
                'overwrite_html_confirm' => __('Replace classic editor HTML with Markdown conversion result?', 'wp-addon'),
                'convert_error' => __('Content conversion error', 'wp-addon'),
                'media_title' => __('Choose an image', 'wp-addon'),
                'media_button' => __('Insert into post', 'wp-addon'),
                'media_toolbar' => __('Insert image (WP Media)', 'wp-addon'),
                'help_summary' => __('Markdown help (click to expand)', 'wp-addon'),
                'help_h1' => __('# Heading 1 — large heading', 'wp-addon'),
                'help_h2' => __('## Heading 2 — medium heading', 'wp-addon'),
                'help_bold' => __('**bold** — bold text', 'wp-addon'),
                'help_italic' => __('*italic* — italic text', 'wp-addon'),
                'help_link' => __('[text](url) — link', 'wp-addon'),
                'help_image' => __('![alt](url) — image', 'wp-addon'),
                'help_code' => __('`code` — inline code', 'wp-addon'),
                'help_ul' => __('* item — bullet list', 'wp-addon'),
                'help_ol' => __('1. item — numbered list', 'wp-addon'),
                'help_shortcuts' => __('Shortcuts: Ctrl+B (bold), Ctrl+I (italic), Ctrl+K (link)', 'wp-addon'),
            ],
        ]);

        // GitHub Markdown styles for preview
        wp_enqueue_style(
            'github-markdown-css',
            'https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.5.0/github-markdown.min.css',
            [],
            '5.5.0'
        );

        // Highlight.js for syntax highlighting
        wp_enqueue_style(
            'highlightjs-github',
            'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css',
            [],
            '11.9.0'
        );

        wp_enqueue_script(
            'highlightjs',
            'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js',
            [],
            '11.9.0',
            true
        );

        $css_file = defined('RW_PLUGIN_DIR') ? RW_PLUGIN_DIR.'assets/css/markdown-editor.css' : '';
        $css_version = ($css_file && file_exists($css_file)) ? (string) filemtime($css_file) : '1.5.2';

        wp_enqueue_style(
            'markdown-editor',
            RW_PLUGIN_URL.'assets/css/markdown-editor.css',
            [],
            $css_version
        );

        // Extra styles for Markdown elements
        wp_add_inline_style('markdown-editor', '
            /* Markdown tables */
            .markdown-table {
                border-collapse: collapse;
                width: 100%;
                margin: 15px 0;
                font-size: 14px;
            }
            .markdown-table th,
            .markdown-table td {
                border: 1px solid #ddd;
                padding: 8px 12px;
                text-align: left;
            }
            .markdown-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .markdown-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            /* Task lists */
            .task-list {
                list-style: none;
                padding-left: 0;
            }
            .task-list li {
                margin: 5px 0;
            }
            .task-list input[type="checkbox"] {
                margin-right: 8px;
                margin-left: 0;
            }
            
            /* Code blocks with syntax highlighting */
            pre code[class*="language-"] {
                background: #f8f8f8;
                border: 1px solid #e1e1e8;
                border-radius: 4px;
                font-size: 13px;
                line-height: 1.4;
            }
            pre code.language-php { border-left: 4px solid #777bb4; }
            pre code.language-js,
            pre code.language-javascript { border-left: 4px solid #f7df1e; }
            pre code.language-css { border-left: 4px solid #1572b6; }
            pre code.language-html { border-left: 4px solid #e34f26; }
            pre code.language-sql { border-left: 4px solid #336791; }
            
            /* Blockquotes */
            blockquote {
                border-left: 4px solid #ddd;
                margin: 15px 0;
                padding-left: 15px;
                color: #666;
                font-style: italic;
            }
            blockquote p {
                margin: 5px 0;
            }
            
            /* Inline code */
            code {
                background: #f4f4f4;
                border: 1px solid #ddd;
                border-radius: 3px;
                padding: 2px 4px;
                font-size: 90%;
                color: #c7254e;
            }
            
            /* Horizontal rules */
            hr {
                border: none;
                border-top: 2px solid #eee;
                margin: 20px 0;
            }
        ');
    }
}
