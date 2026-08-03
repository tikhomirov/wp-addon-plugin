<?php

function add_bootstrap_3()
{
    class TinyBootstrapExtends
    {
        public $name;

        public function __construct()
        {
            $this->name = 'bootstrap';

            add_action('admin_head', [$this, 'show']);
            add_filter('mce_css', [$this, 'add_mce_css']);
            add_action('admin_footer', [$this, 'get_shortcodes']);
        }

        public function show()
        {
            // check user permissions
            if (! current_user_can('edit_posts')) {
                return;
            }

            if (get_user_option('rich_editing') === 'true') { // check if WYSIWYG is enabled
                add_filter('mce_external_plugins', [$this, 'add_js_mce'], 20, 1);
                add_filter('mce_buttons_3', [$this, 'register_mce_button']);
            }

        }

        /**
         * Add JS
         */
        public function add_js_mce($plugin_array): array
        {
            $arr['bootstrap'] = RW_PLUGIN_URL.'assets/js/tinymce/bootstrap.js';

            return $plugin_array + $arr;
        }

        /**
         * Register new button in the editor
         *
         * @param  $buttons  array
         */
        public function register_mce_button(array $buttons): array
        {
            $buttons[] = $this->name;

            return $buttons;
        }

        /**
         * Show all shortcodes in JS
         *
         * @unused
         */
        public function get_shortcodes()
        {
            global $shortcode_tags; ?>
            <script type="text/javascript">
                let shortcodes_button = [];
                <?php $count = 0;
            foreach ($shortcode_tags as $tag => $code) {
                echo "shortcodes_button[{$count}] = '{$tag}';";
                $count++;
            } ?>
            </script>
            <?php
        }

        /**
         * Add custom scripts to Editor
         */
        public function add_mce_css($mce_css): string
        {
            $ver = '01';
            if (! empty($mce_css)) {
                $mce_css .= ',';
            }

            $file_path = get_theme_file_path('assets/css/plugins/bootstrap3.min.css');
            $file_url = get_theme_file_uri('assets/css/plugins/bootstrap3.min.css');

            if (file_exists($file_path)) {
                $ver = filemtime($file_path);
                $mce_css .= $file_url.'?'.$ver.',';
                $mce_css .= get_theme_file_uri('assets/css/theme.min.css').'?'.$ver.',';
                $mce_css .= get_theme_file_uri('assets/css/fonts.min.css').'?'.$ver.',';

            } else {
                $mce_css .= 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css?'.$ver;
                $mce_css .= ',';
                $mce_css .= 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css';
                $mce_css .= ',';
            }

            $mce_css .= RW_PLUGIN_URL.'assets/css/min/tiny.min.css?'.$ver;
            $mce_css .= ',';

            return $mce_css;
        }
    }

    new TinyBootstrapExtends;
}
