<?php
/**
 * Accordion FAQ. Bootstrap 3 collapse. Single elements.
 *
 * @year: 2019-11-20
 */

require_once 'model/ShortcodeInterface.php';
require_once 'FAQ.php';

class FAQ_Question implements ShortcodeInterface
{
    public $id;

    public $tag;

    public $title;

    public $description;

    public $icon;

    public $parent_tag;

    public $single_tag;

    public static $instance = 0;

    public $last_group;

    /** @var object */
    public $parent_params;

    public function __construct($tag = '', $title = null, $description = null, $icon = null)
    {
        $this->tag = $tag ?: 'question';
        $this->parent_tag = 'faq';
        $this->single_tag = $this->tag;

        $this->title = __('Question', 'wp-addon');
        $this->description = __('Question', 'wp-addon');

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
            'title' => '',
        ], $atts, $this->tag);

        if ($this->last_group < FAQ::$instance) {
            static::$instance = 0;
        }
        static::$instance++;

        $this->parent_params = FAQ::$params;
        $this->last_group = FAQ::$instance;
        $this->id = $this->last_group.'acc'.static::$instance;

        if (isset($this->parent_params->type) && $this->parent_params->type === 'show_first') {

            $aria_expanded = (static::$instance === 1) ? 'true' : 'false';
            $panel_collapse = (static::$instance === 1) ? 'collapse in' : 'collapse';

        } elseif (isset($this->parent_params->type) && $this->parent_params->type === 'hidden') {

            $aria_expanded = 'false';
            $panel_collapse = 'collapse';

        } else { // default

            $aria_expanded = 'true';
            $panel_collapse = 'collapse in';
        }

        ob_start();
        ?>
        <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <h3 itemprop="name">
                <a role="button" data-parent="#accordion<?= $this->last_group?>" href="#<?= $this->id?>" aria-expanded="<?php echo $aria_expanded ?>" data-toggle="collapse">
                <?= $atts->title?>
                </a>
            </h3>
            <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer" id="<?= $this->id?>" class="panel-collapse <?php echo $panel_collapse?>" role="tabpanel">
                <div itemprop="text">
                    <div style="padding-bottom: 20px"><?php echo do_shortcode($content) ?></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * VC mapping. Render Admin Settings.
     *
     * @throws Exception
     */
    public function vc_support()
    {
        if (! function_exists('vc_map')) {
            return;
        }

        vc_map([
            'category' => __('Elements', 'wp-addon'),
            'name' => __('Question', 'gillion'),
            'base' => $this->single_tag,
            'as_child' => ['only' => $this->parent_tag],
            'as_parent' => [''],
            'allowed_container_element' => 'vc_row',
            'js_view' => 'VcColumnView',
            'icon' => get_stylesheet_directory_uri().'/img/elements/tab.svg',
            'params' => array_merge(
                [
                    [
                        'type' => 'textfield',
                        'holder' => 'div',
                        'admin_label' => true,
                        'heading' => esc_html__('Question', 'wp-addon'),
                        'param_name' => 'tab_title',
                        'description' => __('Question for answer', 'wp-addon'),
                    ],
                ]
            ),
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
