<?php

require_once dirname(__DIR__).'/main-settings-helpers.php';

/**
 * CategoriesFilter
 */
final class CategoriesFilter
{
    private static $instance;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __clone() {}

    public function __wakeup() {}

    /**
     * CategoriesFilter constructor.
     */
    private function __construct()
    {
        add_action('wp', [$this, 'wp_cat_filter']);
        add_filter('get_next_post_excluded_terms', [$this, 'exclude_cat'], 10, 1);
        add_filter('get_previous_post_excluded_terms', [$this, 'exclude_cat'], 10, 1);
        add_action('pre_get_posts', [$this, 'filter_run'], 10);
    }

    /**
     * @return array<int, int>
     */
    private function get_settings(): array
    {
        $options = wp_addon_main_settings();
        $catIds = $options['posts']['exclude_cat_val'] ?? null;

        return wp_addon_normalize_category_exclude_ids($catIds);
    }

    public function filter_run(WP_Query $query)
    {
        if (is_admin()) {
            return;
        }

        $cats = $this->get_settings();

        if ($cats !== []) {
            $query->set('category__not_in', $cats);
        }
    }

    /**
     * @param  array<int, int>|null  $excluded_terms
     * @return array<int, int>|null
     */
    public function exclude_cat($excluded_terms = null)
    {
        $cats = $this->get_settings();

        if ($cats !== []) {
            $excluded_terms = $cats;
        }

        return apply_filters('exclude_categories_filter', $excluded_terms);
    }

    /**
     * Redirect from exclude category to home page
     */
    public function wp_cat_filter()
    {
        global $wp_query;

        if (! $wp_query instanceof WP_Query || ! $wp_query->is_archive || ! $wp_query->is_category) {
            return;
        }

        $term = get_queried_object();

        if (! $term instanceof WP_Term) {
            return;
        }

        $catIds = $this->exclude_cat();

        if (! is_array($catIds) || ! in_array((int) $term->term_id, $catIds, true)) {
            return;
        }

        wp_safe_redirect(get_home_url(), 301, 'wp-addon');
        exit;
    }
}

if (! function_exists('exclude_cat')) {
    function exclude_cat()
    {
        CategoriesFilter::getInstance();
    }
}
