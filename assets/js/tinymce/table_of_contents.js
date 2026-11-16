(function() {
    tinymce.PluginManager.add('table_of_contents', function( editor, url ) {
        editor.addButton('table_of_contents', {
            title : 'Table of content',
            image: 'https://img.icons8.com/wired/64/000000/overview-pages-4.png',
            //'https://img.icons8.com/dotty/80/000000/overview-pages-1.png',
            //icon: 'dashicons-text-page',
            onclick: function() {
                editor.windowManager.open( {
                    title: 'Shortcode params',
                    body: [
                        {
                            type: 'textbox',
                            name: 'title',
                            label: 'Title',
                            value: ''
                        },
                        {
                            type: 'textbox',
                            name: 'headings',
                            label: 'Headings',
                            value: 'h2,h3,h4'
                        },
                        {
                            type: 'textbox',
                            name: 'content',
                            label: 'jQuery Selector of content',
                            value: 'div.post-content'
                        },
                        {
                            type: 'listbox',
                            name: 'style',
                            label: 'Choose style',
                            'values': [
                                {text: 'Default', value: 'default'},
                                {text: 'Style 1', value: 'style1'},
                                {text: 'Style 2', value: 'style2'}
                            ]
                        }
                    ],
                    onsubmit: function( e ) {
                        //editor.insertContent( '<h3 class="toc-title">' + e.data.title + '</h3>');
                        editor.insertContent( '<p>[table_of_contents style="' + e.data.style + '" headings="' + e.data.headings + '" content="' + e.data.content + '" title="' + e.data.title + '"]</p>');
                        editor.insertContent( '<hr>');
                    }
                });
            }
        });
    });
})();