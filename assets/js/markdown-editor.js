/**
 * Markdown Editor JavaScript
 * Поддержка переключателя табов (Классический редактор / Markdown) по образцу WPBakery / YOOtheme / Divi.
 */
jQuery(document).ready(function($) {
    var MarkdownEditor = {
        textarea: null,
        easyMDE: null,
        modeInput: null,
        container: null,
        postdivrich: null,
        btnConvertToMd: null,
        btnConvertToHtml: null,

        init: function() {
            this.textarea = $('#markdown-textarea');
            this.modeInput = $('#wp_addon_editor_mode');
            this.container = $('#markdown-editor-container');
            this.postdivrich = $('#postdivrich');
            this.switcherWrap = $('#wp-addon-editor-switcher-wrap');
            this.btnConvertToMd = $('#wp-addon-btn-convert-to-md');
            this.btnConvertToHtml = $('#wp-addon-btn-convert-to-html');

            if (this.textarea.length === 0) {
                return;
            }

            // Гарантируем правильный порядок в DOM: переключатель и MD-редактор
            // должны находиться ровно над основным редактором #postdivrich,
            // ПОСЛЕ подзаголовка и других полей edit_form_after_title.
            if (this.postdivrich.length && this.switcherWrap.length) {
                this.postdivrich.before(this.switcherWrap);
            }

            this.initEasyMDE();
            this.bindEvents();

            // Если при загрузке режим Markdown — гарантируем скрытие TinyMCE
            if (this.modeInput.val() === 'markdown') {
                this.postdivrich.hide();
                this.container.show();
                if (this.easyMDE && this.easyMDE.codemirror) {
                    setTimeout(() => {
                        this.easyMDE.codemirror.refresh();
                    }, 50);
                }
            } else {
                this.container.hide();
                this.postdivrich.show();
            }
        },

        initEasyMDE: function() {
            if (typeof EasyMDE === 'undefined') {
                console.error('EasyMDE is not loaded');
                return;
            }

            var options = typeof markdownAjax !== 'undefined' ? markdownAjax : {};
            var previewEnabled = options.enable_preview !== false;
            var shortcutsEnabled = options.enable_shortcuts !== false;

            var toolbar = [
                "bold", "italic", "strikethrough", "heading", "|",
                "quote", "unordered-list", "ordered-list", "|",
                "link",
                {
                    name: "image",
                    action: function customImageAction(editor) {
                        var customUploader = wp.media.frames.file_frame = wp.media({
                            title: 'Выберите изображение',
                            button: {
                                text: 'Вставить в запись'
                            },
                            multiple: false
                        });

                        customUploader.on('select', function() {
                            var attachment = customUploader.state().get('selection').first().toJSON();
                            var url = attachment.url;
                            var alt = attachment.alt || attachment.title || '';
                            var cm = editor.codemirror;
                            var imageMarkdown = '![' + alt + '](' + url + ')';

                            cm.replaceSelection(imageMarkdown);
                            cm.focus();
                        });

                        customUploader.open();
                    },
                    className: "fa fa-picture-o",
                    title: "Вставить изображение (WP Media)"
                },
                "table", "horizontal-rule", "|"
            ];

            if (previewEnabled) {
                toolbar = toolbar.concat(["side-by-side", "fullscreen", "|", "guide"]);
            } else {
                toolbar.push("guide");
            }

            var shortcuts = {};
            if (shortcutsEnabled) {
                shortcuts.toggleBold = "Cmd-B";
                shortcuts.toggleItalic = "Cmd-I";
                shortcuts.drawLink = "Cmd-K";
            } else {
                shortcuts.toggleBold = null;
                shortcuts.toggleItalic = null;
                shortcuts.drawLink = null;
            }
            if (!previewEnabled) {
                shortcuts.togglePreview = null;
                shortcuts.toggleSideBySide = null;
            }

            this.easyMDE = new EasyMDE({
                element: this.textarea[0],
                spellChecker: false,
                autosave: {
                    enabled: false,
                },
                status: ["lines", "words", "cursor"],
                sideBySideFullscreen: previewEnabled,
                toolbar: toolbar,
                shortcuts: shortcuts
            });

            var initialMarkdown = this.easyMDE.value();
            var editedInput = $('#markdown_edited');
            if (editedInput.length) {
                editedInput.data('initial', initialMarkdown);
            }

            // Синхронизация EasyMDE с textarea при изменении
            this.easyMDE.codemirror.on("change", () => {
                var current = this.easyMDE.value();
                this.textarea.val(current);
                if (editedInput.length) {
                    editedInput.val(current !== initialMarkdown ? '1' : '0');
                }
            });
        },

        bindEvents: function() {
            var self = this;

            // Клик по табам переключения режимов (Классический / Markdown)
            $('.wp-addon-builder-tab').on('click', function(e) {
                e.preventDefault();
                var targetMode = $(this).data('mode');
                var currentMode = self.modeInput.val();

                if (targetMode === currentMode) {
                    return;
                }

                if (targetMode === 'markdown') {
                    self.switchToMarkdown();
                } else {
                    self.switchToClassic();
                }
            });

            // Кнопка принудительного импорта HTML -> Markdown
            $('#wp-addon-btn-convert-to-md').on('click', function(e) {
                e.preventDefault();
                var i18n = self.getI18n();
                var htmlVal = self.getClassicEditorContent();
                if (confirm(i18n.overwrite_md_confirm)) {
                    self.convertContent('html_to_md', htmlVal, function(md) {
                        self.setMarkdownEditorContent(md);
                    });
                }
            });

            // Кнопка принудительного импорта Markdown -> HTML
            $('#wp-addon-btn-convert-to-html').on('click', function(e) {
                e.preventDefault();
                var i18n = self.getI18n();
                var mdVal = self.easyMDE ? self.easyMDE.value() : self.textarea.val();
                if (confirm(i18n.overwrite_html_confirm)) {
                    self.convertContent('md_to_html', mdVal, function(html) {
                        self.setClassicEditorContent(html);
                    });
                }
            });

            // Синхронизация перед сохранением формы
            $('#post').on('submit', function() {
                var mode = self.modeInput.val() || 'classic';

                if (mode === 'markdown' && self.easyMDE) {
                    var current = self.easyMDE.value();
                    self.textarea.val(current);
                    // Для поддержки предпросмотра и ревизий WP также заполняем #content сконвертированным HTML
                    if (typeof self.easyMDE.markdown === 'function') {
                        try {
                            $('#content').val(self.easyMDE.markdown(current));
                        } catch (err) {
                            // Игнорируем ошибку рендера на клиенте
                        }
                    }
                } else if (mode === 'classic') {
                    if (typeof tinymce !== 'undefined') {
                        tinymce.triggerSave();
                    }
                }
            });

            // Добавление справки под Markdown-редактор
            this.addMarkdownHelp();
        },

        switchToMarkdown: function() {
            var self = this;
            var i18n = this.getI18n();
            var mdVal = this.easyMDE ? this.easyMDE.value().trim() : this.textarea.val().trim();
            var htmlVal = this.getClassicEditorContent().trim();

            // Если Markdown пуст, а в классическом редакторе есть HTML — предлагаем конвертировать
            if (mdVal === '' && htmlVal !== '') {
                if (confirm(i18n.import_html_confirm)) {
                    this.convertContent('html_to_md', htmlVal, function(md) {
                        self.setMarkdownEditorContent(md);
                    });
                }
            }

            // Переключаем активный таб
            $('.wp-addon-builder-tab').removeClass('is-active');
            $('.wp-addon-builder-tab[data-mode="markdown"]').addClass('is-active');
            this.modeInput.val('markdown');

            // Показываем Markdown, скрываем TinyMCE
            $('#wp-addon-hide-tinymce').remove();
            $('<style id="wp-addon-hide-tinymce">#postdivrich { display: none !important; }</style>').appendTo('head');
            this.postdivrich.hide();
            this.container.show();
            this.btnConvertToMd.show();
            this.btnConvertToHtml.hide();

            // Обновляем CodeMirror после показа
            setTimeout(function() {
                if (self.easyMDE && self.easyMDE.codemirror) {
                    self.easyMDE.codemirror.refresh();
                    self.easyMDE.codemirror.focus();
                }
            }, 50);
        },

        switchToClassic: function() {
            var self = this;
            var i18n = this.getI18n();
            var mdVal = this.easyMDE ? this.easyMDE.value().trim() : this.textarea.val().trim();
            var htmlVal = this.getClassicEditorContent().trim();

            // Если классический редактор пуст, а в Markdown есть текст — предлагаем конвертировать
            if (htmlVal === '' && mdVal !== '') {
                if (confirm(i18n.import_md_confirm)) {
                    this.convertContent('md_to_html', mdVal, function(html) {
                        self.setClassicEditorContent(html);
                    });
                }
            }

            // Переключаем активный таб
            $('.wp-addon-builder-tab').removeClass('is-active');
            $('.wp-addon-builder-tab[data-mode="classic"]').addClass('is-active');
            this.modeInput.val('classic');

            // Показываем TinyMCE, скрываем Markdown
            $('#wp-addon-hide-tinymce').remove();
            this.container.hide();
            this.postdivrich.show();
            this.btnConvertToHtml.show();
            this.btnConvertToMd.hide();

            // Обновляем визуальный редактор если доступен
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                var editor = tinymce.get('content');
                if (!editor.isHidden()) {
                    editor.execCommand('mceAutoResize');
                }
            }
        },

        getClassicEditorContent: function() {
            if (typeof tinymce !== 'undefined') {
                var editor = tinymce.get('content');
                if (editor && !editor.isHidden()) {
                    return editor.getContent();
                }
            }
            return $('#content').val() || '';
        },

        setClassicEditorContent: function(html) {
            if (typeof tinymce !== 'undefined') {
                var editor = tinymce.get('content');
                if (editor) {
                    editor.setContent(html);
                }
            }
            $('#content').val(html);
        },

        setMarkdownEditorContent: function(md) {
            if (this.easyMDE) {
                this.easyMDE.value(md);
                if (this.easyMDE.codemirror) {
                    this.easyMDE.codemirror.refresh();
                }
            }
            this.textarea.val(md);
            $('#markdown_edited').val('1');
        },

        convertContent: function(direction, content, callback) {
            if (!content) {
                return;
            }

            var options = typeof markdownAjax !== 'undefined' ? markdownAjax : {};
            var ajaxUrl = options.ajax_url || window.ajaxurl;
            var nonce = options.nonce || '';
            var i18n = this.getI18n();

            var $btn = direction === 'html_to_md' ? this.btnConvertToMd : this.btnConvertToHtml;
            $btn.addClass('updating-message');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'markdown_convert',
                    direction: direction,
                    nonce: nonce,
                    content: content
                },
                success: function(res) {
                    $btn.removeClass('updating-message');
                    if (res && res.success && typeof callback === 'function') {
                        callback(res.data);
                    } else {
                        alert(i18n.convert_error);
                    }
                },
                error: function() {
                    $btn.removeClass('updating-message');
                    alert(i18n.convert_error);
                }
            });
        },

        getI18n: function() {
            var options = typeof markdownAjax !== 'undefined' ? markdownAjax : {};
            return options.i18n || {
                import_html_confirm: 'Импортировать текущий HTML контент в Markdown редактор?',
                import_md_confirm: 'Конвертировать Markdown в HTML для классического редактора?',
                overwrite_md_confirm: 'Заменить текущий Markdown контент результатом конвертации из HTML?',
                overwrite_html_confirm: 'Заменить текущий HTML в классическом редакторе результатом конвертации из Markdown?',
                convert_error: 'Ошибка при конвертации контента'
            };
        },

        addMarkdownHelp: function() {
            var helpHtml = '<details class="markdown-help" style="margin: 12px 16px; padding: 8px 12px; background: #f0f6fc; border: 1px solid #d0d7de; border-radius: 6px; font-size: 12px; color: #50575e;">' +
                '<summary style="cursor: pointer; font-weight: 600; color: #1d2327;">' +
                'Markdown Справка (нажмите, чтобы развернуть)' +
                '</summary>' +
                '<ul style="margin: 8px 0 0 16px; padding: 0;">' +
                '<li><strong># Заголовок 1</strong> — большой заголовок</li>' +
                '<li><strong>## Заголовок 2</strong> — средний заголовок</li>' +
                '<li><strong>**жирный**</strong> — жирный текст</li>' +
                '<li><strong>*курсив*</strong> — курсивный текст</li>' +
                '<li><strong>[текст](url)</strong> — ссылка</li>' +
                '<li><strong>![alt](url)</strong> — изображение</li>' +
                '<li><strong>`код`</strong> — inline код</li>' +
                '<li><strong>* пункт</strong> — маркированный список</li>' +
                '<li><strong>1. пункт</strong> — нумерованный список</li>' +
                '<li><strong>Горячие клавиши:</strong> Ctrl+B (жирный), Ctrl+I (курсив), Ctrl+K (ссылка)</li>' +
                '</ul>' +
                '</details>';

            $('#markdown-editor-container').append(helpHtml);
        }
    };

    // Инициализация
    MarkdownEditor.init();

    // Глобальный доступ для отладки
    window.MarkdownEditor = MarkdownEditor;
});