<?php
/**
 * WP_External_Widget_RSS
 */
class WP_Widget_External_RSS extends WP_Widget
{
    /**
     * Sets up a new RSS widget instance.
     *
     * @since 2.8.0
     */
    public function __construct()
    {
        $widget_ops = [
            'description' => __('External RSS feed.'),
            'customize_selective_refresh' => true,
            'show_instance_in_rest' => true,

        ];
        $control_ops = [
            'width' => 400,
            'height' => 200,
        ];

        parent::__construct('external_rss', __('External RSS'), $widget_ops, $control_ops);
    }

    /**
     * Outputs the content for the current RSS widget instance.
     *
     * @param  array  $args  Display arguments including 'before_title', 'after_title',
     *                       'before_widget', and 'after_widget'.
     * @param  array  $instance  Settings for the current RSS widget instance.
     *
     * @since 2.8.0
     */
    public function widget($args, $instance)
    {

        if (isset($instance['error']) && $instance['error']) {
            console_log($instance['error']);

            return;
        }

        $url = ! empty($instance['url']) ? $instance['url'] : '';
        while (! empty($url) && stristr($url, 'http') !== $url) {
            $url = substr($url, 1);
        }

        if (empty($url)) {
            console_log('Empty URL');

            return;
        }

        // Self-URL destruction sequence.
        if (in_array(untrailingslashit($url), [site_url(), home_url()],
            true)
        ) {
            console_log('Self hosted!');

            return;
        }

        $rss = fetch_feed($url);
        $title = $instance['title'];
        $desc = '';
        $link = '';

        if (! is_wp_error($rss)) {
            $desc
                = esc_attr(strip_tags(html_entity_decode($rss->get_description(),
                    ENT_QUOTES, get_option('blog_charset'))));
            if (empty($title)) {
                $title = strip_tags($rss->get_title());
            }
            $link = strip_tags($rss->get_permalink());
            while (! empty($link) && stristr($link, 'http') !== $link) {
                $link = substr($link, 1);
            }
        } else {
            console_log($rss->get_error_message());
        }

        if (empty($title)) {
            $title = ! empty($desc) ? $desc : __('Unknown Feed');
        }

        /** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
        $title = apply_filters('widget_title', $title, $instance,
            $this->id_base);

        if ($title) {
            $feed_link = '';
            $feed_url = strip_tags($url);
            $feed_icon = includes_url('images/rss.png');
            $feed_link = sprintf(
                '<a class="rsswidget rss-widget-feed" href="%1$s" target="_blank" rel="nofollow"><img class="rss-widget-icon" style="border:0" width="14" height="14" src="%2$s" alt="%3$s"%4$s /></a> ',
                esc_url($feed_url),
                esc_url($feed_icon),
                esc_attr__('RSS'),
                (wp_lazy_loading_enabled('img', 'rss_widget_feed_icon')
                    ? ' loading="lazy"' : '')
            );

            /**
             * Filters the classic RSS widget's feed icon link.
             *
             * Themes can remove the icon link by using `add_filter( 'rss_widget_feed_link', '__return_empty_string' );`.
             *
             * @param  string|false  $feed_link  HTML for link to RSS feed.
             * @param  array  $instance  Array of settings for the current widget.
             *
             * @since 5.9.0
             */
            $feed_link = apply_filters('rss_widget_feed_link', $feed_link,
                $instance);

            $title = $feed_link.'<a class="rsswidget rss-widget-title" href="'
                .esc_url($link).'" rel="nofollow" target="_blank">'.esc_html($title).'</a>';
        }

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'].$title.$args['after_title'];
        }

        $format = current_theme_supports('html5', 'navigation-widgets')
            ? 'html5' : 'xhtml';

        /** This filter is documented in wp-includes/widgets/class-wp-nav-menu-widget.php */
        $format = apply_filters('navigation_widgets_format', $format);

        if ($format === 'html5') {
            // The title may be filtered: Strip out HTML and make sure the aria-label is never empty.
            $title = trim(strip_tags($title));
            $aria_label = $title ?: __('RSS Feed');
            echo '<nav aria-label="'.esc_attr($aria_label).'">';
        }

        wp_widget_rss_output($rss, $instance);

        if ($format === 'html5') {
            echo '</nav>';
        }

        echo $args['after_widget'];

        if (! is_wp_error($rss)) {
            $rss->__destruct();
        }
        unset($rss);
    }

    /**
     * Handles updating settings for the current RSS widget instance.
     *
     * @param  array  $new_instance  New settings for this instance as input by the user via
     *                               WP_Widget::form().
     * @param  array  $old_instance  Old settings for this instance.
     * @return array Updated settings to save.
     *
     * @since 2.8.0
     */
    public function update($new_instance, $old_instance)
    {
        $testurl = (isset($new_instance['url'])
            && (! isset($old_instance['url'])
                || ($new_instance['url'] !== $old_instance['url'])));

        // var_dump($new_instance, $old_instance); die();
        return wp_widget_rss_process($new_instance, $testurl);
    }

    /**
     * Outputs the settings form for the RSS widget.
     *
     * @param  array  $instance  Current settings.
     *
     * @since 2.8.0
     */
    public function form($instance)
    {
        if (empty($instance)) {
            $instance = [
                'title' => '',
                'url' => '',
                'items' => 10,
                'error' => false,
                'show_summary' => 0,
                'show_author' => 0,
                'show_date' => 0,
            ];
        }
        $instance['number'] = $this->id;

        $args = $instance;
        $inputs = null;

        $default_inputs = [
            'url' => true,
            'title' => true,
            'items' => true,
            'show_summary' => true,
            'show_author' => true,
            'show_date' => true,
        ];
        $inputs = wp_parse_args($inputs, $default_inputs);

        $args['title'] = isset($args['title']) ? $args['title'] : '';
        $args['url'] = isset($args['url']) ? $args['url'] : '';
        $args['items'] = isset($args['items']) ? (int) $args['items'] : 0;

        if ($args['items'] < 1 || $args['items'] > 20) {
            $args['items'] = 10;
        }

        $args['show_summary'] = isset($args['show_summary']) ? (int) $args['show_summary'] : (int) $inputs['show_summary'];
        $args['show_author'] = isset($args['show_author']) ? (int) $args['show_author'] : (int) $inputs['show_author'];
        $args['show_date'] = isset($args['show_date']) ? (int) $args['show_date'] : (int) $inputs['show_date'];

        if (! empty($args['error'])) {
            echo '<p class="widget-error"><strong>'.__('RSS Error:').'</strong> '.esc_html($args['error']).'</p>';
        }

        $esc_number = esc_attr($args['number']);
        if ($inputs['url']) {
            ?>
            <p><label for="<?php echo $this->get_field_id('url'); ?>">
                    <?php _e('Enter the RSS feed URL here:'); ?>
                </label>
                <input class="widefat"
                       id="<?php echo $this->get_field_id('url'); ?>"
                       name="<?php echo $this->get_field_name('url'); ?>" type="text"
                       value="<?php echo esc_attr($instance['url']); ?>" />
            </p>
        <?php }
        if ($inputs['title']) { ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>">
                <?php _e('Give the feed a title (optional):'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($instance['title']); ?>" />
        </p>
    <?php }
        if ($inputs['items']) { ?>
        <p><label for="<?php echo $this->get_field_id('items'); ?>">
                <?php _e('How many items would you like to display?'); ?>
            </label>
            <select id="<?php echo $this->get_field_id('items'); ?>"
                    name="<?php echo $this->get_field_name('items'); ?>"
            >
                <?php
                    for ($i = 1; $i <= 20; $i++) {
                        echo "<option value='$i' ".selected($args['items'], $i, false).">$i</option>";
                    }
            ?>
            </select>
        </p>
    <?php }

        }
}

add_action('widgets_init', function () {
    register_widget(WP_Widget_External_RSS::class);
});
