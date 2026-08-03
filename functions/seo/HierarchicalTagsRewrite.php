<?php

/**
 * Hierarchical Tags Rewrite Rules
 * Description: Adds proper rewrite rules for hierarchical tags to support nested URLs like /tag/git/submodules/
 * Version: 1.0.0
 */
class HierarchicalTagsRewrite
{
    /**
     * Class constructor.
     */
    public function __construct()
    {
        add_filter('post_tag_rewrite_rules', [$this, 'tag_rewrite_rules'], 10, 1);
        add_filter('term_link', [$this, 'hierarchical_tag_link'], 10, 3);

        add_action('created_post_tag', [$this, 'schedule_flush']);
        add_action('edited_post_tag', [$this, 'schedule_flush']);
        add_action('delete_post_tag', [$this, 'schedule_flush']);

        add_action('init', [$this, 'flush'], PHP_INT_MAX);
    }

    /**
     * Generate hierarchical tag link.
     *
     * @param  string  $termlink  Term link URL.
     * @param  WP_Term  $term  Term object.
     * @param  string  $taxonomy  Taxonomy slug.
     * @return string Modified term link.
     */
    public function hierarchical_tag_link($termlink, $term, $taxonomy)
    {
        if ($taxonomy !== 'post_tag' || ! $term->parent) {
            return $termlink;
        }

        $tag_base = get_option('tag_base');
        if (empty($tag_base)) {
            $tag_base = 'tag';
        }

        $slug = $term->slug;
        if ($term->parent) {
            $parents = $this->get_tag_parents($term->parent, false, '/', true);
            if (! is_wp_error($parents)) {
                $slug = $parents.$slug;
            }
        }

        return home_url(user_trailingslashit($tag_base.'/'.$slug, 'category'));
    }

    /**
     * Save an option that triggers a flush on the next init.
     */
    public function schedule_flush()
    {
        update_option('flush_rewrite_tags', 1);
    }

    /**
     * If the flush option is set, flush the rewrite rules.
     *
     * @return bool
     */
    public function flush()
    {
        if (get_option('flush_rewrite_tags')) {
            add_action('shutdown', 'flush_rewrite_rules');
            delete_option('flush_rewrite_tags');

            return true;
        }

        return false;
    }

    /**
     * Generate rewrite rules for hierarchical tags.
     *
     * @param  array  $tag_rewrite  Existing tag rewrite rules.
     * @return array Modified tag rewrite rules.
     */
    public function tag_rewrite_rules($tag_rewrite)
    {
        global $wp_rewrite;

        $new_tag_rewrite = [];

        $taxonomy = get_taxonomy('post_tag');
        if (! $taxonomy || ! isset($taxonomy->rewrite['hierarchical']) || ! $taxonomy->rewrite['hierarchical']) {
            return $tag_rewrite;
        }

        $tag_base = get_option('tag_base');
        if (empty($tag_base)) {
            $tag_base = 'tag';
        }

        $tag_base = trim($tag_base, '/');

        $tags = get_terms([
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
        ]);

        if (is_array($tags) && ! empty($tags)) {
            foreach ($tags as $tag) {
                $tag_nicename = $tag->slug;

                if ($tag->parent === $tag->term_id) {
                    $tag->parent = 0;
                } elseif ($tag->parent !== 0) {
                    $parents = $this->get_tag_parents($tag->parent, false, '/', true);
                    if (! is_wp_error($parents)) {
                        $tag_nicename = $parents.$tag_nicename;
                    }
                    unset($parents);
                }

                $new_tag_rewrite = $this->add_tag_rewrites(
                    $new_tag_rewrite,
                    $tag_nicename,
                    $tag_base,
                    $wp_rewrite->pagination_base
                );

                $tag_nicename_filtered = $this->convert_encoded_to_upper($tag_nicename);
                if ($tag_nicename_filtered !== $tag_nicename) {
                    $new_tag_rewrite = $this->add_tag_rewrites(
                        $new_tag_rewrite,
                        $tag_nicename_filtered,
                        $tag_base,
                        $wp_rewrite->pagination_base
                    );
                }
            }
            unset($tags, $tag, $tag_nicename, $tag_nicename_filtered);
        }

        // Merge new rules BEFORE default rules so they take precedence
        return array_merge($new_tag_rewrite, $tag_rewrite);
    }

    /**
     * Get tag parents path.
     *
     * @param  int  $id  Tag ID.
     * @param  bool  $link  Whether to format with link.
     * @param  string  $separator  Path separator.
     * @param  bool  $nicename  Whether to use nice name for display.
     * @return string|WP_Error Tag parents path or WP_Error on failure.
     */
    protected function get_tag_parents($id, $link = false, $separator = '/', $nicename = false)
    {
        $chain = '';
        $parent = get_term($id, 'post_tag');

        if (is_wp_error($parent)) {
            return $parent;
        }

        if ($nicename) {
            $name = $parent->slug;
        } else {
            $name = $parent->name;
        }

        if ($parent->parent && ($parent->parent !== $parent->term_id)) {
            $chain .= $this->get_tag_parents($parent->parent, $link, $separator, $nicename);
        }

        if ($link) {
            $chain .= '<a href="'.esc_url(get_term_link($parent)).'">'.$name.'</a>'.$separator;
        } else {
            $chain .= $name.$separator;
        }

        return $chain;
    }

    /**
     * Adds required tag rewrite rules.
     *
     * @param  array  $rewrites  The current set of rules.
     * @param  string  $tag_name  Tag nicename (hierarchical path).
     * @param  string  $tag_base  Tag base.
     * @param  string  $pagination_base  WP_Query pagination base.
     * @return array The added set of rules.
     */
    protected function add_tag_rewrites($rewrites, $tag_name, $tag_base, $pagination_base)
    {
        $rewrite_name = $tag_base.'/('.$tag_name.')';

        // Extract the actual slug from the hierarchical path (e.g. 'git/submodules' -> 'submodules')
        $parts = explode('/', $tag_name);
        $actual_slug = end($parts);

        $rewrites[$rewrite_name.'/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?tag='.$actual_slug.'&feed=$matches[2]';
        $rewrites[$rewrite_name.'/'.$pagination_base.'/?([0-9]{1,})/?$'] = 'index.php?tag='.$actual_slug.'&paged=$matches[2]';
        $rewrites[$rewrite_name.'/?$'] = 'index.php?tag='.$actual_slug;

        return $rewrites;
    }

    /**
     * Walks through tag nicename and convert encoded parts into uppercase.
     *
     * @param  string  $name  The encoded tag URI string.
     * @return string The converted URI string.
     */
    protected function convert_encoded_to_upper($name)
    {
        if (strpos($name, '%') === false) {
            return $name;
        }

        $names = explode('/', $name);
        $names = array_map([$this, 'encode_to_upper'], $names);

        return implode('/', $names);
    }

    /**
     * Converts the encoded URI string to uppercase.
     *
     * @param  string  $encoded  The encoded string.
     * @return string The uppercased string.
     */
    public function encode_to_upper($encoded)
    {
        if (strpos($encoded, '%') === false) {
            return $encoded;
        }

        return strtoupper($encoded);
    }
}

function hierarchical_tags_rewrite()
{
    static $instance = null;
    if ($instance === null) {
        $instance = new HierarchicalTagsRewrite;
    }

    return $instance;
}
