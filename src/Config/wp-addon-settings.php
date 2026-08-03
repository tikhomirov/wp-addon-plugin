<?php

namespace WpAddon;

use Automatic_Upgrader_Skin;
use Plugin_Upgrader;
use WpAddon\Services\MediaCleanupService;

defined('ABSPATH') or exit;

class WP_Addon_Settings
{
    private static $instance;

    public $file;

    public $path;

    public $url;

    public $ver;

    public $wp_plugin_name;

    public $wp_plugin_slug;

    private function __construct()
    {
        $this->file = RW_FILE;
        $this->path = RW_PLUGIN_DIR;
        $this->url = RW_PLUGIN_URL;

        // Get version from plugin header dynamically
        $plugin_data = get_file_data($this->file, ['Version' => 'Version']);
        $this->ver = $plugin_data['Version'] ?? '1.0.0';

        // Use timestamp for version in debug mode to avoid caching issues
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->ver = time();
        }
    }

    public static function getInstance(): WP_Addon_Settings
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function __clone() {}

    public function __wakeup() {}

    private function get_github_plugins()
    {
        $cache_key = 'wp_addon_github_plugins';
        $cached_plugins = get_transient($cache_key);
        if ($cached_plugins !== false) {
            return $cached_plugins;
        }

        $token = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : null;
        $headers = $token ? ['Authorization' => 'token '.$token] : [];

        $response = wp_remote_get('https://api.github.com/users/rwsite/repos?page=1&per_page=100', ['headers' => $headers]);
        if (is_wp_error($response)) {
            return [];
        }
        $repos = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($repos)) {
            return [];
        }
        $plugins = [];
        foreach ($repos as $repo) {
            $name = $repo['name'] ?? '';
            // Исключаем текущий плагин
            if ($name === 'wp-addon-plugin') {
                continue;
            }
            // Фильтр по формату wp-{name}-plugin
            if (! preg_match('/^wp-.*-plugin$/', $name)) {
                continue;
            }
            // Проверяем наличие тегов (стабильных версий)
            $tags_response = wp_remote_get('https://api.github.com/repos/rwsite/'.$name.'/tags?per_page=1', ['headers' => $headers]);
            if (is_wp_error($tags_response)) {
                continue;
            }
            $tags = json_decode(wp_remote_retrieve_body($tags_response), true);
            if (! is_array($tags) || empty($tags)) {
                continue;
            }
            // Проверяем, что это WordPress плагин (наличие plugin.php или readme.txt)
            $contents_response = wp_remote_get('https://api.github.com/repos/rwsite/'.$name.'/contents?ref='.($repo['default_branch'] ?? 'main'), ['headers' => $headers]);
            if (is_wp_error($contents_response)) {
                continue;
            }
            $contents = json_decode(wp_remote_retrieve_body($contents_response), true);
            if (! is_array($contents)) {
                continue;
            }
            $has_plugin_file = false;
            foreach ($contents as $file) {
                if (! is_array($file) || ! isset($file['name'])) {
                    continue;
                }
                if ($file['name'] === $name.'.php' || $file['name'] === 'plugin.php' || $file['name'] === 'readme.txt') {
                    $has_plugin_file = true;
                    break;
                }
            }
            if (! $has_plugin_file) {
                continue;
            }
            $plugins[] = [
                'name' => $name,
                'description' => $repo['description'] ?? '',
                'html_url' => $repo['html_url'] ?? '',
                'zip_url' => 'https://github.com/rwsite/'.$name.'/archive/'.($repo['default_branch'] ?? 'main').'.zip',
            ];
        }

        // Кешируем на сутки
        set_transient($cache_key, $plugins, DAY_IN_SECONDS);

        return $plugins;
    }

    public function get_plugins_html()
    {
        $plugins = $this->get_github_plugins();
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $html = '<div class="my-plugins-list" style="max-width: 800px;">';
        $html .= '<button id="refresh-plugins-list" class="button">Обновить список</button><br><br>';
        if (empty($plugins)) {
            $html .= '<p>Не удалось загрузить список плагинов.</p>';
        } else {
            foreach ($plugins as $plugin) {
                $is_installed = false;
                $plugin_file = null;
                $is_active = false;
                foreach ($installed_plugins as $file => $data) {
                    if (dirname($file) === $plugin['name']) {
                        $is_installed = true;
                        $plugin_file = $file;
                        $is_active = in_array($file, $active_plugins);
                        break;
                    }
                }
                $class = $is_installed ? ($is_active ? 'plugin-item installed active' : 'plugin-item installed inactive') : 'plugin-item not-installed';
                $style = $is_installed ? ($is_active ? 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px; background-color: #d4edda;' : 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px; background-color: #fff3cd;') : 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px; background-color: #f8f9fa;';
                $button_text = $is_installed ? 'Деинсталлировать' : 'Установить';
                $button_class = $is_installed ? 'uninstall-plugin-btn button button-secondary' : 'install-plugin-btn button button-primary';
                $html .= '<div class="'.esc_attr($class).'">';
                $html .= '<h4 style="margin: 0 0 5px 0;">'.esc_html($plugin['name']).'</h4>';
                $html .= '<p style="margin: 5px 0; list-style: none;">'.esc_html($plugin['description']).'</p>';
                $html .= '<a href="'.esc_url($plugin['html_url']).'" target="_blank" style="margin-right: 10px;">Посмотреть на GitHub</a>';
                if ($is_installed) {
                    $activate_text = $is_active ? 'Деактивировать' : 'Активировать';
                    $activate_class = $is_active ? 'deactivate-plugin-btn button button-warning' : 'activate-plugin-btn button button-success';
                    $html .= '<button class="'.esc_attr($activate_class).'" data-repo="'.esc_attr($plugin['name']).'" data-file="'.esc_attr($plugin_file).'">'.esc_html($activate_text).'</button> ';
                    $html .= '<button class="'.esc_attr($button_class).'" data-repo="'.esc_attr($plugin['name']).'" data-zip="'.esc_attr($plugin['zip_url']).'">'.esc_html($button_text).'</button>';
                } else {
                    $html .= '<button class="'.esc_attr($button_class).'" data-repo="'.esc_attr($plugin['name']).'" data-zip="'.esc_attr($plugin['zip_url']).'">'.esc_html($button_text).'</button>';
                }
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        $html .= '<style>.installed p:before { content: none !important; }</style>';
        $html .= '<style>
            .plugin-item {
                border: 1px solid #ddd;
                padding: 10px;
                margin-bottom: 10px;
                border-radius: 5px;
            }
            .plugin-item.installed.active {
                background-color: #d4edda;
            }
            .plugin-item.installed.inactive {
                background-color: #fff3cd;
            }
            .plugin-item.not-installed {
                background-color: #f8f9fa;
            }
        </style>';
        $html .= '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on("click", ".install-plugin-btn", function() {
                var btn = $(this);
                var originalText = btn.text();
                btn.text("Установка...").prop("disabled", true);
                $.post(ajaxurl, {
                    action: "install_my_plugin",
                    repo: btn.data("repo"),
                    zip: btn.data("zip"),
                    nonce: "'.wp_create_nonce('install_plugin').'"
                }, function(response) {
                    if (response.success) {
                        btn.text("Установлен и активирован").removeClass("button").addClass("button-disabled");
                        location.reload();
                    } else {
                        btn.text("Ошибка установки").prop("disabled", false);
                        console.log(response.data);
                    }
                }).fail(function() {
                    btn.text("Ошибка").prop("disabled", false);
                });
            });
            $(document).on("click", ".deactivate-plugin-btn, .activate-plugin-btn", function() {
                var btn = $(this);
                var originalText = btn.text();
                btn.text("Обработка...").prop("disabled", true);
                $.post(ajaxurl, {
                    action: "toggle_my_plugin",
                    plugin_file: btn.data("file"),
                    nonce: "'.wp_create_nonce('toggle_plugin').'"
                }, function(response) {
                    if (response.success) {
                        if (response.data.action == "deactivated") {
                            btn.removeClass("deactivate-plugin-btn button button-warning").addClass("activate-plugin-btn button button-success").text("Активировать");
                        } else {
                            btn.removeClass("activate-plugin-btn button button-success").addClass("deactivate-plugin-btn button button-warning").text("Деактивировать");
                        }
                        location.reload();
                    } else {
                        btn.text(originalText).prop("disabled", false);
                        console.log(response.data);
                    }
                }).fail(function() {
                    btn.text(originalText).prop("disabled", false);
                });
            });
            $(document).on("click", ".uninstall-plugin-btn", function() {
                var btn = $(this);
                var originalText = btn.text();
                if (!confirm("Вы уверены, что хотите деинсталлировать этот плагин?")) {
                    return;
                }
                btn.text("Деинсталляция...").prop("disabled", true);
                $.post(ajaxurl, {
                    action: "uninstall_my_plugin",
                    repo: btn.data("repo"),
                    nonce: "'.wp_create_nonce('uninstall_plugin').'"
                }, function(response) {
                    if (response.success) {
                        var pluginItem = btn.closest(".plugin-item");
                        pluginItem.removeClass("installed active inactive").addClass("not-installed").css("background-color", "#f8f9fa");
                        pluginItem.find(".activate-plugin-btn, .deactivate-plugin-btn").remove();
                        btn.removeClass("uninstall-plugin-btn button button-secondary").addClass("install-plugin-btn button button-primary").text("Установить").prop("disabled", false);
                    } else {
                        btn.text("Ошибка деинсталляции").prop("disabled", false);
                        console.log(response.data);
                    }
                }).fail(function() {
                    btn.text("Ошибка").prop("disabled", false);
                });
            });
            $("#refresh-plugins-list").click(function() {
                var btn = $(this);
                btn.text("Обновление...").prop("disabled", true);
                $.post(ajaxurl, {
                    action: "refresh_plugins_list",
                    nonce: "'.wp_create_nonce('refresh_plugins').'"
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        btn.text("Ошибка").prop("disabled", false);
                        alert("Ошибка обновления: " + response.data);
                    }
                });
            });
        });
        </script>';

        return $html;
    }

    public function refresh_plugins_list_ajax()
    {
        check_ajax_referer('refresh_plugins', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Нет прав.');
        }
        delete_transient('wp_addon_github_plugins');
        wp_send_json_success();
    }

    public function uninstall_plugin_ajax()
    {
        check_ajax_referer('uninstall_plugin', 'nonce');
        if (! current_user_can('delete_plugins')) {
            wp_send_json_error('Нет прав для удаления плагинов.');
        }
        $repo = sanitize_text_field($_POST['repo']);
        if (empty($repo)) {
            wp_send_json_error('Неверные параметры.');
        }
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        if (! WP_Filesystem()) {
            wp_send_json_error('Ошибка файловой системы.');

            return;
        }
        global $wp_filesystem;
        $installed_plugins = get_plugins();
        $plugin_file = null;
        foreach ($installed_plugins as $file => $data) {
            if (dirname($file) === $repo) {
                $plugin_file = $file;
                break;
            }
        }
        if (! $plugin_file) {
            wp_send_json_error('Плагин не найден.');

            return;
        }
        $plugin_dir = WP_PLUGIN_DIR.'/'.$repo;
        if ($wp_filesystem->exists($plugin_dir)) {
            $wp_filesystem->delete($plugin_dir, true);
        }
        wp_send_json_success();
    }

    public function toggle_plugin_ajax()
    {
        check_ajax_referer('toggle_plugin', 'nonce');
        if (! current_user_can('activate_plugins')) {
            wp_send_json_error('Нет прав для активации/деактивации плагинов.');
        }
        $plugin_file = sanitize_text_field($_POST['plugin_file']);
        if (empty($plugin_file)) {
            wp_send_json_error('Неверные параметры.');
        }
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        $active_plugins = get_option('active_plugins', []);
        if (in_array($plugin_file, $active_plugins)) {
            // Деактивировать
            deactivate_plugins($plugin_file);
            wp_send_json_success(['action' => 'deactivated']);
        } else {
            // Активировать
            $activate_result = activate_plugin($plugin_file);
            if ($activate_result === true || is_null($activate_result)) {
                wp_send_json_success(['action' => 'activated']);
            } else {
                wp_send_json_error('Не удалось активировать плагин: '.(is_wp_error($activate_result) ? $activate_result->get_error_message() : 'Неизвестная ошибка'));
            }
        }
    }

    public function install_plugin_ajax()
    {
        check_ajax_referer('install_plugin', 'nonce');
        if (! current_user_can('install_plugins')) {
            wp_send_json_error('Нет прав для установки плагинов.');
        }
        $zip_url = esc_url_raw($_POST['zip']);
        $repo = sanitize_text_field($_POST['repo']);
        if (empty($zip_url) || empty($repo)) {
            wp_send_json_error('Неверные параметры.');
        }
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH.'wp-admin/includes/plugin-install.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        if (! WP_Filesystem()) {
            wp_send_json_error('Ошибка файловой системы.');

            return;
        }
        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin);
        $result = $upgrader->install($zip_url);
        if ($result) {
            // Найти установленную папку (GitHub ZIP создает папку с -main)
            $possible_dirs = glob(WP_PLUGIN_DIR.'/'.$repo.'-*');
            if (empty($possible_dirs)) {
                wp_send_json_error('Не найдена установленная папка.');

                return;
            }
            $installed_dir = $possible_dirs[0]; // берем первую
            $expected_dir = WP_PLUGIN_DIR.'/'.$repo;
            if (is_dir($expected_dir)) {
                wp_send_json_error('Папка плагина уже существует.');

                return;
            }
            if (rename($installed_dir, $expected_dir)) {
                // Найти основной файл плагина
                $plugin_files = glob($expected_dir.'/*.php');
                $plugin_file = null;
                foreach ($plugin_files as $file) {
                    $data = get_plugin_data($file);
                    if (! empty($data['Name'])) {
                        $plugin_file = $file;
                        break;
                    }
                }
                if ($plugin_file) {
                    $activate_result = activate_plugin($plugin_file);
                    if ($activate_result === true || is_null($activate_result)) {
                        wp_send_json_success();
                    } else {
                        wp_send_json_error('Плагин установлен, но не удалось активировать: '.(is_wp_error($activate_result) ? $activate_result->get_error_message() : 'Неизвестная ошибка'));
                    }
                } else {
                    wp_send_json_error('Не найден файл плагина для активации.');
                }
            } else {
                wp_send_json_error('Не удалось переименовать папку плагина.');
            }
        }
    }

    public function add_actions()
    {
        add_action('after_setup_theme', [$this, 'after_setup_theme']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets'], 20);
        add_action('wp_ajax_install_my_plugin', [$this, 'install_plugin_ajax']);
        add_action('wp_ajax_refresh_plugins_list', [$this, 'refresh_plugins_list_ajax']);
        add_action('wp_ajax_uninstall_my_plugin', [$this, 'uninstall_plugin_ajax']);
        add_action('wp_ajax_toggle_my_plugin', [$this, 'toggle_plugin_ajax']);
    }

    /**
     * style and scripts in wp-admin
     */
    public function admin_assets($page)
    {
        if (strpos($page, $this->wp_plugin_slug) === false) {
            return;
        }

        wp_enqueue_style($this->wp_plugin_slug,
            RW_PLUGIN_URL.'assets/css/min/admin.min.css',
            false,
            $this->ver,
            'all');
    }

    /**
     * @see  http://codestarframework.com/documentation/#/fields?id=checkbox
     */
    public function after_setup_theme()
    {

        // Check core class for avoid errors
        if (! class_exists('CSF')) {
            return;
        }

        $this->wp_plugin_name = __('Wordpress Addon', 'wp-addon');
        $this->wp_plugin_slug = 'wp-addon';

        // Set a unique slug-like ID
        $prefix = $this->wp_plugin_slug;

        // Create options
        \CSF::createOptions($prefix, require_once __DIR__.'/_options.php');

        // General Settings
        \CSF::createSection($prefix, [
            'title' => __('General Settings', 'wp-addon'),
            'icon' => 'fa fa-rocket',
            'fields' => require_once __DIR__.'/main.php',
        ]);

        // Tweaks
        \CSF::createSection($prefix, [
            'title' => __('Tweaks', 'wp-addon'),
            'icon' => 'fa fa-wordpress',
            'fields' => require_once __DIR__.'/tweaks.php',
        ]);

        // Cache
        \CSF::createSection($prefix, [
            'title' => __('Cache', 'wp-addon'),
            'icon' => 'fa fa-database',
            'description' => __('Page caching saves ready HTML pages to a file. When a user visits the site, instead of executing PHP code and database queries, they are shown the saved page immediately. This speeds up site loading by 5-10 times.<br><br><strong>When to use:</strong> On a finished site with high traffic. <strong>When to disable:</strong> During development or if content changes frequently.<br><br><strong>Important:</strong> Cached pages are stored in the wp-content/cache/pages/ folder as .gz files.', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'cache_enabled',
                    'type' => 'switcher',
                    'title' => __('Enable page caching', 'wp-addon'),
                    'desc' => __('Main switch for enabling/disabling cache. When ON: all site pages are saved to cache. When OFF: cache is not used, pages are generated each time anew.', 'wp-addon'),
                    'default' => true,
                ],
                [
                    'id' => 'cache_ttl',
                    'type' => 'number',
                    'title' => __('Cache lifetime (seconds)', 'wp-addon'),
                    'desc' => __('How many seconds to store the cached page. After this time, the page will be recreated. For a news site - 1800 sec (30 min). For static - 3600 sec (1 hour).', 'wp-addon'),
                    'default' => 3600,
                    'min' => 300,
                    'max' => 86400,
                ],
                [
                    'id' => 'cache_exclude_logged_in',
                    'type' => 'switcher',
                    'title' => __('Do not cache for logged-in users', 'wp-addon'),
                    'desc' => __('If a user is logged into admin or personal account - show them fresh pages without cache. Otherwise, they may not see their changes or notifications.', 'wp-addon'),
                    'default' => true,
                ],
                [
                    'id' => 'cache_exclude_urls',
                    'type' => 'textarea',
                    'title' => __('Do not cache these pages', 'wp-addon'),
                    'desc' => __('Pages that change frequently and should not be cached. One line - one URL. Examples: /wp-admin/ (admin), /checkout/ (checkout), /cart/ (cart), /my-account/ (personal account).', 'wp-addon'),
                    'default' => "/wp-admin/\n/wp-login.php\n/checkout/\n/cart/",
                ],
                [
                    'id' => 'cache_preload_pages',
                    'type' => 'textarea',
                    'title' => __('Preload these pages', 'wp-addon'),
                    'desc' => __('Pages for auto-caching every hour. <strong>Leave empty for automatic mode:</strong> the home page + all pages from the main site menu (up to 10 pcs) will be loaded. Or specify manually: one URL per line, for example /about/, /services/', 'wp-addon'),
                    'default' => '',
                ],
                [
                    'id' => 'cache_clear_on_post_save',
                    'type' => 'switcher',
                    'title' => __('Clear cache on post publish', 'wp-addon'),
                    'desc' => __('When you publish a new article or edit an old one - automatically delete all cache. So readers will immediately see fresh content. Disable if you publish often - this will slow down the site.', 'wp-addon'),
                    'default' => true,
                ],
            ],
        ]);

        // Asset Minification
        \CSF::createSection($prefix, [
            'title' => __('Asset Minification', 'wp-addon'),
            'icon' => 'fa fa-compress',
            'description' => __('Asset optimization is a comprehensive system for improving site performance by minifying and combining CSS/JavaScript files. The module automatically analyzes all connected resources and applies optimal optimization strategies.<br><br><strong>Benefits:</strong><br>• Reduce file size by 20-40%<br>• Decrease number of HTTP requests<br>• Speed up page loading<br>• Better PageSpeed Insights scores<br><br><strong>Automatic logic:</strong><br>• Excludes WordPress system resources<br>• Does not process files smaller than 1KB<br>• Skips already minified files<br>• Analyzes resource loading priorities', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'asset_minification_enabled',
                    'type' => 'switcher',
                    'title' => __('Enable asset optimization', 'wp-addon'),
                    'desc' => __('Main switch of the optimization module. When enabled, intelligent processing of all CSS and JavaScript resources on the site is activated. Recommended to enable on production sites for maximum performance.', 'wp-addon'),
                    'default' => true,
                ],
                [
                    'id' => 'asset_minify_css',
                    'type' => 'switcher',
                    'title' => __('Minify CSS files', 'wp-addon'),
                    'desc' => __('Removes from CSS files: comments, extra spaces, line breaks and tabs. Does not process files that are already minified or smaller than 1KB. Traffic savings: 15-30% per file.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_minify_js',
                    'type' => 'switcher',
                    'title' => __('Minify JavaScript files', 'wp-addon'),
                    'desc' => __('Compresses JS code by removing comments, extra spaces and formatting. Skips minified files and files smaller than 1KB. Important: check functionality after enabling, as some plugins may have minification-sensitive code.', 'wp-addon'),
                    'default' => false,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_combine_css',
                    'type' => 'switcher',
                    'title' => __('Combine CSS files', 'wp-addon'),
                    'desc' => __('Collects all suitable CSS files into one combined file, reducing the number of HTTP requests to the server. Automatically excludes WordPress system styles. Effective for sites with 3+ CSS files.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_combine_js',
                    'type' => 'switcher',
                    'title' => __('Combine JavaScript files', 'wp-addon'),
                    'desc' => __('Combines JS files into one loaded in the footer. Reduces the number of requests, but may break loading order. Recommended to test for JavaScript errors after enabling.', 'wp-addon'),
                    'default' => false,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_critical_css_enabled',
                    'type' => 'switcher',
                    'title' => __('Implement critical CSS', 'wp-addon'),
                    'desc' => __('Automatically extracts and embeds inline critical CSS styles (header, menu, main content) for instant display of above-the-fold content. Improves First Contentful Paint score in Lighthouse.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_defer_non_critical_css',
                    'type' => 'switcher',
                    'title' => __('Defer non-critical CSS', 'wp-addon'),
                    'desc' => __('Loads non-critical CSS files asynchronously after page rendering. Prevents render blocking, but may cause brief "flash of unstyled content" (FOUC).', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_exclude_css',
                    'type' => 'textarea',
                    'title' => __('Exclude CSS files', 'wp-addon'),
                    'desc' => __('List of CSS file handles separated by comma that should not be optimized. Examples: critical-styles, admin-css, custom-admin-styles. WordPress system files are excluded automatically.', 'wp-addon'),
                    'default' => 'admin-bar,dashicons',
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
                [
                    'id' => 'asset_exclude_js',
                    'type' => 'textarea',
                    'title' => __('Exclude JavaScript files', 'wp-addon'),
                    'desc' => __('JS file handles separated by comma for exclusion from optimization. Examples: google-analytics, facebook-pixel, custom-scripts. WordPress system scripts (jQuery, etc.) are excluded automatically.', 'wp-addon'),
                    'default' => 'jquery,jquery-core',
                    'dependency' => ['asset_minification_enabled', '==', 'true'],
                ],
            ],
        ]);

        // Lazy Loading
        \CSF::createSection($prefix, [
            'title' => __('Lazy Loading', 'wp-addon'),
            'icon' => 'fa fa-eye',
            'description' => __('Lazy loading of images and media files is a performance optimization technique where resources are loaded only when they come into the user\'s view. The module uses the modern Intersection Observer API with fallback for older browsers.<br><br><strong>Benefits:</strong><br>• Reduced page load time<br>• Traffic savings (especially on mobile devices)<br>• Improved Core Web Vitals (LCP, CLS)<br>• Automatic image compression with blur placeholder<br><br><strong>Support:</strong><br>• Images (img)<br>• Iframe (YouTube, Vimeo videos)<br>• Video elements<br>• Blur placeholder for smooth loading<br>• Fallback for IE11+', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'enable_lazy_loading',
                    'type' => 'switcher',
                    'title' => __('Enable lazy loading', 'wp-addon'),
                    'desc' => __('Main switch of the module. When enabled, lazy loading is activated for selected media types. Recommended to enable on all sites for improved performance.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'lazy_types',
                    'type' => 'checkbox',
                    'title' => __('Media types for lazy loading', 'wp-addon'),
                    'desc' => __('Select element types for which lazy loading will be applied. Images are most effective for optimization.', 'wp-addon'),
                    'options' => [
                        'img' => __('Images (img)', 'wp-addon'),
                        'iframe' => __('Iframe (YouTube, Vimeo)', 'wp-addon'),
                        'video' => __('Video elements', 'wp-addon'),
                    ],
                    'default' => ['img', 'iframe', 'video'],
                    'dependency' => ['enable_lazy_loading', '==', 'true'],
                ],
                [
                    'id' => 'blur_intensity',
                    'type' => 'number',
                    'title' => __('Blur effect intensity', 'wp-addon'),
                    'desc' => __('Degree of blur placeholder blur. Value 1 - weak blur, 10 - strong. Recommended 3-7 for optimal quality and performance balance.', 'wp-addon'),
                    'default' => 5,
                    'min' => 1,
                    'max' => 10,
                    'dependency' => ['enable_lazy_loading', '==', 'true'],
                ],
                [
                    'id' => 'root_margin',
                    'type' => 'text',
                    'title' => __('Viewport margin (rootMargin)', 'wp-addon'),
                    'desc' => __('Distance from viewport edge at which to start loading. Example: 50px - loading 50px before element appears. 10% - 10% of viewport height.', 'wp-addon'),
                    'default' => '50px',
                    'attributes' => [
                        'placeholder' => '50px',
                    ],
                    'dependency' => ['enable_lazy_loading', '==', 'true'],
                ],
                [
                    'id' => 'threshold',
                    'type' => 'number',
                    'title' => __('Visibility threshold', 'wp-addon'),
                    'desc' => __('The portion of the element that must enter the viewport to start loading. 0.1 = 10% of element visible. 1.0 = entire element visible.', 'wp-addon'),
                    'default' => 0.1,
                    'min' => 0,
                    'max' => 1,
                    'step' => 0.1,
                    'dependency' => ['enable_lazy_loading', '==', 'true'],
                ],
                [
                    'id' => 'enable_fallback',
                    'type' => 'switcher',
                    'title' => __('Enable fallback for older browsers', 'wp-addon'),
                    'desc' => __('Use scroll event listeners instead of Intersection Observer in browsers without IO API support. Slows performance but ensures compatibility.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['enable_lazy_loading', '==', 'true'],
                ],
            ],
        ]);

        // Media Cleanup
        \CSF::createSection($prefix, [
            'title' => __('Media Cleanup', 'wp-addon'),
            'icon' => 'fa fa-image',
            'description' => __('This section allows you to clean up unused image sizes to free up disk space. WordPress generates multiple sizes for each uploaded image, but if your theme or plugins don\'t use all of them, they take up unnecessary space. Use this tool to identify and remove such files.<br><br><strong>When to use:</strong> After changing themes, disabling plugins that generate custom sizes, or optimizing site performance.<br><br><strong>Precautions:</strong> Always create a backup before cleanup. Use "Preview Cleanup" first to see what will be deleted. The tool preserves original images and "scaled" versions (up to 2000px). Deleted files cannot be recovered!', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'media_cleanup_enabled',
                    'type' => 'switcher',
                    'title' => __('Enable Media Cleanup', 'wp-addon'),
                    'desc' => __('Registers the cleanup AJAX actions. Keep disabled until you are ready to review and remove generated image sizes.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'cleanup_images',
                    'type' => 'content',
                    'title' => __('Clean up unused image sizes', 'wp-addon'),
                    'dependency' => ['media_cleanup_enabled', '==', 'true'],
                    'content' => '<p>'.sprintf(__('This will delete all image sizes except: %s. Files will be deleted permanently!', 'wp-addon'), implode(', ', MediaCleanupService::getRegisteredSizesStatic())).'</p><button id="preview-cleanup-btn" class="button">'.__('Preview Cleanup', 'wp-addon').'</button> <button id="cleanup-images-btn" class="button button-primary">'.__('Start Cleanup', 'wp-addon').'</button><div id="cleanup-result"></div><script>jQuery(document).ready(function($){$("#preview-cleanup-btn").click(function(e){e.preventDefault();$("#cleanup-result").html("'.__('Loading preview...', 'wp-addon').'");$.post(ajaxurl,{action:"wp_addon_cleanup_images_dry_run",nonce:"'.wp_create_nonce('cleanup_images').'"},function(r){$("#cleanup-result").html(r);});});$("#cleanup-images-btn").click(function(e){e.preventDefault();if(confirm("'.__('Are you sure? This action cannot be undone.', 'wp-addon').'")){$("#cleanup-result").html("'.__('Processing...', 'wp-addon').'");$.post(ajaxurl,{action:"wp_addon_cleanup_images",nonce:"'.wp_create_nonce('cleanup_images').'"},function(r){$("#cleanup-result").html(r);});}});});</script>',
                ],
            ],
        ]);

        // Redirects
        \CSF::createSection($prefix, [
            'title' => __('Redirects', 'wp-addon'),
            'icon' => 'fa fa-share',
            'description' => __('301 redirect management. Create redirect rules from one URL to another. Supports both simple redirection and wildcard (*) usage for folder redirection.<br><br><strong>Simple redirects:</strong> /old-page/ → /new-page/<br><strong>Wildcard redirects:</strong> /old-folder/* → /new-folder/*<br><br><strong>Important:</strong> Redirects apply to all requests except wp-admin and wp-login to prevent admin access blocking.', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'redirect_enable',
                    'type' => 'switcher',
                    'title' => __('Enable redirects', 'wp-addon'),
                    'desc' => __('When disabled, redirect rules are not registered or processed.', 'wp-addon'),
                    'default' => true,
                ],
                [
                    'id' => 'redirects_wildcard',
                    'type' => 'switcher',
                    'title' => __('Use wildcard redirects', 'wp-addon'),
                    'desc' => __('Enable for * symbol support in URLs. Example: /old-folder/* will redirect all pages from old-folder to corresponding pages in new-folder.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'redirects_rules',
                    'type' => 'repeater',
                    'title' => __('Redirect rules', 'wp-addon'),
                    'desc' => __('Add redirection rules. Request - source URL (relative to site root), Destination - target URL.', 'wp-addon'),
                    'fields' => [
                        [
                            'id' => 'request',
                            'type' => 'text',
                            'title' => __('Request URL', 'wp-addon'),
                            'desc' => __('Source URL for redirection. Example: /old-page/ or /old-folder/*', 'wp-addon'),
                            'attributes' => [
                                'placeholder' => '/old-page/',
                            ],
                        ],
                        [
                            'id' => 'destination',
                            'type' => 'text',
                            'title' => __('Destination URL', 'wp-addon'),
                            'desc' => __('Target URL. Can be relative (/new-page/) or absolute (https://example.com/new-page/)', 'wp-addon'),
                            'attributes' => [
                                'placeholder' => '/new-page/',
                            ],
                        ],
                    ],
                    'default' => [],
                ],
            ],
        ]);

        // Shortcodes and Widgets
        \CSF::createSection($prefix, [
            'title' => __('Shortcodes and Widgets', 'wp-addon'),
            'icon' => 'fa fa-bolt',
            'fields' => require __DIR__.'/wp-widgets.php',
        ]);

        do_action('wp_addon_settings_section', $prefix);

        // Custom Code
        \CSF::createSection($prefix, [
            'title' => __('Custom code', 'wp-addon'),
            'icon' => 'fa fa-code',
            'fields' => [
                [
                    'id' => 'rw_header_css',
                    'type' => 'code_editor',
                    'title' => __('CSS Code in Header', 'wp-addon'),
                    'settings' => [
                        'theme' => 'mbo',
                        'mode' => 'css',
                    ],
                    'sanitize' => false,
                ],
                [
                    'id' => 'rw_header_html',
                    'type' => 'code_editor',
                    'title' => __('Any HTML code or Analytics code in header.',
                        'wp-addon'),
                    'settings' => [
                        'theme' => 'monokai',
                        'mode' => 'htmlmixed',
                    ],
                    'default' => '',
                    'sanitize' => false,
                ],
                [
                    'id' => 'rw_footer_html',
                    'type' => 'code_editor',
                    'title' => __('Any HTML code in footer.', 'wp-addon'),
                    'settings' => [
                        'theme' => 'monokai',
                        // 'mode'  => 'php',
                    ],
                    'default' => '',
                    'sanitize' => false,
                ],
            ], // #fields
        ]);

        // Markdown Editor
        \CSF::createSection($prefix, [
            'title' => __('Markdown Editor', 'wp-addon'),
            'icon' => 'fa fa-edit',
            'description' => __('Markdown Editor позволяет писать статьи в удобном формате Markdown и автоматически конвертировать их в HTML. Поддерживает синтаксис заголовков, списков, ссылок, изображений, кода и других элементов.<br><br><strong>Преимущества:</strong><br>• Простой и читаемый синтаксис<br>• Быстрое форматирование текста<br>• Предпросмотр в реальном времени<br>• Горячие клавиши для ускорения работы<br>• Автоматическое преобразование в HTML<br><br><strong>Поддерживаемые элементы:</strong><br>• Заголовки (# ## ###)<br>• Жирный (**текст**) и курсив (*текст*)<br>• Ссылки [текст](url)<br>• Изображения ![alt](url)<br>• Списки маркированные и нумерованные<br>• Код `inline` и блоки кода<br>• Цитаты и горизонтальные линии', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'wp_addon_markdown_enabled',
                    'type' => 'switcher',
                    'title' => __('Enable Markdown Editor', 'wp-addon'),
                    'desc' => __('Включает Markdown редактор для постов и страниц. При включении в админ-панели появится дополнительный блок для редактирования контента в формате Markdown с предпросмотром.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'markdown_post_types',
                    'type' => 'checkbox',
                    'title' => __('Post types for Markdown', 'wp-addon'),
                    'desc' => __('Выберите типы постов, для которых будет доступен Markdown редактор.', 'wp-addon'),
                    'options' => [
                        'post' => __('Posts', 'wp-addon'),
                        'page' => __('Pages', 'wp-addon'),
                    ],
                    'default' => ['post', 'page'],
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_replace_tinymce',
                    'type' => 'switcher',
                    'title' => __('Replace TinyMCE editor', 'wp-addon'),
                    'desc' => __('Заменить стандартный редактор TinyMCE на Markdown редактор по умолчанию. При отключении оба редактора будут доступны.', 'wp-addon'),
                    'default' => false,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_enable_preview',
                    'type' => 'switcher',
                    'title' => __('Enable live preview', 'wp-addon'),
                    'desc' => __('Включает предпросмотр Markdown в реальном времени. Показывает как будет выглядеть контент после публикации.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_enable_shortcuts',
                    'type' => 'switcher',
                    'title' => __('Enable keyboard shortcuts', 'wp-addon'),
                    'desc' => __('Активирует горячие клавиши для быстрого форматирования: Ctrl+B (жирный), Ctrl+I (курсив), Ctrl+K (ссылка), Tab (отступ).', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_migrate_existing',
                    'type' => 'switcher',
                    'title' => __('Convert existing HTML to Markdown', 'wp-addon'),
                    'desc' => __('Автоматически конвертирует существующий HTML контент в Markdown при первом редактировании поста. Необратимая операция.', 'wp-addon'),
                    'default' => false,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'type' => 'content',
                    'content' => '<div style="background: #f0f6fc; border: 1px solid #d0d7de; border-radius: 6px; padding: 16px; margin: 16px 0;">
                        <h4 style="margin-top: 0; color: #1d2327;">📝 Справка по Markdown синтаксису:</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-family: monospace; font-size: 13px;">
                            <div>
                                <strong>Заголовки:</strong><br>
                                # Заголовок 1<br>
                                ## Заголовок 2<br>
                                ### Заголовок 3<br><br>
                                <strong>Форматирование:</strong><br>
                                **жирный текст**<br>
                                *курсив*<br>
                                ~~зачеркнутый~~<br>
                                `код`
                            </div>
                            <div>
                                <strong>Ссылки и изображения:</strong><br>
                                [текст ссылки](https://example.com)<br>
                                ![описание](image.jpg)<br><br>
                                <strong>Списки:</strong><br>
                                * Маркированный список<br>
                                1. Нумерованный список<br><br>
                                <strong>Цитаты:</strong><br>
                                > Цитата
                            </div>
                        </div>
                    </div>',
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
            ],
        ]);

        // BackUp
        \CSF::createSection($prefix, [
            'title' => __('Backup Settings', 'wp-addon'),
            'icon' => 'fa fa-server',
            'fields' => [
                [
                    'title' => __('Download settings now', 'wp-addon'),
                    'desc' => __('You can get or set settings from backup'),
                    'type' => 'backup',
                ],
            ],
        ]);

        // My Plugins
        \CSF::createSection($prefix, [
            'title' => __('Мои плагины', 'wp-addon'),
            'icon' => 'fa fa-plug',
            'fields' => [
                [
                    'type' => 'content',
                    'content' => $this->get_plugins_html(),
                ],
            ],
        ]);
    }
}
