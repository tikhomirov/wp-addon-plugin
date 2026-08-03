<?php

/**
 * Showing ID of posts
 */
function show_id()
{
    function true_id($args)
    {
        $args['post_page_id'] = 'ID';

        return $args;
    }

    function true_custom($column, $id)
    {
        if ($column === 'post_page_id') {
            echo $id;
        }
    }

    add_filter('manage_pages_columns', 'true_id', 99);
    add_action('manage_pages_custom_column', 'true_custom', 99, 2);

    // manage_(post_type)_custom_column
    add_filter('manage_posts_columns', 'true_id', 99);
    add_action('manage_posts_custom_column', 'true_custom', 99, 2);

    add_filter('manage_media_columns', 'posts_columns_attachment_id', 999);
    add_action('manage_media_custom_column', 'posts_custom_columns_attachment_id', 999, 2);
    function posts_columns_attachment_id($defaults)
    {
        $defaults['post_attachments_id'] = __('ID');

        return $defaults;
    }

    function posts_custom_columns_attachment_id($column_name, $id)
    {
        if ($column_name === 'post_attachments_id') {
            echo $id;
        }

    }

    /**
     * style
     */
    add_action('admin_print_scripts', 'action_function_columns');
    function action_function_columns($data)
    {
        // action...
        echo '<style>
			th#post_attachments_id {
			    width: 50px;
			}
			th#post_page_id {
			    width: 50px;
			}
		</style>';
    }
}
