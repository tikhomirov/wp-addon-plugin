<?php
/**
 * Adv
 */

/*newdocument
bold
italic
underline
strikethrough
alignleft
aligncenter
alignright
alignjustify
alignnone
styleselect
formatselect
fontselect
fontsizeselect
cut
copy
paste
outdent
indent
blockquote
undo
redo
removeformat
subscript
superscript
visualaid
insert
hr
bullist
numlist
link
unlink
openlink
image
charmap
pastetext
print
    preview
anchor
pagebreak
spellchecker
searchreplace
visualblocks
visualchars
code
help
fullscreen
insertdatetime
media
nonbreaking
save
cancel
table
tabledelete
tablecellprops
tablemergecells
tablesplitcells
tableinsertrowbefore
tableinsertrowafter
tabledeleterow
tablerowprops
tablecutrow
tablecopyrow
tablepasterowbefore
tablepasterowafter
tableinsertcolbefore
tableinsertcolafter
tabledeletecol
rotateleft
rotateright
flipv
fliph
editimage
imageoptions
fullpage
ltr
rtl
emoticons
template
forecolor
backcolor
restoredraft
insertfile
a11ycheck
toc
quickimage
quicktable
quicklink*/

function tiny_advanced()
{

    add_filter('tiny_mce_before_init', function ($in) {

        $in['font_formats'] = 'Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Courier New=courier new,courier;';

        $in['toolbar1'] = 'insertfile undo redo | blockquote styleselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify alignnone | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons | wp_more';
        /* if(class_exists('\JsonFileManager\Model\JsonFile')) {
             $file = new \JsonFileManager\Model\JsonFile(null,null,'buttons.json',
                 ['basedir' => RW_PLUGIN_DIR . 'functions/TinyMCE/',
                     'baseurl' => RW_PLUGIN_URL . 'functions/TinyMCE/',
                 ]);
             $buttons = $file->read();
             if(!empty($buttons)){
                 $in['toolbar2'] = '';
                 foreach ($buttons as $button){
                     if( false == strpos($in['toolbar1'], $button['control']) ){
                         $in['toolbar2'] .= $button['control'] . ' ';
                     }
                 }
             }
         }*/
        $in['toolbar2'] = 'formatselect fontselect fontsizeselect | table | subscript superscript removeformat | insert unlink openlink charmap code | cut copy paste pastetext';

        $in['fontsize_formats'] = '10px 12px 14px 15px 18px 20px 24px 28px 30px 32px 36px 48px';
        $in['font_formats'] .= 'Open Sans=Open Sans,sans-serif;';
        $in['menubar'] = 'file edit insert view format table tools help';

        return $in;
    }, 9, 1);

    // Add Google Scripts for use with the editor
    add_action('init', function () {
        $fonts_url = [
            'https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800',
            'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
        ];
        foreach ($fonts_url as $font_url) {
            add_editor_style(str_replace(',', '%2C', $font_url));
        }
    });
}

function tiny_table_plugin()
{
    add_filter('mce_buttons_2', function ($buttons) {
        array_push($buttons, 'separator', '| table |');

        return $buttons;
    }, 10, 1);

    add_filter('mce_external_plugins', function ($plugins) {
        global $tinymce_version;
        $plugins['icofonts'] = RW_PLUGIN_URL.'functions/TinyMCE/plugins/icofonts/plugin.min.js';
        $plugins['table'] = RW_PLUGIN_URL.'functions/TinyMCE/plugins/table/plugin.min.js';

        return $plugins;
    }, 10, 1);

    add_filter('tiny_mce_before_init', function ($settings, $editor_id) {

        // $tinymce_settings['menubar'] = true;
        /*$tinymce_settings = [
                'table_tab_navigation'     => false,
                'table_resize_bars'        => false,
                'table_responsive_width'   => true,
                'table_appearance_options' => false,
                'invalid_styles'           => json_encode(['td' => 'width height', 'th' => 'width height']),
                'table_default_styles'     => json_encode([
                    'width'  => '',
                    'height' => '',
                ])
        ];*/

        $tinymce_settings['table_class_list'] = json_encode([
            ['title' => 'None', 'value' => ''],

            ['title' => 'None + 1/2', 'value' => 't-col-2'],
            ['title' => 'None + 1/3', 'value' => 't-col-3'],
            ['title' => 'None + 1/4', 'value' => 't-col-4'],
            ['title' => 'None + 1/5', 'value' => 't-col-5'],
            ['title' => 'None + 1/6', 'value' => 't-col-6'],

            ['title' => 'Default', 'value' => 'table'],

            ['title' => 'Default + 1/2', 'value' => 'table t-col-2'],
            ['title' => 'Default + 1/3', 'value' => 'table t-col-3'],
            ['title' => 'Default + 1/4', 'value' => 'table t-col-4'],
            ['title' => 'Default + 1/5', 'value' => 'table t-col-5'],
            ['title' => 'Default + 1/6', 'value' => 'table t-col-6'],

            ['title' => 'Striped rows', 'value' => 'table table-striped'],

            ['title' => 'Striped + 1/2', 'value' => 'table table-striped t-col-2'],
            ['title' => 'Striped + 1/3', 'value' => 'table table-striped t-col-3'],
            ['title' => 'Striped + 1/4', 'value' => 'table table-striped t-col-4'],
            ['title' => 'Striped + 1/5', 'value' => 'table table-striped t-col-5'],
            ['title' => 'Striped + 1/6', 'value' => 'table table-striped t-col-6'],

            ['title' => 'Striped hoverable rows', 'value' => 'table table-hover table-striped'],

            /*
            ['title' => 'Bordered table', 'value' => 'table table-bordered'],
            ['title' => 'Borderless table', 'value' => 'table table-borderless'],
            ['title' => 'Hoverable rows', 'value' => 'table table-hover'],
            */

            /*
            ['title' => 'Small table', 'value' => 'table table-sm'],
            ['title' => 'Small striped hoverable rows', 'value' => 'table table-sm table-hover table-striped'],
            ['title' => 'Dark', 'value' => 'table table-dark'],
            ['title' => 'Dark striped rows', 'value' => 'table table-striped table-dark'],
            ['title' => 'Dark bordered table', 'value' => 'table table-bordered table-dark'],
            ['title' => 'Dark borderless table', 'value' => 'table table-borderless table-dark'],
            ['title' => 'Dark hoverable rows', 'value' => 'table table-hover table-dark'],
            ['title' => 'Dark small rows', 'value' => 'table table-sm table-dark'],
            */
        ]);

        $tinymce_settings['icons'] = 'material';

        return array_merge($settings, $tinymce_settings);
    }, 10, 2);

    add_action('admin_footer', function () {
        ?>
        <style>
            .mce-floatpanel.mce-arrow-up {
                margin-top: 120px;
                box-shadow: 0 0 40px #3333333d;
            }
        </style>
        <?php
    });
}

function tiny_visual_block()
{
    add_filter('tiny_mce_before_init', function ($in) {
        $in['toolbar2'] .= ',visualblocks';
        $in['visualblocks_default_state'] = true;

        return $in;
    });
    add_filter('mce_external_plugins', function ($plugins) {
        $plugins['visualblocks'] = RW_PLUGIN_URL.'functions/TinyMCE/plugins/visualblocks/plugin.min.js';

        return $plugins;
    }, 10, 1);
}

function upload_feature()
{
    add_filter('media_upload_tabs', function ($tabs) {
        $tabs['g_drive'] = 'Google Drive';

        return $tabs;
    });

    // call the new tab with wp_iframe
    add_action('media_upload_'.'g_drive', function () {
        wp_iframe('my_new_form');
    });

    // the tab content
    function my_new_form()
    {
        media_upload_header(); // This function is used for print media uploader headers etc.
        echo '<div style=" margin: 10px auto;text-align: center;"><h3>Coming soon..</h3></div>';
    }
}
