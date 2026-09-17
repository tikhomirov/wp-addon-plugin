<?php
/**
Plugin Name: Ремонтирует Guid
Version: 1.2
Plugin URI: http://wp-kama.ru/
Description: Плагин нужен для просмотра/редактирования поля guid в базе данных WordPress. В это поле будет записаны постоянные ссылки (permalink) на статью. Бонус: просмотр/грамотное удаление всевозможных ревизий :)
Author: Kama
Author URI: http://wp-kama.ru/
*/
function write_right_guid()
{
    add_action('save_post', 'guid_write', 99);
    function guid_write($id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if ($id === (int) $id) {
            global $wpdb;
            $wpdb->update($wpdb->posts, ['guid' => get_permalink($id)], ['ID' => $id]);
        }
        clean_post_cache($id);
    }
}

function fix_guid()
{
    add_filter('admin_menu', static function () {
        add_options_page(
            __('Guid repair', 'wp-addon'),
            __('Guid repair', 'wp-addon'),
            'manage_options',
            'krg_admin_page',
            'krg_admin_page'
        );
    });

    function krg_admin_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wp-addon'));
        }

        $tab = isset($_GET['krg']) ? sanitize_key((string) wp_unslash($_GET['krg'])) : 'look_all_guide';
        $baseUrl = admin_url('options-general.php?page=krg_admin_page');
        $separator = ' | ';

        if (
            $tab === 'update_all_guid'
            && isset($_POST['krg_update_all_guid'])
            && check_admin_referer('krg_update_all_guid')
        ) {
            krg_guid('update');
            $tab = 'look_all_guide';
        }

        ?>

        <div class="wrap">

            <div class="icon32"></div>
            <h2><?php esc_html_e('Guid repair', 'wp-addon'); ?></h2>

            <h1 class="screen-reader-text">GUID</h1>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url(add_query_arg('krg', 'look_all_guide', $baseUrl)); ?>" class="<?php echo $tab === 'look_all_guide' ? 'bold' : ''; ?>">
                        <?php esc_html_e('View all GUID', 'wp-addon'); ?>
                    </a> <?php echo esc_html($separator); ?>
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('krg', 'update_all_guid', $baseUrl)); ?>" class="<?php echo $tab === 'update_all_guid' ? 'bold' : ''; ?>"
                       title="<?php esc_attr_e('Update all GUID fields in the posts table with permalinks.', 'wp-addon'); ?>">
                        <?php esc_html_e('Update all GUID', 'wp-addon'); ?>
                    </a> <?php echo esc_html($separator); ?>
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('krg', 'look_all_revision', $baseUrl)); ?>" class="<?php echo $tab === 'look_all_revision' ? 'bold' : ''; ?>"
                       title="<?php esc_attr_e('View all revisions stored in the posts table.', 'wp-addon'); ?>">
                        <?php esc_html_e('All revision', 'wp-addon'); ?>
                    </a> <?php echo esc_html($separator); ?>
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('krg', 'delete_all_revision', $baseUrl)); ?>" class="<?php echo $tab === 'delete_all_revision' ? 'bold' : ''; ?>"
                       title="<?php esc_attr_e('Delete all revisions and related rows.', 'wp-addon'); ?>">
                        <?php esc_html_e('Remove all revision', 'wp-addon'); ?>
                    </a>
                </li>
            </ul>
            <br class="clear">
            <style>
                .bold {
                    font-weight: bold;
                }
                .subsubsub{
                    text-transform: uppercase;
                }
            </style>

            <?php
            switch ($tab) {
                case 'update_all_guid':
                    krg_render_guid_update_form();
                    break;
                case 'look_all_revision':
                    look_all_revision();
                    break;
                case 'delete_all_revision':
                    delete_all_revision();
                    break;
                default:
                    krg_guid('look');
                    break;
            }
        ?>
        </div>
        <?php
    }

    function krg_render_guid_update_form(): void
    {
        ?>
        <div id="submitdiv" class="postbox">
            <h3 style="margin:0;padding:8px;"><span><?php esc_html_e('Bulk GUID replacement', 'wp-addon'); ?></span></h3>
            <div style="padding:12px 20px;">
                <p><?php esc_html_e('This action replaces GUID values for all published public posts with their permalinks. RSS subscribers may receive duplicate items.', 'wp-addon'); ?></p>
                <form method="post" action="<?php echo esc_url(add_query_arg('krg', 'update_all_guid', admin_url('options-general.php?page=krg_admin_page'))); ?>" onsubmit="return confirm('<?php echo esc_js(__('Replace GUID values for all published posts?', 'wp-addon')); ?>');">
                    <?php wp_nonce_field('krg_update_all_guid'); ?>
                    <input type="hidden" name="krg_update_all_guid" value="1">
                    <?php submit_button(__('Replace all GUID values', 'wp-addon'), 'delete', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    function krg_guid($action)
    {
        global $wpdb;

        $postTypes = get_post_types(['public' => true], 'names');

        if (! is_array($postTypes) || $postTypes === []) {
            return null;
        }

        unset($postTypes['attachment']);
        $placeholders = implode(', ', array_fill(0, count($postTypes), '%s'));
        $sql = $wpdb->prepare(
            "SELECT ID, post_date, post_title, guid
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
            AND post_status = 'publish'",
            ...array_values($postTypes)
        );
        $results = $wpdb->get_results($sql);

        if (! $results) {
            echo '<p>'.esc_html__('The query returned no results.', 'wp-addon').'</p>';

            return null;
        }

        if ($action === 'update') {
            echo "<div id='submitdiv' class='postbox'>
				<h3 style='margin:0;padding:8px;'><span>№ / ID / guid</span></h3>
				<ol style='padding-left:20px;'>";

            foreach ($results as $reslt) {
                $guid = $reslt->guid;
                $permalink = get_permalink((int) $reslt->ID);
                $updated = $wpdb->update(
                    $wpdb->posts,
                    ['guid' => $permalink],
                    ['ID' => (int) $reslt->ID],
                    ['%s'],
                    ['%d']
                );

                if ($updated !== false) {
                    echo '<li>'.esc_html__('Updated:', 'wp-addon').' <span>id: '.esc_html((string) $reslt->ID).'</span> <a href="'.esc_url($permalink).'" title="'.esc_attr(sprintf(__('Previous GUID: %s', 'wp-addon'), $guid)).'">'.esc_html($permalink).'</a></li>';
                } else {
                    echo '<li>'.esc_html__('Not updated:', 'wp-addon').' id: '.esc_html((string) $reslt->ID).': <a href="'.esc_url($permalink).'" title="'.esc_attr(sprintf(__('Previous GUID: %s', 'wp-addon'), $guid)).'">'.esc_html($permalink).'</a></li>';
                }
            }
            echo '</ol></div>';

            return null;
        }

        if ($action === 'look') {
            echo "<div id='submitdiv' class='postbox'>
				<h3 style='margin:0;padding:8px;'><span>№ / ID / guid</span></h3>
				<ol style='padding-left:20px;'>";

            foreach ($results as $reslt) {
                $ID = (int) $reslt->ID;
                $guid = $reslt->guid;
                $style = (strpos($guid, '?p=') !== false || strpos($guid, '?page_id=') !== false)
                    ? " style='color:#f00;'"
                    : " style='color:green;'";

                echo '<li><span title="'.esc_attr__('Post or page ID', 'wp-addon').'">id: '.esc_html((string) $ID).'</span>  <a'.$style.' href="'.esc_url($guid).'">'.esc_html($guid).'</a></li>';
            }
            echo '</ol></div>';
        }

        return null;
    }

    function delete_all_revision()
    {
        global $wpdb;

        $sql = "DELETE a,b,c,d
	FROM {$wpdb->posts} a
		LEFT JOIN {$wpdb->term_relationships} b ON (a.ID = b.object_id)
		LEFT JOIN {$wpdb->postmeta} c ON (a.ID = c.post_id)
		LEFT JOIN {$wpdb->comments} d ON (a.ID = d.comment_post_ID)
	WHERE a.post_type = 'revision'";
        $wpdb->query($sql);

        echo '<p style="color:green;">'.esc_html__('All revisions were removed from posts and related rows in term_relationships, postmeta, and comments.', 'wp-addon').'</p>';
    }

    function look_all_revision()
    {
        global $wpdb;

        $results = $wpdb->get_results("SELECT ID, post_date, post_title, post_status, guid, post_type FROM {$wpdb->posts} WHERE post_type = 'revision'");

        if (! $results) {
            echo '<p style="color:green;">'.esc_html__('No revisions found.', 'wp-addon').'</p>';

            return null;
        }

        echo '<ul>';
        foreach ($results as $index => $reslt) {
            echo '<li><span style="color:green;">'.esc_html((string) ($index + 1)).'.</span> id: '.esc_html((string) $reslt->ID).' | guid: <span style="color:red;">'.esc_html($reslt->guid).'</span></li>';
        }
        echo '</ul>';

        return null;
    }
}
