(function () {
    tinymce.PluginManager.add('bootstrap', function (editor) {
        function insert(content) {
            editor.insertContent(content);
        }

        function wrapSelection(html) {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && tinyMCE.activeEditor.selection) {
                tinyMCE.activeEditor.selection.setContent(html);
                return;
            }

            insert(html);
        }

        editor.addButton('bootstrap', {
            icon: 'bootstrap',
            type: 'menubutton',
            text: 'Bootstrap 3',
            title: 'Bootstrap 3 shortcodes',
            menu: [
                {
                    text: 'Dividers',
                    menu: [
                        {
                            text: 'Divider Full',
                            onclick: function () {
                                insert('<hr />');
                            }
                        },
                        {
                            text: 'Divider Short',
                            onclick: function () {
                                insert('<hr class="short">');
                            }
                        },
                        {
                            text: 'Divider Dashed',
                            onclick: function () {
                                insert('<hr class="dashed">');
                            }
                        }
                    ]
                },
                {
                    text: 'Typography',
                    menu: [
                        {
                            text: 'Lead paragraph',
                            onclick: function () {
                                wrapSelection('<p class="lead">Lead paragraph text</p>');
                            }
                        },
                        {
                            text: 'Blockquote',
                            onclick: function () {
                                wrapSelection('<blockquote><p>Quote text</p><footer>Author</footer></blockquote>');
                            }
                        },
                        {
                            text: 'Label default',
                            onclick: function () {
                                insert('<span class="label label-default">Label</span>');
                            }
                        },
                        {
                            text: 'Badge',
                            onclick: function () {
                                insert('<span class="badge">1</span>');
                            }
                        }
                    ]
                },
                {
                    text: 'Lists',
                    menu: [
                        {
                            text: 'Check List',
                            onclick: function () {
                                wrapSelection('<ul class="list list-style-check"><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>');
                            }
                        },
                        {
                            text: 'Star list',
                            onclick: function () {
                                insert('<ul class="list list-style-star"><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>');
                            }
                        }
                    ]
                },
                {
                    text: 'Grid',
                    menu: [
                        {
                            text: '2 columns',
                            onclick: function () {
                                wrapSelection('<div class="row"><div class="col-sm-6">Column 1</div><div class="col-sm-6">Column 2</div></div>');
                            }
                        },
                        {
                            text: '3 columns',
                            onclick: function () {
                                insert('<div class="row"><div class="col-sm-4">Column 1</div><div class="col-sm-4">Column 2</div><div class="col-sm-4">Column 3</div></div>');
                            }
                        },
                        {
                            text: '4 columns',
                            onclick: function () {
                                insert('<div class="row"><div class="col-sm-3">Column 1</div><div class="col-sm-3">Column 2</div><div class="col-sm-3">Column 3</div><div class="col-sm-3">Column 4</div></div>');
                            }
                        },
                        {
                            text: 'Sidebar layout (8/4)',
                            onclick: function () {
                                insert('<div class="row"><div class="col-sm-8">Main content</div><div class="col-sm-4">Sidebar</div></div>');
                            }
                        }
                    ]
                },
                {
                    text: 'Buttons',
                    menu: [
                        {
                            text: 'Default button',
                            onclick: function () {
                                insert('<a class="btn btn-default" href="#">Button</a>');
                            }
                        },
                        {
                            text: 'Primary button',
                            onclick: function () {
                                insert('<a class="btn btn-primary" href="#">Button</a>');
                            }
                        },
                        {
                            text: 'Success button',
                            onclick: function () {
                                insert('<a class="btn btn-success" href="#">Button</a>');
                            }
                        },
                        {
                            text: 'Button group',
                            onclick: function () {
                                insert('<div class="btn-group" role="group"><a class="btn btn-default" href="#">Left</a><a class="btn btn-default" href="#">Middle</a><a class="btn btn-default" href="#">Right</a></div>');
                            }
                        }
                    ]
                },
                {
                    text: 'Alerts',
                    menu: [
                        {
                            text: 'Info alert',
                            onclick: function () {
                                insert('<div class="alert alert-info" role="alert"><strong>Info:</strong> Message text.</div>');
                            }
                        },
                        {
                            text: 'Success alert',
                            onclick: function () {
                                insert('<div class="alert alert-success" role="alert"><strong>Success:</strong> Message text.</div>');
                            }
                        },
                        {
                            text: 'Warning alert',
                            onclick: function () {
                                insert('<div class="alert alert-warning" role="alert"><strong>Warning:</strong> Message text.</div>');
                            }
                        },
                        {
                            text: 'Danger alert',
                            onclick: function () {
                                insert('<div class="alert alert-danger" role="alert"><strong>Error:</strong> Message text.</div>');
                            }
                        }
                    ]
                },
                {
                    text: 'Panels',
                    menu: [
                        {
                            text: 'Default panel',
                            onclick: function () {
                                insert('<div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">Panel title</h3></div><div class="panel-body">Panel content</div></div>');
                            }
                        },
                        {
                            text: 'Primary panel',
                            onclick: function () {
                                insert('<div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">Panel title</h3></div><div class="panel-body">Panel content</div></div>');
                            }
                        }
                    ]
                },
                {
                    text: 'Wells',
                    menu: [
                        {
                            text: 'Default well',
                            onclick: function () {
                                wrapSelection('<div class="well">Well content</div>');
                            }
                        },
                        {
                            text: 'Large well',
                            onclick: function () {
                                wrapSelection('<div class="well well-lg">Large well content</div>');
                            }
                        },
                        {
                            text: 'Small well',
                            onclick: function () {
                                wrapSelection('<div class="well well-sm">Small well content</div>');
                            }
                        }
                    ]
                },
                {
                    text: 'Jumbotron',
                    onclick: function () {
                        insert('<div class="jumbotron"><h1>Heading</h1><p>Lead copy for a simple hero unit.</p><p><a class="btn btn-primary btn-lg" href="#" role="button">Learn more</a></p></div>');
                    }
                },
                {
                    text: 'FAQ',
                    onclick: function () {
                        insert('[faq]\n' +
                            '[question title="Question 1"] Answer 1... [/question]\n' +
                            '[question title="Question 2"] Answer 2... [/question]\n' +
                            '[question title="Question 3"] Answer 3... [/question]\n' +
                            '[/faq]');
                    }
                }
            ]
        });
    });
})();
