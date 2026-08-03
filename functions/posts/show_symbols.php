<?php

function show_symbols()
{
    add_action('admin_footer', function () {
        $screen = get_current_screen();
        if (! isset($screen) || $screen->id !== 'post') {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function ($) {
                let input_length= '';
                let fields = $('[name^="post_data"]');
                fields.each(function (i, v){
                    let parent = $(this).parent();
                    input_length = 'symbols: ' + $(this).val().length;

                    parent.append('<span class="input_length icon is-small is-right">'+input_length+'</span>');
                    $(this).on('input', function () {
                        let parent = $(this).parent();
                        input_length = 'symbols: ' + $(this).val().length;

                        parent.children('.input_length').text( input_length);
                    })
                });
            });
        </script>
        <?php
    });
}
show_symbols();
