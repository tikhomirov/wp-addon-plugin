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

        $this->addHook('add_meta_boxes', [$this, 'addMarkdownMetaBox']);
        $this->addHook('post_updated', [$this, 'saveMarkdownContent'], 10, 3);
        $this->addHook('save_post', [$this, 'saveMarkdownOnFirstPublish'], 10, 3);
        $this->addHook('admin_enqueue_scripts', [$this, 'enqueueMarkdownAssets']);

        if ($this->getSetting('markdown_replace_tinymce', false)) {
            $this->addFilter('user_can_richedit', [$this, 'maybeDisableRichEdit']);
            $this->addHook('admin_init', [$this, 'disableVisualEditor']);
        }
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
     * Отключает визуальный редактор TinyMCE для постов с поддержкой Markdown
     */
    public function maybeDisableRichEdit($can_richedit): bool
    {
        global $pagenow, $typenow;

        // Проверяем, что мы на странице редактирования поста
        if (! in_array($pagenow, ['post.php', 'post-new.php'])) {
            return $can_richedit;
        }

        // Получаем тип поста
        $post_type = $typenow;
        if (! $post_type && isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        } elseif (! $post_type && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $post_type = get_post_type($post_id);
        } elseif (! $post_type) {
            $post_type = 'post'; // По умолчанию
        }

        // Проверяем, поддерживается ли этот тип поста
        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);
        if (! in_array($post_type, $enabled_post_types)) {
            return $can_richedit;
        }

        return false; // Отключаем визуальный редактор
    }

    /**
     * Дополнительный метод для полного отключения визуального редактора
     */
    public function disableVisualEditor(): void
    {
        global $pagenow, $typenow;

        // Проверяем, что мы на странице редактирования поста
        if (! in_array($pagenow, ['post.php', 'post-new.php'])) {
            return;
        }

        // Получаем тип поста
        $post_type = $typenow;
        if (! $post_type && isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        } elseif (! $post_type && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $post_type = get_post_type($post_id);
        }

        // Проверяем, поддерживается ли этот тип поста
        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);
        if ($post_type && in_array($post_type, $enabled_post_types)) {
            // Отключаем визуальный редактор принудительно
            add_filter('wp_default_editor', function () {
                return 'html';
            });
        }
    }

    /**
     * Добавляет мета-бокс для Markdown редактирования
     */
    public function addMarkdownMetaBox(): void
    {
        $enabled_post_types = $this->getSetting('markdown_post_types', ['post', 'page']);

        foreach ($enabled_post_types as $post_type) {
            $title = $this->getSetting('markdown_replace_tinymce', false)
                ? __('Content (Markdown)', 'wp-addon')
                : __('Markdown Editor', 'wp-addon');

            add_meta_box(
                'markdown-editor',
                $title,
                [$this, 'renderMarkdownMetaBox'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Отрисовывает мета-бокс для Markdown
     */
    public function renderMarkdownMetaBox($post): void
    {
        // Markdown исходник не хранится: поле всегда заполняется из текущего HTML,
        // чтобы редакторы не расходились друг с другом.
        $markdown_content = ! empty($post->post_content)
            ? $this->htmlToMarkdown($post->post_content)
            : '';

        wp_nonce_field('save_markdown_content', 'markdown_nonce');

        // Если включена замена TinyMCE, скрываем стандартный редактор
        if ($this->getSetting('markdown_replace_tinymce', false)) {
            echo '<style>
                #postdivrich { display: none !important; }
                #wp-content-wrap { display: none !important; }
                .wp-editor-tabs { display: none !important; }
            </style>';
        }

        echo '<div id="markdown-editor-container">';
        echo '<textarea id="markdown-textarea" name="markdown_content" rows="20" style="width: 100%; font-family: monospace; font-size: 14px;">'.esc_textarea($markdown_content).'</textarea>';
        echo '</div>';

        echo '<p><small><em>'.__('В основном поле поста сохраняется только HTML. Markdown автоматически конвертируется в HTML при сохранении и нигде отдельно не хранится.', 'wp-addon').'</em></small></p>';
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
     * Сохраняет содержимое поста, используя Markdown как источник только тогда,
     * когда сам Markdown редактор действительно менялся.
     *
     * При любом сохранении в основном поле остается только HTML, а исходник
     * Markdown никуда не сохраняется.
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
        if (! in_array($post_after->post_type, $enabled_post_types)) {
            return;
        }

        if (! isset($_POST['markdown_content'])) {
            return;
        }

        $new_markdown = wp_unslash($_POST['markdown_content']);

        // Значение, которое редактору нужно было показать, если его не трогали.
        // Оно всегда выводится из HTML, поэтому правки в стандартном редакторе
        // не перетираются сохраненным Markdown.
        $expected_markdown = (! empty($post_before) && ! empty($post_before->post_content))
            ? $this->htmlToMarkdown($post_before->post_content)
            : '';

        // Markdown редактировался осознанно: конвертируем его в HTML
        // и пишем в единственный источник контента — post_content.
        if ($new_markdown !== $expected_markdown && ! empty($new_markdown)) {
            $this->saving = true;

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $this->parseMarkdown($new_markdown),
            ]);

            $this->saving = false;
        }

        // Исходник Markdown не храним: удаляем возможные старые значения.
        if (metadata_exists('post', $post_id, '_markdown_content')) {
            delete_post_meta($post_id, '_markdown_content');
        }
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
            '1.1.0',
            true
        );

        wp_localize_script('markdown-editor', 'markdownAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('markdown_preview'),
            'enable_shortcuts' => (bool) $this->getSetting('markdown_enable_shortcuts', true),
            'enable_preview' => (bool) $this->getSetting('markdown_enable_preview', true),
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
