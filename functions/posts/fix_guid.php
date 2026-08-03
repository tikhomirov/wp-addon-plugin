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
    add_filter('admin_menu', function () {
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
        $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace('@&krg=.*@', '', $url);
        $separator = ' | ';
        $tab = $_GET['krg'] ?? '';

        ?>

        <div class="wrap">

            <div class="icon32"></div>
            <h2><?= __('Guid repair', 'wp-addon'); ?></h2>

            <h1 class="screen-reader-text">GUID</h1>

            <ul class="subsubsub">
                <li>
                    <a href='<?= $url ?>&krg=look_all_guide' class=" <?= $tab === 'look_all_guide' || $tab === '' ? 'bold' : ''; ?>">
                        <?= __('View all GUID', 'wp-addon')?>
                    </a> <?= $separator ?>
                </li>
                <li><a href='<?= $url ?>&krg=update_all_guid' class=" <?= $tab === 'update_all_guid' ? 'bold' : ''; ?>"
                       title='Обновить в БД в таблице "posts" все поля guide ( в них запишутся постоянные ссылки на страницы )'>
                        <?= __('Update all GUID', 'wp-addon')?>
                    </a> <?= $separator ?>
                </li>
                <li><a href='<?= $url ?>&krg=look_all_revision' class=" <?= $tab === 'look_all_revision' ? 'bold' : ''; ?>"
                       title='Посмотреть все существующие в БД в таблице "posts" ревизии записей'>
		                <?= __('All revision', 'wp-addon')?>
                    </a> <?= $separator ?>
                </li>
                <li><a href='<?= $url ?>&krg=delete_all_revision' class=" <?= $tab === 'delete_all_revision' ? 'bold' : ''; ?>"
                       title='Удалить все ревизии и соответствующие им поля в таблицах term_relationships и postmeta'>
		                <?= __('Remove all revision', 'wp-addon')?></a>
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
            switch ($_GET['krg'] ?? '') {

                case 'update_all_guid' :
                    krg_guid('update');
                    break;
                case 'look_all_revision' :
                    look_all_revision();
                    break;
                case 'delete_all_revision' :
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

    /* ========= GUID ========= */
    /* Обновить все поля Guid в БД таблице posts. Функция запишет в эти поля пермалинки страниц. Функция на установку постоянных ссылок в БД (permalink)
    --------------------------------------------------------------------------------------- */
    function krg_guid($action)
    {

        global $wpdb;

        $post_types = get_post_types(['public' => true], 'names');
        if (! $post_types) {
            return null;
        }
        unset($post_types['attachment']);

        $post_types = "'".implode("','", $post_types)."'";
        $SQL = "SELECT ID, post_date, post_title, guid
        FROM $wpdb->posts p
        WHERE p.post_type IN ($post_types)
        AND p.post_status = 'publish'";
        $results = $wpdb->get_results($SQL);

        if (! $results) {
            return print 'Запрос вернул пустой результат';
        }

        // Обновить все поля Guid в БД таблице posts.
        if ($action == 'update') {
            echo "<div id='submitdiv' class='postbox'>
				<h3 style='margin:0;padding:8px;'><span>№ / ID / guid</span></h3>
				<ol style='padding-left:20px;'>";

            foreach ($results as $reslt) {
                $guid = $reslt->guid;

                $permalink = get_permalink($reslt->ID);

                if ($wpdb->query("UPDATE $wpdb->posts SET guid = '$permalink' WHERE ID = $reslt->ID LIMIT 1")) {
                    echo "<li> Обновлено: <span>id: $reslt->ID</span> <a href='$permalink' title='guid который был: $guid '>$permalink</a></li>";
                } else {
                    echo "<li>Не обнволено: id: $reslt->ID: <a href='$permalink' title='guid который был: $guid '>$permalink</a></li>";
                }
            }
            echo '</ol></div>';

        } // Посмотреть все поля Guid в БД таблице posts.
        elseif ($action == 'look') {

            echo "<div id='submitdiv' class='postbox'>
				<h3 style='margin:0;padding:8px;'><span>№ / ID / guid</span></h3>
				<ol style='padding-left:20px;'>";

            foreach ($results as $reslt) {
                $ID = $reslt->ID;
                $guid = $reslt->guid;

                (strpos($guid, '?p=')
                 || strpos($guid,
                     '?page_id=')) !== false ? $style = " style='color:#f00;'" : $style = " style='color:green;'";

                echo "<li><span title='ID поста или страницы'>id: $ID</span>  <a $style href='$guid'>$guid</a></li>";
            }
            echo '</ol></div>';

        }
    }

    /* ========= РЕВИЗИИ ========= */
    /* Удалить все ревизии и соответствующие им поля в таблицах term_relationships и postmeta
    ------------------------------------------------------- */
    function delete_all_revision()
    {
        global $wpdb;

        $sql = "DELETE a,b,c,d
	FROM $wpdb->posts a
		LEFT JOIN $wpdb->term_relationships b ON (a.ID = b.object_id)
		LEFT JOIN $wpdb->postmeta c ON (a.ID = c.post_id)
		LEFT JOIN $wpdb->comments d ON (a.ID = d.comment_post_ID)
	WHERE a.post_type = 'revision'";
        $wpdb->query($sql);

        echo "<font color='green'>Все ревизии были удалены из БД posts и соответствующие им поля в таблицах term_relationships, postmeta и wp_comments</font>";
    }

    /* Посмотреть все ревизии
    -------------------------------------------------------- */
    function look_all_revision()
    {
        global $wpdb;

        if (! $results = $wpdb->get_results("SELECT ID, post_date, post_title, post_status, guid, post_type FROM $wpdb->posts WHERE post_type = 'revision'")) {
            return print "<font color='green'>Ревизий не найдено. Запрос вернул пустой результат</font>";
        }

        $d = 0;
        $rrr = '';
        foreach ($results as $reslt) {
            $rrr .= "<li><font color='green'>".++$d.".</font> id: {$reslt->ID} | guid: <font color='red'>{$reslt->guid}</font> </li>";
        }
        echo "<ul>$rrr</ul>";
    }
}
