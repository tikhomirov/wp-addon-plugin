<?php

// add settings tabs

add_action('wp_addon_settings_section', [Advertising::class, 'set_settings'], 10, 1);

class Advertising
{
    public $placement;

    public $banner;

    private $selector;

    /**
     * Advertising constructor.
     *
     * @param  string  $placement  - placement: 'before_content', 'in_content', 'after_content'
     * @param  string  $banner  - html code
     */
    public function __construct($placement, $banner, $selector = null)
    {
        $this->placement = $placement;
        $this->banner = $banner;
        $this->selector = $selector;
        $this->select_placement();
    }

    /**
     * Выбор места вывода поста
     */
    public function select_placement()
    {
        switch ($this->placement) {
            case 'before_content':
                add_filter('the_content', [$this, 'banner_before_content'], 8, 1);
                break;
            case 'in_content':
                add_filter('the_content', [$this, 'banner_in_content'], 8, 1);
                break;
            case 'after_content':
                add_filter('the_content', [$this, 'banner_after_content'], 8, 1);
                break;
            default:
                $this->banner_jquery();
                break;
        }
    }

    /**
     * Баннер до поста
     *
     * @param  string  $content
     */
    public function banner_before_content($content): string
    {
        return $this->banner.$content;
    }

    /**
     * Баннер в середине поста
     */
    public function banner_in_content($content): string
    {
        $parts = $this->get_parts($content);
        $content = $parts[0].$this->banner.$parts[1];

        return $content;
    }

    /**
     * Баннер после контента поста
     */
    public function banner_after_content($content): string
    {
        return $content.$this->banner;
    }

    /**
     * Добавляет событие
     */
    public function banner_jquery()
    {
        add_action('wp_footer', [$this, 'js']);
    }

    /**
     * Вставлет баннер в выделенный селектор
     */
    public function js()
    {
        if ($this->selector && $this->banner) {
            $this->banner = str_replace(["\r", "\n"], '', $this->banner)
            ?>
            <script type="application/javascript">
                jQuery(document).ready(function($) {
                    $('<?= $this->selector?>').append('<?= $this->banner?>');
                })
        </script>
        <?php }
        }

    /**
     * Делит контент поста на 2 части
     */
    private function get_parts($text): array
    {
        $length = strlen($text);
        $half = (int) ($length / 2);

        $part_1 = substr($text, 0, $half);
        $part_2 = substr($text, $half);

        return [$part_1, $part_2];
    }

    /**
     * Generate settings tabs for ADs
     *
     * @param  string  $prefix
     */
    public static function set_settings($prefix)
    {

        $ads = [
            'title' => __('Ads manager', 'wp-addon'),
            'icon' => 'fa fa-external-link',
        ];

        $types = get_post_types(['public' => 1]);

        if (isset($types['attachment'])) {
            unset($types['attachment']);
        }

        $before = 'before_content';
        $in_conten = 'in_content';
        $after = 'after_content';

        /* $ads['fields'][] = [
             'id'      => 'init_ads_shortcode',
             'type'    => 'switcher',
             'title'   => __('Enable shortcode', 'wp-addon'),
             'label'   => __('Enable ads shortcodes', 'wp-addon'),
             'default' => true
         ];*/

        $ads['fields']['enq'] = [
            'id' => 'ads_enabler',
            'type' => 'switcher',
            'title' => __('Enable this extension?', 'wp-addon'),
            'label' => __('Change to On for enable this.', 'wp-addon'),
            'default' => false,
        ];

        $ads['fields'][] = [
            'id' => 'ads_in_content',
            'type' => 'tabbed',
            'title' => __('Ads in content', 'wp-addon'),
            'subtitle' => __('Displays HTML content in the selected location of the post', 'wp-addon'),
            'tabs' => [
                [
                    'title' => __('Before content', 'wp-addon'),
                    'icon' => 'fa fa-th-large',
                    'fields' => [
                        [
                            'id' => 'before_content_mode',
                            'type' => 'switcher',
                            'title' => __('ADs Mode', 'wp-addon'),
                            'label' => __('Off to run html mode', 'wp-addon'),
                            'default' => true,
                        ],
                        [
                            'id' => 'before_content_upload',
                            'type' => 'upload',
                            'title' => 'Upload',
                            'library' => 'image',
                            'placeholder' => 'http://',
                            'button_title' => 'Add Image',
                            'remove_title' => 'Remove Image',
                            'dependency' => ['before_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'before_content_url',
                            'type' => 'text',
                            'title' => __('Link', 'wp-addon'),
                            'dependency' => ['before_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'before_content_html',
                            'type' => 'code_editor',
                            'title' => __('Banner html code', 'wp-addon'),
                            'settings' => [
                                'theme' => 'mbo',
                                'mode' => 'htmlmixed',
                                'tabSize' => 4,
                            ],
                            'default' => '',
                            'dependency' => ['before_content_mode', '==', 'false'],
                        ],
                        [
                            'id' => 'before_content_post_type',
                            'type' => 'select',
                            'title' => __('Select post type', 'wp-addon'),
                            'multiple' => true,
                            'chosen' => true,
                            'placeholder' => __('Select post type', 'wp-addon'),
                            'options' => $types,
                            'default' => 'post',
                        ],
                    ],
                ],
                [
                    'title' => __('In content', 'wp-addon'),
                    'icon' => 'fa fa-th-large',
                    'fields' => [
                        [
                            'id' => 'in_content_mode',
                            'type' => 'switcher',
                            'title' => __('ADs Mode', 'wp-addon'),
                            'label' => __('Off to run html mode', 'wp-addon'),
                            'default' => true,
                        ],
                        [
                            'id' => 'in_content_upload',
                            'type' => 'upload',
                            'title' => 'Upload',
                            'library' => 'image',
                            'placeholder' => 'http://',
                            'button_title' => 'Add Image',
                            'remove_title' => 'Remove Image',
                            'dependency' => ['in_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'in_content_url',
                            'type' => 'text',
                            'title' => __('Link', 'wp-addon'),
                            'dependency' => ['in_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'in_content_html',
                            'type' => 'code_editor',
                            'title' => __('Banner html code', 'wp-addon'),
                            'settings' => [
                                'theme' => 'mbo',
                                'mode' => 'htmlmixed',
                                'tabSize' => 4,
                            ],
                            'default' => '',
                            'dependency' => ['in_content_mode', '==', 'false'],
                        ],
                        [
                            'id' => 'in_content_post_type',
                            'type' => 'select',
                            'title' => __('Select post type', 'wp-addon'),
                            'multiple' => true,
                            'chosen' => true,
                            'placeholder' => __('Select post type', 'wp-addon'),
                            'options' => $types,
                            'default' => 'post',
                        ],
                    ],
                ],
                [
                    'title' => __('After content', 'wp-addon'),
                    'icon' => 'fa fa-th-large',
                    'fields' => [
                        [
                            'id' => 'after_content_mode',
                            'type' => 'switcher',
                            'title' => __('ADs Mode', 'wp-addon'),
                            'label' => __('Off to run html mode', 'wp-addon'),
                            'default' => true,
                        ],
                        [
                            'id' => 'after_content_upload',
                            'type' => 'upload',
                            'title' => 'Upload',
                            'library' => 'image',
                            'placeholder' => 'http://',
                            'button_title' => 'Add Image',
                            'remove_title' => 'Remove Image',
                            'dependency' => ['after_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'after_content_url',
                            'type' => 'text',
                            'title' => __('Link', 'wp-addon'),
                            'dependency' => ['after_content_mode', '==', 'true'],
                        ],
                        [
                            'id' => 'after_content_html',
                            'type' => 'code_editor',
                            'title' => __('Banner html code', 'wp-addon'),
                            'settings' => [
                                'theme' => 'mbo',
                                'mode' => 'htmlmixed',
                                'tabSize' => 4,
                            ],
                            'default' => '',
                            'dependency' => ['after_content_mode', '==', 'false'],
                        ],
                        [
                            'id' => 'after_content_post_type',
                            'type' => 'select',
                            'title' => __('Select post type', 'wp-addon'),
                            'multiple' => true,
                            'chosen' => true,
                            'placeholder' => __('Select post type', 'wp-addon'),
                            'options' => $types,
                            'default' => 'post',
                        ],
                    ],
                ],
            ],
        ];

        $ads['fields'][] = [
            'id' => 'jquery_ads_mode',
            'type' => 'switcher',
            'title' => __('Enable jQuery ADs mode', 'wp-addon'),
            'label' => __('Advanced Advertising mode. For developers.', 'wp-addon'),
            'default' => false,
        ];

        $ads['fields'][] = [
            'id' => 'ads_jquery',
            'type' => 'repeater',
            'title' => __('Banner in jQuery selector'),
            'subtitle' => __('Displays HTML content in the selected jQuery selector, anywhere on the site.', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'ads_jquery_upload',
                    'type' => 'upload',
                    'title' => 'Upload',
                    'library' => 'image',
                    'placeholder' => 'http://',
                    'button_title' => __('Add Banner Image', 'wp-addon'),
                    'remove_title' => __('Remove Image', 'wp-addon'),
                ],
                [
                    'id' => 'js_selector',
                    'type' => 'text',
                    'title' => __('jQuery selector', 'wp-addon'),
                    'help' => __('Example: #div', 'wp-addon'),
                    'placeholder' => '',
                ],
                [
                    'id' => 'js_html',
                    'type' => 'code_editor',
                    'title' => __('Banner code', 'wp-addon'),
                    'settings' => [
                        'theme' => 'mbo',
                        'mode' => 'htmlmixed',
                        'tabSize' => 4,
                    ],
                    'default' => '',
                ],
            ],
        ];

        CSF::createSection($prefix, $ads);
    }
}

function ads_enabler()
{
    add_action('wp', 'rw_init_ads');
}

function rw_init_ads()
{
    $settings = get_option('wp-addon', []);

    $prefix['before'] = 'before_content';
    $prefix['in'] = 'in_content';
    $prefix['after'] = 'after_content';

    if (isset($settings['ads_in_content']) && is_array($settings['ads_in_content'])) {
        foreach ($settings['ads_in_content'] as $key => $value) {
            if ($key === 'before_content_mode') { // link + img
                $link = '';
                if ($value === '1') {
                    $img = $settings['ads_in_content']['before_content_upload'] ?? '';
                    $url = $settings['ads_in_content']['before_content_url'] ?? '';
                    if (! empty($img) && ! empty($url)) {
                        $link = '<a href="'.esc_url($url).'" class="banner '.$prefix['before'].'"><img src="'.esc_url($img).'"></a>';
                    }
                } else {
                    $link = $settings['ads_in_content']['before_content_html'] ?? '';
                }
                $html = $link ?: false;

                if ($html && validate_request_post_type($settings, $prefix['before'])) {
                    new Advertising($prefix['before'], $html);
                }

            } elseif ($key === 'in_content_mode') {

                $link = '';
                if ($value === '1') {
                    $img = $settings['ads_in_content']['in_content_upload'] ?? '';
                    $url = $settings['ads_in_content']['in_content_url'] ?? '';
                    if (! empty($img) && ! empty($url)) {
                        $link = '<a href="'.esc_url($url).'" class="banner '.$prefix['in'].'"><img src="'.esc_url($img).'"></a>';
                    }
                } else {
                    $link = $settings['ads_in_content']['in_content_html'] ?? '';
                }
                $html = $link ?: false;

                if ($html && validate_request_post_type($settings, $prefix['in'])) {
                    new Advertising($prefix['in'], $html);
                }

            } elseif ($key === 'after_content_mode') {

                $link = '';
                if ($value === '1') {
                    $img = $settings['ads_in_content']['after_content_upload'] ?? '';
                    $url = $settings['ads_in_content']['after_content_url'] ?? '';
                    if (! empty($img) && ! empty($url)) {
                        $link = '<a href="'.esc_url($url).'" class="banner '.$prefix['after'].'" ><img src="'.esc_url($img).'"></a>';
                    }
                } else {
                    $link = $settings['ads_in_content']['after_content_html'] ?? '';
                }
                $html = $link ?: false;

                if ($html && validate_request_post_type($settings, $prefix['after'])) {
                    new Advertising($prefix['after'], $html);
                }
            }
        }
    }
}

function validate_request_post_type($settings, $key)
{

    $allow_post_types = $settings['ads_in_content'][$key.'_post_type'];
    global $wp_query;
    if ($wp_query->is_main_query() && $wp_query->have_posts()) {
        if ($wp_query->get_queried_object() instanceof WP_Post) {
            $current_post_type = $wp_query->get_queried_object()->post_type;
            if (in_array($current_post_type, $allow_post_types, true)) {
                return true;
            }
        }
    }

    return false;
}

function jquery_ads_mode()
{
    // if work js mode is run
    $settings = get_option('wp-addon', []);
    if (isset($settings['ads_jquery']) && is_array($settings['ads_jquery'])) {
        foreach ($settings['ads_jquery'] as $element) {
            if (isset($element['js_html'], $element['js_selector']) && ! empty($element['js_selector'])
            ) {
                new Advertising('jquery', $element['js_html'], $element['js_selector']);
            }
        }
    }
}
