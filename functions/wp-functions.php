<?php
/*
1: Remove WP-Version im Header
2: WP-Emojis deaktivieren
3: Remove Windows Live Writer
4: Remove RSD-Link
5: Remove RSS links
6: Remove shortlink in the header
7: Remove adjacent links to posts in the header
8: Set limit post revisions to 5
9: Block http-requests by plugins/themes
10: Disable heartbeat
11: Disable new themes on major WP updates
12: Remove jQuery Migrate
13: Disable the XML-RPC
14: Remove post by email function
15: Disable URL-fields on comments
16: Disable URL auto-linking in comments
17: Remove login-shake on errors
18: Empty WP-Trash every 14 days
19: Allow SVG type download
20: Disable Pingback
21: Disable adminbar in front for non admin users
22: Add VK, OK to profile
23: Show usages memory and time to generate page
24: Deregister widgets
25: Remove license.txt и readme.html files
26: Add filter to metabox of post taxonomies
27: Disable select taxonomy in top of metabox in post-edit page
28: If posts have status "pending" show numbers it in menu
29: Showing message "Update wordpress" only admin
30: Repalse [...] to "Read more ..." for posts
31: Allow shortcode in "Text" widget
32: Get jquery from google cloud
33: Remove autotop function. Remove <p></p> tags in post content.
34: Allow WEBP support for media
35: Allow SVG support for media
36: Disable browser checking in dashboard
37: Change login error message
38: Extend login session to 1 year

 */

function wptweaker_setting_1()
{
    remove_action('wp_head', 'wp_generator'); // из заголовка
    add_filter('the_generator', '__return_empty_string'); // из фидов и URL
    if ( file_exists( ABSPATH . '/readme.txt'  ) ) {
	    unlink( ABSPATH . '/readme.txt' );
    }
}

/** Disable Emo */
function wptweaker_setting_2()
{
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
    add_filter( 'wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );
    function disable_emojis_tinymce( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, array( 'wpemoji' ) );
        }
    }

    function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            // This filter is documented in wp-includes/formatting.php
            $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
            $urls = array_diff( $urls, array( $emoji_svg_url ) );
        }
        return $urls;
    }
}

function wptweaker_setting_3()
{
    remove_action('wp_head', 'wlwmanifest_link');
}

function wptweaker_setting_4()
{
    remove_action('wp_head', 'rsd_link');
}

function wptweaker_setting_5()
{
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
}

function wptweaker_setting_6()
{
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'wp_shortlink_header');
}

function wptweaker_setting_7()
{
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
}

function wptweaker_setting_8()
{
    if (!defined('WP_POST_REVISIONS')) {
        define('WP_POST_REVISIONS', 5);
    }
}

function wptweaker_setting_9()
{
   add_filter( 'pre_http_request', '__return_true', 100 );
}

function wptweaker_setting_10()
{
    add_action('init', 'stop_heartbeat', 1);
    function stop_heartbeat()
    {
        wp_deregister_script('heartbeat');
    }
}
function wptweaker_setting_11()
{
    /**
     * Remove jQuery Migrate script from the jQuery bundle only in front end.
     *
     * @since 1.0
     *
     * @param WP_Scripts $scripts WP_Scripts object.
     */
    function rw_remove_jquery_migrate( $scripts ) {
        if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];

            if ( $script->deps ) { // Check whether the script has any dependencies
                $script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
            }
        }
    }

    add_action( 'wp_default_scripts', 'rw_remove_jquery_migrate' );
}

function wptweaker_setting_12()
{
    define( 'CORE_UPGRADE_SKIP_NEW_BUNDLED', true );
}

function wptweaker_setting_13()
{
    add_filter('xmlrpc_enabled', '__return_false' );
}

function wptweaker_setting_14()
{
    add_filter( 'enable_post_by_email_configuration', '__return_false' );
}

function wptweaker_setting_15()
{
    // disable aggressive update
    if (is_admin()) {
        remove_action('admin_init', '_maybe_update_core');
        remove_action('admin_init', '_maybe_update_plugins');
        remove_action('admin_init', '_maybe_update_themes');

        remove_action('load-plugins.php', 'wp_update_plugins');
        remove_action('load-themes.php', 'wp_update_themes');
    }
}

function wptweaker_setting_16()
{
    remove_filter('comment_text', 'make_clickable', 9);
}

function wptweaker_setting_17()
{
    function wpt_login_shake()
    {
        remove_action('login_head', 'wp_shake_js', 12);
    }
    add_action('login_head', 'wpt_login_shake');
}

function wptweaker_setting_18()
{
    if (!defined('EMPTY_TRASH_DAYS')) {
        define('EMPTY_TRASH_DAYS', 14 );
    }
}

function wptweaker_setting_19(){
    function upload_allow_types( $mimes ) {
        // разрешаем новые типы
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        $mimes['doc']  = 'application/msword';
        $mimes['woff'] = 'font/woff';
        $mimes['psd']  = 'image/vnd.adobe.photoshop';
        $mimes['djv']  = 'image/vnd.djvu';
        $mimes['djvu'] = 'image/vnd.djvu';
        $mimes['webp'] = 'image/webp';
        // отключаем имеющиеся
        unset( $mimes['mp4a'] );
        return $mimes;
    }
    add_filter( 'upload_mimes', 'upload_allow_types' );
}

function wptweaker_setting_20()
{
    // Отключаем пинги на свои же посты
    add_action('pre_ping', function (&$links) {
        foreach ($links as $k => $val) {
            if (false !== strpos($val, str_replace('www.', '', $_SERVER['HTTP_HOST']))) {
                unset($links[$k]);
            }
        }
    });
}

function wptweaker_setting_21()
{
    /* Отключение админ-бара для всех, кроме админа */
    function disable_admin_bar()
    {
        if ( ! current_user_can('edit_posts')) {
            add_filter('show_admin_bar', '__return_false');
            add_action('admin_print_scripts-profile.php', 'hide_admin_bar_settings');
        }
    }
    add_action('init', 'disable_admin_bar', 9);
}

function wptweaker_setting_22()
{
    // удаляет из профиля поля: AIM, Yahoo IM, Jabber / Add VK, OK
    add_filter('user_contactmethods', 'new_contactmethod', 10, 2);
    function new_contactmethod($methods, $user)
    {
        unset($methods['aim'], $methods['jabber'], $methods['yim']);
        $methods['vk'] = __('VK', 'wp-addon');
        $methods['ok'] = __('OK', 'wp-addon');
        return $methods;
    }
}

function wptweaker_setting_23()
{
    // Функционал перенесен в класс PerformanceTweaks
}

function wptweaker_setting_24()
{
    /* deregister widgets */
    add_action('widgets_init', 'unregister_basic_widgets');
    function unregister_basic_widgets()
    {
        unregister_widget('WP_Widget_Pages');            // Виджет страниц
        unregister_widget('WP_Widget_Calendar');         // Календарь
        unregister_widget('WP_Widget_Archives');         // Архивы
        unregister_widget('WP_Widget_Links');            // Ссылки
        unregister_widget('WP_Widget_Meta');             // Мета виджет
        unregister_widget('WP_Widget_Search');           // Поиск
        unregister_widget('WP_Widget_Text');             // Текст
        unregister_widget('WP_Widget_Categories');       // Категории
        unregister_widget('WP_Widget_Recent_Posts');     // Последние записи
        unregister_widget('WP_Widget_Recent_Comments');  // Последние комментарии
        unregister_widget('WP_Widget_RSS');              // RSS
        unregister_widget('WP_Widget_Tag_Cloud');        // Облако меток
        unregister_widget('WP_Nav_Menu_Widget');         // Меню
    }
}

function wptweaker_setting_25()
{
    ## Удаление файлов license.txt и readme.html для защиты
    if (is_admin() && ! defined('DOING_AJAX')) {
        $license_file = ABSPATH . '/license.txt';
        $readme_file  = ABSPATH . '/readme.html';

        if (file_exists($license_file) && current_user_can('manage_options')) {
            $deleted = unlink($license_file) && unlink($readme_file);

            if ( ! $deleted) {
                $GLOBALS['readmedel'] = sprintf(__('Failed to delete files license.txt and readme.html from folder %s. Please delete them manually!', 'wp-addon'), ABSPATH);
            } else {
                $GLOBALS['readmedel'] = sprintf(__('Files license.txt and readme.html have been deleted from folder %s.', 'wp-addon'), ABSPATH);
            }

            add_action('admin_notices', function () {
                echo '<div class="error is-dismissible"><p>' . $GLOBALS['readmedel'] . '</p></div>';
            });
        }
    }
}

function wptweaker_setting_26()
{
    ## Фильтр элементо втаксономии для метабокса таксономий в админке.
    ## Позволяет удобно фильтровать (искать) элементы таксономии по назанию, когда их очень много
    add_action('admin_print_scripts', 'my_admin_term_filter', 99);
    function my_admin_term_filter()
    {
        $screen = get_current_screen();

        if ($screen === null || 'post' !== $screen->base) {
            return;
        } // только для страницы редактирвоания любой записи
        ?>
        <script>
            jQuery(document).ready(function ($) {
                var $categoryDivs = $('.categorydiv');
                $categoryDivs.prepend('<input type="search" class="fc-search-field" placeholder="<?=__('filter...', 'wp-addon')?>" style="width:100%" />');
                $categoryDivs.on('keyup search', '.fc-search-field', function (event) {
                    var searchTerm = event.target.value,
                        $listItems = $(this).parent().find('.categorychecklist li');

                    if ($.trim(searchTerm)) {
                        $listItems.hide().filter(function () {
                            return $(this).text().toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1;
                        }).show();
                    } else {
                        $listItems.show();
                    }
                });
            });
        </script>
        <?php
    }
}

function wptweaker_setting_27()
{
    ##  отменим показ выбранного термина наверху в checkbox списке терминов
    add_filter('wp_terms_checklist_args', 'set_checked_ontop_default', 10);
    function set_checked_ontop_default($args)
    {
        // изменим параметр по умолчанию на false
        if ( ! isset($args['checked_ontop'])) {
            $args['checked_ontop'] = false;
        }

        return $args;
    }
}


function wptweaker_setting_28()
{
    add_action( 'admin_menu', 'add_user_menu_bubble' );
    function add_user_menu_bubble(){
        global $menu;
        $count = wp_count_posts()->pending; // на утверждении
        if( $count ){
            foreach( $menu as $key => $value ){
                if( $menu[$key][2] === 'edit.php' ){
                    $menu[$key][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . $count . '</span></span>';
                    break;
                }
            }
        }
    }
}

function wptweaker_setting_29()
{
    // Удаляем уведомление об обновлении WordPress для всех кроме админа
    function disable_notice_for_users() {
        if ( ! current_user_can('manage_options')) {
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag', 10);
        }
    }
    add_action('admin_head', 'disable_notice_for_users');
}

function wptweaker_setting_30()
{
    ## Ссылка «Читать далее...» после цитаты в цикле. Замена `[...]`
    add_filter('excerpt_more', 'replace_excerpt_func', 99);
    function replace_excerpt_func($more)
    {
        global $post;
        return '<a class="read_more" href="' . get_permalink($post) . '">' . __('Read more ...', 'wp-addon') . '</a>';
    }
}

function wptweaker_setting_31()
{
    ## Шорткоды в виджете "Текст"
    if ( ! is_admin()) {
        add_filter('widget_text', 'do_shortcode', 11);
    }
}

function wptweaker_setting_32()
{
    ## Изменяет URL расположения jQuery файла только для фронт-энда
    add_action('rw_enqueue_scripts', 'jquery_enqueue_func');
    function jquery_enqueue_func(){
        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js', false, false);
        wp_enqueue_script('jquery');
    }
}

function wptweaker_setting_33()
{
    remove_filter('the_content', 'shortcode_unautop');
}

function wptweaker_setting_34()
{
    add_filter('mime_types', function ($existing_mimes) {
        $existing_mimes['webp'] = 'image/webp';
        return $existing_mimes;
    }, 10, 1);

    add_filter('file_is_displayable_image', function($result, $path) {
        if ($result === false) {
            $displayable_image_types = array( IMAGETYPE_WEBP );
            $info = @getimagesize( $path );
            if (empty($info)) {
                $result = false;
            } elseif (!in_array($info[2], $displayable_image_types)) {
                $result = false;
            } else {
                $result = true;
            }
        }
        return $result;
    }, 10, 2);
}

function wptweaker_setting_35() {
    # Формирует данные для отображения SVG как изображения в медиабиблиотеке.
    add_filter( 'wp_prepare_attachment_for_js', function ( $response ) {
        if ( $response['mime'] === 'image/svg+xml' ) {
            $response['sizes'] = [
                'medium' => [
                    'url' => $response['url'],
                ],
                'full' => [
                    'url' => $response['url'],
                ],
            ];

            /* С выводом названия файла
            $response['image'] = [
                'src' => $response['url'],
            ]; */
        }
        return $response;
    });
}

function wptweaker_setting_36() {
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if ( $user_agent === '' ) {
		return;
	}
	add_filter( 'pre_site_transient_browser_' . md5( $user_agent ), '__return_null' );
}

function wptweaker_setting_38() {
    // Увеличение времени сессии до 1 года
    add_filter( 'auth_cookie_expiration', 'extend_login_session_year', 10, 3 );
    function extend_login_session_year( $seconds, $user_id, $remember ) {
        return YEAR_IN_SECONDS;
    }
}