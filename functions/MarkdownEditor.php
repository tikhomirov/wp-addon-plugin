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

        $this->addHook('edit_form_after_title', [$this, 'renderEditorSwitcher']);
        $this->addHook('post_updated', [$this, 'saveMarkdownContent'], 10, 3);
        $this->addHook('save_post', [$this, 'saveMarkdownOnFirstPublish'], 10, 3);
        $this->addHook('admin_enqueue_scripts', [$this, 'enqueueMarkdownAssets']);
        $this->addHook('wp_ajax_markdown_convert', [$this, 'ajaxConvertContent']);
    }

    /**
     * Получает настройку из CSF
     */
    private function getSetting($key, $default = null)
    {
        $settings = get_option($this->settings_prefix, []);

        return $settings[$key] ?? $default;
    }

    /**
     * Проверяет, включен ли Markdown
     */
    private function isMarkdownEnabled(): bool
    {
        return (bool) $this->getSetting('wp_addon_markdown_enabled', false);
    }

    /**
     * Оставлено для обратной совместимости.
     */
    public function maybeDisableRichEdit($can_richedit)
    {
        return $can_richedit;
    }

    /**
     * Оставлено для обратной совместимости.
     */
    public function disableVisualEditor(): void {}

    /**
     * Возвращает режим редактора для поста ('classic' или 'markdown').
     *
     * Приоритет определения:
     * 1. Явно сохранённое значение в meta `_wp_addon_editor_mode`.
     * 2. Наличие мета-поля `_markdown_content` (пост был создан в Markdown).
     * 3. Наличие существующего HTML-контента — открываем в Classic, чтобы не ломать старые записи.
     * 4. Для новых пустых записей — выбор из настройки `markdown_replace_tinymce`.
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
     * Возвращает содержимое Markdown для редактора.
     *
     * КРИТИЧЕСКИ ВАЖНО: если пост открыт в режиме Classic (HTML), возвращаем пустую строку.
     * Это гарантирует, что редактор Markdown не будет восстанавливать контент и не затрёт HTML
     * при последующем сохранении.
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

        // Fallback для старых постов при переключении в Markdown:
        if ($post && ! empty($post->post_content)) {
            return $this->htmlToMarkdown($post->post_content);
        }

        return '';
    }

    /**
     * Отрисовывает переключатель табов (Классический / Markdown) в стиле билдеров (WPBakery/YOOtheme/Divi)
     * и встроенную область Markdown-редактора.
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
            <div class="wp-addon-builder-tabs-bar">
                <div class="wp-addon-builder-tabs">
                    <button type="button" class="wp-addon-builder-tab <?php echo $current_mode === 'classic' ? 'is-active' : ''; ?>" data-mode="classic">
                        <span class="dashicons dashicons-editor-kitchensink"></span>
                        <span class="tab-text"><?php esc_html_e('Классический редактор', 'wp-addon'); ?></span>
                    </button>
                    <button type="button" class="wp-addon-builder-tab <?php echo $current_mode === 'markdown' ? 'is-active' : ''; ?>" data-mode="markdown">
                        <svg class="tab-md-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
                            <path d="M14 3H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM2 2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/>
                            <path d="M3 11V5h1.5l1.5 2.5L7.5 5H9v6H7.5V7.5L6 10l-1.5-2.5V11H3zm8.5-3.5h1.5v3h-1.5v-3zm0-1h1.5L12 5l-1 1.5z"/>
                        </svg>
                        <span class="tab-text"><?php esc_html_e('Markdown Редактор', 'wp-addon'); ?></span>
                    </button>
                </div>
                <div class="wp-addon-builder-actions">
                    <button type="button" class="button button-small wp-addon-convert-btn" id="wp-addon-btn-convert-to-md" title="<?php esc_attr_e('Конвертировать HTML из классического редактора в Markdown', 'wp-addon'); ?>" style="<?php echo $current_mode === 'markdown' ? '' : 'display: none;'; ?>">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Импорт из HTML', 'wp-addon'); ?></span>
                    </button>
                    <button type="button" class="button button-small wp-addon-convert-btn" id="wp-addon-btn-convert-to-html" title="<?php esc_attr_e('Конвертировать Markdown в HTML для классического редактора', 'wp-addon'); ?>" style="<?php echo $current_mode === 'classic' ? '' : 'display: none;'; ?>">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Импорт из Markdown', 'wp-addon'); ?></span>
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
     * Оставлено для обратной совместимости.
     */
    public function addMarkdownMetaBox(): void {}

    /**
     * Оставлено для обратной совместимости.
     */
    public function renderMarkdownMetaBox($post): void
    {
        $this->renderEditorSwitcher($post);
    }

    /**
     * AJAX конвертация HTML <-> Markdown
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
     * Первое сохранение новой записи: post_updated не срабатывает,
     * поэтому конвертация Markdown в HTML выполняется здесь.
     */
    public function saveMarkdownOnFirstPublish(int $post_id, $post, bool $update): void
    {
        if ($update) {
            return;
        }

        $this->handleMarkdownSave($post_id, $post, null);
    }

    /**
     * Сохраняет содержимое записи согласно активному режиму редактора (Markdown или Classic).
     *
     * @param  int  $post_id  ID поста
     * @param  WP_Post  $post_after  Пост после обновления
     * @param  WP_Post|null  $post_before  Пост до обновления
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

        // Режим редактора: classic или markdown
        $mode = isset($_POST['wp_addon_editor_mode'])
            ? sanitize_text_field($_POST['wp_addon_editor_mode'])
            : null;

        // Fallback для прямых вызовов / unit-тестов
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

            // Сохраняем исходный Markdown в мета-поле, предотвращая искажения при повторном открытии
            update_post_meta($post_id, '_markdown_content', $new_markdown);

            $parsed_html = $new_markdown !== '' ? $this->parseMarkdown($new_markdown) : '';

            // Обновляем post_content в базе данных только если сконвертированный HTML отличается
            if ($post_after->post_content !== $parsed_html) {
                $this->saving = true;

                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $parsed_html,
                ]);

                $this->saving = false;
            }
        } else {
            // В режиме Classic удаляем мета Markdown: пост ведётся в HTML, и MD не должен перезаписывать или восстанавливать контент
            $this->purgeStoredMarkdown($post_id);
            // post_content не перезаписываем — WordPress уже сохранил актуальный HTML из классического редактора
        }
    }

    /**
     * Удаляет сохранённый исходник Markdown при переходе в режим HTML или по запросу.
     */
    private function purgeStoredMarkdown(int $post_id, string $html_content = ''): void
    {
        delete_post_meta($post_id, '_markdown_content');
        delete_post_meta($post_id, 'markdown_content');
    }

    /**
     * Парсер Markdown в HTML с использованием библиотеки Parsedown
     */
    public function parseMarkdown(string $markdown): string
    {
        // Проверяем наличие автозагрузчика Composer
        if (file_exists(ABSPATH.'vendor/autoload.php')) {
            require_once ABSPATH.'vendor/autoload.php';
        } elseif (file_exists(dirname(ABSPATH).'/vendor/autoload.php')) {
            require_once dirname(ABSPATH).'/vendor/autoload.php';
        }

        // Используем Parsedown если доступен
        if (class_exists('Parsedown')) {
            $parsedown = new Parsedown;

            // Настройка безопасности
            $parsedown->setSafeMode(false); // Разрешаем HTML для WordPress
            $parsedown->setMarkupEscaped(false);
            $parsedown->setUrlsLinked(true);
            $parsedown->setBreaksEnabled(false);

            $html = $parsedown->text($markdown);

            // Дополнительная обработка для WordPress-специфичных элементов
            $html = $this->processWordPressElements($html);

            return $html;
        }

        // Fallback на простой парсер если Parsedown недоступен
        return $this->fallbackMarkdownParser($markdown);
    }

    /**
     * Дополнительная обработка WordPress-специфичных элементов
     */
    private function processWordPressElements(string $html): string
    {
        // Добавляем классы для таблиц
        $html = str_replace('<table>', '<table class="markdown-table">', $html);

        // Обрабатываем чекбоксы задач
        $html = preg_replace('/\[x\]/', '<input type="checkbox" checked disabled>', $html);
        $html = preg_replace('/\[ \]/', '<input type="checkbox" disabled>', $html);

        // Добавляем target="_blank" для внешних ссылок
        $html = preg_replace('/<a href="(https?:\/\/[^"]*)"/', '<a href="$1" target="_blank" rel="noopener"', $html);

        // Стилизация изображений
        $html = preg_replace('/<img([^>]*src="[^"]*"[^>]*)>/', '<img$1 style="max-width: 100%; height: auto;">', $html);

        return $html;
    }

    /**
     * Простой fallback парсер на случай отсутствия Parsedown
     */
    private function fallbackMarkdownParser(string $markdown): string
    {
        $html = $markdown;

        // Базовая обработка основных элементов
        // Заголовки
        $html = preg_replace('/^###### (.*$)/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^##### (.*$)/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^#### (.*$)/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);

        // Жирный и курсив
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Ссылки и изображения
        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width: 100%; height: auto;">', $html);
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);

        // Inline код
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Горизонтальные линии
        $html = preg_replace('/^---$/m', '<hr>', $html);

        // Параграфы
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
     * Простое преобразование HTML в Markdown (для миграции)
     */
    private function htmlToMarkdown(string $html): string
    {
        $markdown = $html;

        // Заголовки
        $markdown = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', '# $1', $markdown);
        $markdown = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', '## $1', $markdown);
        $markdown = preg_replace('/<h3[^>]*>(.*?)<\/h3>/i', '### $1', $markdown);
        $markdown = preg_replace('/<h4[^>]*>(.*?)<\/h4>/i', '#### $1', $markdown);
        $markdown = preg_replace('/<h5[^>]*>(.*?)<\/h5>/i', '##### $1', $markdown);
        $markdown = preg_replace('/<h6[^>]*>(.*?)<\/h6>/i', '###### $1', $markdown);

        // Жирный и курсив
        $markdown = preg_replace('/<strong[^>]*>(.*?)<\/strong>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<b[^>]*>(.*?)<\/b>/i', '**$1**', $markdown);
        $markdown = preg_replace('/<em[^>]*>(.*?)<\/em>/i', '*$1*', $markdown);
        $markdown = preg_replace('/<i[^>]*>(.*?)<\/i>/i', '*$1*', $markdown);

        // Ссылки
        $markdown = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', '[$2]($1)', $markdown);

        // Изображения
        $markdown = preg_replace('/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i', '![$2]($1)', $markdown);
        $markdown = preg_replace('/<img[^>]*src="([^"]*)"[^>]*>/i', '![]($1)', $markdown);

        // Код
        $markdown = preg_replace('/<code[^>]*>(.*?)<\/code>/i', '`$1`', $markdown);
        $markdown = preg_replace('/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/s', "```\n$1\n```", $markdown);

        // Списки
        $markdown = preg_replace('/<li[^>]*>(.*?)<\/li>/i', '* $1', $markdown);
        $markdown = preg_replace('/<ul[^>]*>(.*?)<\/ul>/s', '$1', $markdown);
        $markdown = preg_replace('/<ol[^>]*>(.*?)<\/ol>/s', '$1', $markdown);

        // Цитаты
        $markdown = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/s', '> $1', $markdown);

        // Параграфы
        $markdown = preg_replace('/<p[^>]*>(.*?)<\/p>/i', '$1'."\n\n", $markdown);

        // Убираем лишние HTML теги
        $markdown = strip_tags($markdown);

        return trim($markdown);
    }

    /**
     * Подключает стили и скрипты для Markdown редактора
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

        // Подключаем EasyMDE
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

        // Подключаем WordPress Media Uploader
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
                'import_html_confirm' => __('Импортировать текущий HTML контент в Markdown редактор?', 'wp-addon'),
                'import_md_confirm' => __('Конвертировать Markdown в HTML для классического редактора?', 'wp-addon'),
                'overwrite_md_confirm' => __('Заменить текущий Markdown контент результатом конвертации из HTML?', 'wp-addon'),
                'overwrite_html_confirm' => __('Заменить текущий HTML в классическом редакторе результатом конвертации из Markdown?', 'wp-addon'),
                'convert_error' => __('Ошибка при конвертации контента', 'wp-addon'),
            ],
        ]);

        // Подключаем стили GitHub Markdown для предпросмотра
        wp_enqueue_style(
            'github-markdown-css',
            'https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.5.0/github-markdown.min.css',
            [],
            '5.5.0'
        );

        // Подключаем Highlight.js для подсветки синтаксиса
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

        wp_enqueue_style(
            'markdown-editor',
            RW_PLUGIN_URL.'assets/css/markdown-editor.css',
            ['github-markdown-css', 'highlightjs-github'],
            '1.0.0'
        );

        // Добавляем дополнительные стили для новых элементов Markdown
        wp_add_inline_style('markdown-editor', '
            /* Стили для таблиц Markdown */
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
            
            /* Стили для списков задач */
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
            
            /* Стили для блоков кода с подсветкой синтаксиса */
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
            
            /* Улучшение цитат */
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
            
            /* Стили для inline кода */
            code {
                background: #f4f4f4;
                border: 1px solid #ddd;
                border-radius: 3px;
                padding: 2px 4px;
                font-size: 90%;
                color: #c7254e;
            }
            
            /* Горизонтальные линии */
            hr {
                border: none;
                border-top: 2px solid #eee;
                margin: 20px 0;
            }
        ');
    }
}
