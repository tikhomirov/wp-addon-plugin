<?php
/**
 * Accordion FAQ. Bootstrap 3 collapse with SEO. Wrapper.
 *
 * @year: 2019-11-20
 */

require_once 'model/ShortcodeInterface.php';

class FAQ implements ShortcodeInterface
{
    public $tag;

    public $title;

    public $description;

    public $icon;

    public $parent_tag;

    public $single_tag;

    public static $instance = 0;

    public static $params = [];

    public function __construct($tag = '', $title = null, $description = null, $icon = null)
    {
        $this->tag = $tag ?: 'faq';
        $this->parent_tag = $this->tag;
        $this->single_tag = 'question';

        $this->title = __('FAQ', 'wp-addon');
        $this->description = __('FAQ', 'wp-addon');

        $this->icon = file_exists(get_stylesheet_directory().'/img/black_color.svg') ?
            get_stylesheet_directory_uri().'/img/black_color.svg' :
            'https://cdn1.iconfinder.com/data/icons/sugar-glyph/64/174_sugar-white-cubes-512.png';

        add_shortcode($this->tag, [$this, 'html']);
        add_action('init', [$this, 'vc_support']);

        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('admin_head', [$this, 'tiny_mce_support']);

        add_action('init', [$this, 'pll_support']);
    }

    public function html($atts, $content = null)
    {

        $atts = (object) shortcode_atts([
            'type' => 'default',
        ], $atts, $this->tag);

        /** @var string - default = show all, show_first = show first, hidden - hidden all content */
        $atts->type;

        self::$instance++;
        self::$params = $atts;

        ob_start();
        ?>
        <div itemscope itemtype="https://schema.org/FAQPage" id="accordion<?= self::$instance?>" class="panel-group accordion" role="tablist" aria-multiselectable="true">
            <?php echo do_shortcode($content); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @throws Exception
     */
    public function vc_support()
    {
        if (! function_exists('vc_map')) {
            return;
        }

        vc_map([
            'name' => __('FAQ Accordion', 'gillion'),
            'base' => $this->parent_tag,
            'description' => __('Accordion Collapse.js', 'gillion'),
            'category' => __('Elements', 'wp-addon'),
            'icon' => get_stylesheet_directory_uri().'/img/elements/tabs.svg', // https://www.flaticon.com/packs/website-7
            'as_parent' => ['only' => $this->single_tag],
            'js_view' => 'VcColumnView',
            'content_element' => true,
            'show_settings_on_create' => true,
            'is_container' => true,
            'params' => [
                [
                    'type' => 'textfield',
                    'holder' => 'div',
                    'admin_label' => true,
                    'heading' => esc_html__('Section Title', 'rw-addon'),
                    'param_name' => 'section_title',
                    'description' => esc_html__('Section Title', 'rw-addon'),
                    'value' => '',
                ],
                [
                    'type' => 'dropdown',
                    'admin_label' => true,
                    'heading' => esc_html__('Tabs Style', 'wdo-tabs'),
                    'param_name' => 'style',
                    'value' => [
                        'Select Style' => 'style',
                        'Style1' => 'style1',
                        'Style2' => 'style2',
                    ],
                ],
                [
                    'type' => 'dropdown',
                    'admin_label' => true,
                    'heading' => esc_html__('Color Scheme', 'wdo-tabs'),
                    'param_name' => 'wdo_color_scheme',
                    'group' => esc_html__('Color Scheme', 'wdo-tabs'),
                    'value' => [
                        'Select Color Scheme' => '',
                        'Blue' => 'blue',
                        'Green' => 'green',
                        'MidNight Blue' => 'midnightblue',
                        'Orange' => 'orange',
                    ],
                ],
            ],
        ]);
    }

    public function assets()
    {
        // TODO: Implement assets() method.
    }

    public function tiny_mce_support()
    {
        // TODO: Implement tiny_mce_support() method.
    }

    public function admin_script($screen)
    {
        // TODO: Implement admin_script() method.
    }

    public function pll_support() {}
}

function faq_shortcode()
{
    new FAQ('faq');
    new FAQ_Question('question');

    if (class_exists('WPBakeryShortCodesContainer')) {
        class WPBakeryShortCode_faq extends WPBakeryShortCodesContainer {}
        class WPBakeryShortCode_question extends WPBakeryShortCodesContainer {}
    }
}
