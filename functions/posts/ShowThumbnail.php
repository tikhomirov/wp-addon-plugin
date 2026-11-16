<?php

// Adds post thumbnails to the admin posts list table
class ShowThumbnail
{
    public function __construct()
    {
        add_action('init', [$this, 'add_post_thumbs_in_post_list_table'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 99);
    }

    public function admin_enqueue_scripts()
    {

        $theme = wp_get_theme();
        $ver = defined('WP_DEBUG_SCRIPT') ? current_time('timestamp') : $theme->get('Version');
        $dir_uri = get_template_directory_uri();
        $dir_path = get_template_directory();

        // js
        if (file_exists($dir_path . '/assets/js/plugins/lightcase.js')) {
            wp_register_script('lightcase', $dir_uri . '/assets/js/plugins/lightcase.js', ['jquery'], $ver, false);
        } else {
            wp_register_script('lightcase', 'https://cdnjs.cloudflare.com/ajax/libs/lightcase/2.5.0/js/lightcase.min.js', ['jquery'], $ver, false);
        }
        // css
        if (file_exists($dir_path . '/assets/css/plugins/lightcase.min.css')) {
            wp_register_style('lightcase', $dir_uri . '/assets/css/plugins/lightcase.min.css', $ver);
        } else {
            wp_register_style('lightcase', 'https://cdnjs.cloudflare.com/ajax/libs/lightcase/2.5.0/css/lightcase.min.css', $ver);
        }

        wp_enqueue_script('lightcase');
        wp_enqueue_style('lightcase');

        wp_add_inline_script('lightcase', 'jQuery(document).ready(function($){
            $("a[data-rel^=lightcase]").lightcase({
                typeMapping: {
                    "image": "webp,jpg,jpeg,gif,png,bmp",
                },
            });
        });');

        wp_add_inline_style('lightcase', '
        .thumbnail .dashicons.dashicons-format-image {
                width: 90px;
                height: 90px;
                font-size: 90px;
                text-align: center;
            }
            .thumbnail img {
                border-radius: 8px;
            }
            .manage-column.column-thumbnail {
                width: 110px;
                text-align: center;
        }');
    }

    public function add_post_thumbs_in_post_list_table()
    {
        // post types that support featured images
        $supports = get_theme_support('post-thumbnails');

        $ptype_names = ['post', 'page']; // explicit post types for the column

        // Auto-detect post types
        if (! isset($ptype_names)) {
            if ($supports === true) {
                $ptype_names = get_post_types(['public' => true], 'names');
                $ptype_names = array_diff($ptype_names, ['attachment']);
            } // per post type list
            elseif (is_array($supports)) {
                $ptype_names = $supports[0];
            }
        }

        // register filters for each post type
        foreach ($ptype_names as $ptype) {
            add_filter("manage_{$ptype}_posts_columns", [$this, 'add_thumb_column']);
            add_action("manage_{$ptype}_posts_custom_column", [$this, 'add_thumb_value'], 10, 2);
        }
    }

    // add admin column
    public function add_thumb_column($columns)
    {
        $num = 1; // insert after column index
        $new_columns = ['thumbnail' => '<span class="dashicons dashicons-format-image"></span>'];

        return array_slice($columns, 0, $num) + $new_columns + array_slice($columns, $num);
    }

    // render column cell
    public function add_thumb_value($colname, $post_id)
    {
        if ($colname === 'thumbnail') {
            $width = $height = 90;

            /*if( class_exists('\theme\Theme') ){
                $thumb = Theme::get_post_thumb('gillion-square-micro', $post_id, 'a');
            } */

            if ($thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true)) {
                if (function_exists('kama_thumb_img')) {
                    $thumb = kama_thumb_a_img([
                        'width'  => $width,
                        'height' => $height,
                        'crop'   => true,
                        'a_attr' => 'data-rel="lightcase"',
                    ], $thumbnail_id);
                } else {
                    $thumb = wp_get_attachment_image($thumbnail_id, [$width, $height], true);
                }
            } else { // from gallery...

                $attachments = get_children([
                    'post_parent'    => $post_id,
                    'post_mime_type' => 'image',
                    'post_type'      => 'attachment',
                    'numberposts'    => 1,
                    'order'          => 'DESC',
                ]);

                $attach = array_shift($attachments);
                if (isset($attach)) {
                    if (function_exists('kama_thumb_img')) {
                        $thumb = kama_thumb_img(
                            ['width' => $width, 'height' => $height, 'crop' => true],
                            $attach->ID,
                        );
                    } else {
                        $thumb = wp_get_attachment_image($attach->ID, [$width, $height], true);
                    }
                }
            }
            echo $thumb ?? '<span class="dashicons dashicons-format-image"></span>';
        }
    }
}

function show_thumbnail()
{
    new ShowThumbnail;
}
