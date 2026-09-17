/**
 * Markdown Editor JavaScript
 */
jQuery(document).ready(function($) {
    
    // Инициализация редактора
    var MarkdownEditor = {
        textarea: null,
        easyMDE: null,
        
        init: function() {
            this.textarea = $('#markdown-textarea');
            
            if (this.textarea.length === 0) {
                return;
            }
            
            this.initEasyMDE();
            this.bindEvents();
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
                        var customUploader;
                        if (customUploader) {
                            customUploader.open();
                            return;
                        }
                        
                        customUploader = wp.media.frames.file_frame = wp.media({
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
                            var stat = cm.getTokenAt(cm.getCursor());
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
            
            // Синхронизация EasyMDE с textarea для сохранения
            this.easyMDE.codemirror.on("change", () => {
                this.textarea.val(this.easyMDE.value());
            });
        },
        
        bindEvents: function() {
            // Добавление справки
            this.addMarkdownHelp();
        },
        
        addMarkdownHelp: function() {
            var helpHtml = '<div class="markdown-help">' +
                '<h4>Markdown Справка:</h4>' +
                '<ul>' +
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
                '</div>';
            
            $('#markdown-editor-container').append(helpHtml);
        }
    };
    
    // Инициализация
    MarkdownEditor.init();
    
    // Глобальный доступ для отладки
    window.MarkdownEditor = MarkdownEditor;
});