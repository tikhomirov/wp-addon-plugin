<?php

require_once dirname(__DIR__).'/main-settings-helpers.php';

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
        }

        public function show()
        {
            if (! current_user_can('edit_posts')) {
                return;
            }

            if (get_user_option('rich_editing') === 'true') {
                add_filter('mce_external_plugins', [$this, 'add_js_mce'], 20, 1);
                add_filter('mce_buttons_3', [$this, 'register_mce_button']);
            }
        }

        /**
         * @param  array<string, string>  $plugin_array
         * @return array<string, string>
         */
        public function add_js_mce($plugin_array): array
        {
            $plugin_array['bootstrap'] = RW_PLUGIN_URL.'assets/js/tinymce/bootstrap.js';

            return $plugin_array;
        }

        /**
         * @param  array<int, string>  $buttons
         * @return array<int, string>
         */
        public function register_mce_button(array $buttons): array
        {
            $buttons[] = $this->name;

            return $buttons;
        }

        public function add_mce_css($mce_css): string
        {
            $framework = wp_addon_get_tinymce_framework();
            $frameworkCss = wp_addon_get_tinymce_framework_stylesheet($framework);
            $ver = '01';

            if ($frameworkCss === null) {
                return (string) $mce_css;
            }

            if (! empty($mce_css)) {
                $mce_css .= ',';
            }

            $file_path = get_theme_file_path('assets/css/plugins/bootstrap3.min.css');
            $file_url = get_theme_file_uri('assets/css/plugins/bootstrap3.min.css');

            if ($framework === 'bootstrap3' && file_exists($file_path)) {
                $ver = (string) filemtime($file_path);
                $mce_css .= $file_url.'?'.$ver.',';
                $mce_css .= get_theme_file_uri('assets/css/theme.min.css').'?'.$ver.',';
                $mce_css .= get_theme_file_uri('assets/css/fonts.min.css').'?'.$ver.',';
            } else {
                $mce_css .= $frameworkCss.'?'.$ver.',';
            }

            if ($framework === 'bootstrap3') {
                $mce_css .= 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css,';
            }

            $mce_css .= RW_PLUGIN_URL.'assets/css/min/tiny.min.css?'.$ver;

            return $mce_css;
        }
    }

    new TinyBootstrapExtends;
}
