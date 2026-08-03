<?php

// === PATCHWORK PREVENTION ===
// Complete prevention of Patchwork loading and conflicts

// Mock Patchwork namespace and classes BEFORE autoloader
if (! class_exists('Patchwork')) {
    class Patchwork
    {
        public static function redefine() {}

        public static function restoreAll() {}

        public static function disable() {}

        public static function enable() {}
    }
}

// Mock Patchwork\redefine function
if (! function_exists('redefine')) {
    function redefine() {}
}

// Mock Patchwork\restoreAll function
if (! function_exists('restoreAll')) {
    function restoreAll() {}
}

// Mock Patchwork\replace function
if (! function_exists('replace')) {
    function replace() {}
}

// Mock Patchwork\relay function
if (! function_exists('relay')) {
    function relay() {}
}

// Mock Patchwork\redefineMethod function
if (! function_exists('redefineMethod')) {
    function redefineMethod() {}
}

// Mock Brain Monkey functions
if (! function_exists('Brain\Monkey\setUp')) {
    eval('namespace Brain\Monkey; function setUp() {} function tearDown() {}');
}
if (! function_exists('Brain\Monkey\tearDown')) {
    eval('namespace Brain\Monkey; function tearDown() {}');
}

// Prevent any Patchwork autoloading
spl_autoload_register(function ($class) {
    if (strpos($class, 'Patchwork') === 0 || strpos($class, 'Brain\\Monkey') === 0) {
        // Return a mock class for any Patchwork or Brain Monkey class
        eval("class {$class} { public static function __callStatic(\$method, \$args) { return \$args ? \$args[0] : null; } public function __call(\$method, \$args) { return \$args ? \$args[0] : null; } }");

        return true;
    }

    return false;
}, true, true);

// Mock specific Patchwork\Redefinitions functions
$patchworkFunctions = [
    'array_filter', 'preg_replace_callback', 'register_shutdown_function',
    'call_user_func_array', 'spl_autoload_register', 'array_map',
    'array_intersect_uassoc', 'array_udiff', 'array_uintersect_uassoc',
    'libxml_set_external_entity_loader', 'array_diff_uassoc', 'call_user_func',
    'array_udiff_assoc', 'iterator_apply', 'array_udiff_uassoc',
];

foreach ($patchworkFunctions as $func) {
    if (! function_exists("Patchwork\\Redefinitions\\{$func}")) {
        eval("namespace Patchwork\\Redefinitions; function {$func}(...\$args) { return \$args ? \$args[0] : null; }");
    }
}

// === END PATCHWORK PREVENTION ===

// Load composer autoloader
require_once __DIR__.'/../vendor/autoload.php';

// Disable any remaining Patchwork functionality
if (class_exists('Patchwork')) {
    if (method_exists('Patchwork', 'disable')) {
        Patchwork::disable();
    }
    if (method_exists('Patchwork', 'restoreAll')) {
        Patchwork::restoreAll();
    }
}

// Remove Patchwork from global functions if it exists
if (isset($GLOBALS['patchwork'])) {
    unset($GLOBALS['patchwork']);
}
if (isset($GLOBALS['__patchwork'])) {
    unset($GLOBALS['__patchwork']);
}

// Mock basic WordPress functions early
if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file).'/';
    }
}

if (! function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = [])
    {
        throw new Exception($message ?: 'WordPress died');
    }
}

// === WORDPRESS TEST ENVIRONMENT SETUP ===
$wp_tests_dir = getenv('WP_TESTS_DIR');
if ($wp_tests_dir && file_exists($wp_tests_dir.'/includes/bootstrap.php')) {
    // Load WordPress test bootstrap for proper environment
    require_once $wp_tests_dir.'/includes/bootstrap.php';
} else {
    // Fallback for local development without WordPress test suite

    // Define WordPress constants
    if (! defined('ABSPATH')) {
        define('ABSPATH', dirname(dirname(__DIR__)).'/');
    }
    if (! defined('WPINC')) {
        define('WPINC', 'wp-includes');
    }
    if (! defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', ABSPATH.'wp-content');
    }
    if (! defined('WP_PLUGIN_DIR')) {
        define('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins');
    }
    if (! defined('WP_CONTENT_URL')) {
        define('WP_CONTENT_URL', 'http://localhost/wp-content');
    }
    if (! defined('WP_PLUGIN_URL')) {
        define('WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');
    }
    if (! defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (! defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', false);
    }
    if (! defined('WP_DEBUG_DISPLAY')) {
        define('WP_DEBUG_DISPLAY', true);
    }

    // Set up in-memory database for tests
    global $wpdb, $db;
    if (! isset($wpdb)) {
        $wpdb = new stdClass;
        $wpdb->prefix = 'wp_';
    }

    // Create SQLite in-memory database
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create full WordPress schema for proper testing
    $db->exec("
        CREATE TABLE wp_options (
            option_id INTEGER PRIMARY KEY AUTOINCREMENT,
            option_name TEXT NOT NULL,
            option_value LONGTEXT,
            autoload VARCHAR(20) DEFAULT 'yes'
        );
        CREATE UNIQUE INDEX option_name_index ON wp_options (option_name);

        CREATE TABLE wp_posts (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            post_author INTEGER DEFAULT 0,
            post_date DATETIME DEFAULT '0000-00-00 00:00:00',
            post_date_gmt DATETIME DEFAULT '0000-00-00 00:00:00',
            post_content LONGTEXT,
            post_title TEXT,
            post_excerpt TEXT,
            post_status VARCHAR(20) DEFAULT 'publish',
            comment_status VARCHAR(20) DEFAULT 'open',
            ping_status VARCHAR(20) DEFAULT 'open',
            post_password VARCHAR(255),
            post_name VARCHAR(200),
            to_ping TEXT,
            pinged TEXT,
            post_modified DATETIME DEFAULT '0000-00-00 00:00:00',
            post_modified_gmt DATETIME DEFAULT '0000-00-00 00:00:00',
            post_content_filtered LONGTEXT,
            post_parent INTEGER DEFAULT 0,
            guid VARCHAR(255),
            menu_order INTEGER DEFAULT 0,
            post_type VARCHAR(20) DEFAULT 'post',
            post_mime_type VARCHAR(100),
            comment_count BIGINT DEFAULT 0
        );
        CREATE INDEX post_name_index ON wp_posts (post_name);
        CREATE INDEX type_status_date_index ON wp_posts (post_type, post_status, post_date, ID);
        CREATE INDEX post_parent_index ON wp_posts (post_parent);
        CREATE INDEX post_author_index ON wp_posts (post_author);

        CREATE TABLE wp_postmeta (
            meta_id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER DEFAULT 0,
            meta_key VARCHAR(255),
            meta_value LONGTEXT
        );
        CREATE INDEX post_id_index ON wp_postmeta (post_id);
        CREATE INDEX meta_key_index ON wp_postmeta (meta_key);

        CREATE TABLE wp_users (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            user_login VARCHAR(60) NOT NULL,
            user_nicename VARCHAR(50) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            user_url VARCHAR(100),
            user_registered DATETIME DEFAULT '0000-00-00 00:00:00',
            user_activation_key VARCHAR(255),
            user_status INTEGER DEFAULT 0,
            display_name VARCHAR(250)
        );
        CREATE UNIQUE INDEX user_login_key ON wp_users (user_login);
        CREATE INDEX user_nicename_index ON wp_users (user_nicename);
        CREATE INDEX user_email_index ON wp_users (user_email);

        CREATE TABLE wp_usermeta (
            umeta_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT 0,
            meta_key VARCHAR(255),
            meta_value LONGTEXT
        );
        CREATE INDEX user_id_index ON wp_usermeta (user_id);
        CREATE INDEX meta_key_user_index ON wp_usermeta (meta_key);
    ");

    // Mock WordPress functions for local testing
    if (! function_exists('get_option')) {
        function get_option($key, $default = '')
        {
            global $db;
            $stmt = $db->prepare('SELECT option_value FROM wp_options WHERE option_name = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['option_value'] : $default;
        }
    }

    if (! function_exists('update_option')) {
        function update_option($key, $value)
        {
            global $db;
            $stmt = $db->prepare('INSERT OR REPLACE INTO wp_options (option_name, option_value) VALUES (?, ?)');

            return $stmt->execute([$key, $value]);
        }
    }

    if (! function_exists('wp_die')) {
        function wp_die($message = '', $title = '', $args = [])
        {
            throw new Exception($message ?: 'WordPress died');
        }
    }

    if (! function_exists('apply_filters')) {
        function apply_filters($tag, $value)
        {
            return $value;
        }
    }

    if (! function_exists('add_action')) {
        function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
        {
            // Mock add_action - do nothing
        }
    }

    if (! function_exists('is_admin')) {
        function is_admin()
        {
            return false;
        }
    }

    if (! function_exists('home_url')) {
        function home_url($path = '')
        {
            return 'http://localhost'.$path;
        }
    }

    if (! function_exists('wp_upload_dir')) {
        function wp_upload_dir()
        {
            return [
                'path' => '/tmp/uploads',
                'url' => 'http://localhost/wp-content/uploads',
                'subdir' => '',
                'basedir' => '/tmp/uploads',
                'baseurl' => 'http://localhost/wp-content/uploads',
                'error' => false,
            ];
        }
    }

    if (! function_exists('get_file_data')) {
        function get_file_data($file, $headers)
        {
            return ['Version' => '1.3.6'];
        }
    }

    if (! function_exists('plugin_dir_path')) {
        function plugin_dir_path($file)
        {
            return dirname($file).'/';
        }
    }

    if (! function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512)
        {
            return \json_encode($data, $options, $depth);
        }
    }

    if (! function_exists('__')) {
        function __($text, $domain = 'default')
        {
            return $text;
        }
    }

    if (! function_exists('_e')) {
        function _e($text, $domain = 'default')
        {
            echo $text;
        }
    }

    if (! function_exists('site_url')) {
        function site_url($path = '')
        {
            return 'http://localhost'.$path;
        }
    }

    if (! function_exists('admin_url')) {
        function admin_url($path = '')
        {
            return 'http://localhost/wp-admin'.$path;
        }
    }

    if (! function_exists('get_site_url')) {
        function get_site_url($blog_id = null, $path = '', $scheme = null)
        {
            return 'http://localhost'.$path;
        }
    }

    if (! function_exists('wp_get_upload_dir')) {
        function wp_get_upload_dir()
        {
            return [
                'path' => '/tmp/uploads',
                'url' => 'http://localhost/wp-content/uploads',
                'subdir' => '',
                'basedir' => '/tmp/uploads',
                'baseurl' => 'http://localhost/wp-content/uploads',
                'error' => false,
            ];
        }
    }

    if (! function_exists('wp_normalize_path')) {
        function wp_normalize_path($path)
        {
            return str_replace('\\', '/', $path);
        }
    }

    if (! function_exists('wp_doing_ajax')) {
        function wp_doing_ajax()
        {
            return defined('DOING_AJAX') && DOING_AJAX;
        }
    }

    if (! function_exists('wp_doing_cron')) {
        function wp_doing_cron()
        {
            return defined('DOING_CRON') && DOING_CRON;
        }
    }

    if (! function_exists('wp_is_xml_request')) {
        function wp_is_xml_request()
        {
            return false;
        }
    }

    if (! function_exists('wp_is_json_request')) {
        function wp_is_json_request()
        {
            return false;
        }
    }

    if (! function_exists('wp_is_jsonp_request')) {
        function wp_is_jsonp_request()
        {
            return false;
        }
    }

    if (! function_exists('wp_kses_post')) {
        function wp_kses_post($data)
        {
            return $data;
        }
    }

    if (! function_exists('sanitize_text_field')) {
        function sanitize_text_field($str)
        {
            return trim($str);
        }
    }

    if (! function_exists('esc_attr')) {
        function esc_attr($text)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (! function_exists('esc_html')) {
        function esc_html($text)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (! function_exists('esc_url')) {
        function esc_url($url)
        {
            return filter_var($url, FILTER_SANITIZE_URL);
        }
    }

    if (! function_exists('wp_enqueue_script')) {
        function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false)
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_enqueue_style')) {
        function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_register_script')) {
        function wp_register_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false)
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_register_style')) {
        function wp_register_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_deregister_script')) {
        function wp_deregister_script($handle)
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_deregister_style')) {
        function wp_deregister_style($handle)
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_localize_script')) {
        function wp_localize_script($handle, $object_name, $l10n)
        {
            // Mock - do nothing
        }
    }

    if (! function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1)
        {
            return 'test_nonce_'.$action;
        }
    }

    if (! function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1)
        {
            return strpos($nonce, 'test_nonce_') === 0;
        }
    }

    if (! function_exists('current_user_can')) {
        function current_user_can($capability)
        {
            return true; // Assume admin for tests
        }
    }

    if (! function_exists('get_current_user_id')) {
        function get_current_user_id()
        {
            return 1;
        }
    }

    if (! function_exists('get_userdata')) {
        function get_userdata($user_id)
        {
            return (object) [
                'ID' => $user_id,
                'user_login' => 'testuser',
                'user_email' => 'test@example.com',
                'display_name' => 'Test User',
            ];
        }
    }

    if (! function_exists('is_user_logged_in')) {
        function is_user_logged_in()
        {
            return true;
        }
    }

    if (! function_exists('wp_get_current_user')) {
        function wp_get_current_user()
        {
            return (object) [
                'ID' => 1,
                'user_login' => 'testuser',
                'user_email' => 'test@example.com',
                'display_name' => 'Test User',
                'roles' => ['administrator'],
            ];
        }
    }

    if (! function_exists('get_bloginfo')) {
        function get_bloginfo($show = '')
        {
            $info = [
                'name' => 'Test Site',
                'description' => 'Test Description',
                'url' => 'http://localhost',
                'version' => '6.0',
                'charset' => 'UTF-8',
            ];

            return $info[$show] ?? '';
        }
    }

    if (! function_exists('get_option')) {
        function get_option($key, $default = '')
        {
            global $db;
            $stmt = $db->prepare('SELECT option_value FROM wp_options WHERE option_name = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['option_value'] : $default;
        }
    }

    if (! function_exists('update_option')) {
        function update_option($key, $value)
        {
            global $db;
            $stmt = $db->prepare('INSERT OR REPLACE INTO wp_options (option_name, option_value) VALUES (?, ?)');

            return $stmt->execute([$key, $value]);
        }
    }

    if (! function_exists('wp_die')) {
        function wp_die($message = '', $title = '', $args = [])
        {
            throw new Exception($message ?: 'WordPress died');
        }
    }

    if (! function_exists('apply_filters')) {
        function apply_filters($tag, $value)
        {
            return $value;
        }
    }

    if (! function_exists('add_action')) {
        function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
        {
            // Mock add_action - do nothing
        }
    }

    if (! function_exists('is_admin')) {
        function is_admin()
        {
            return false;
        }
    }

    if (! function_exists('home_url')) {
        function home_url($path = '')
        {
            return 'http://localhost'.$path;
        }
    }

    if (! function_exists('wp_upload_dir')) {
        function wp_upload_dir()
        {
            return [
                'path' => '/tmp/uploads',
                'url' => 'http://localhost/wp-content/uploads',
                'subdir' => '',
                'basedir' => '/tmp/uploads',
                'baseurl' => 'http://localhost/wp-content/uploads',
                'error' => false,
            ];
        }
    }

    if (! function_exists('get_file_data')) {
        function get_file_data($file, $headers)
        {
            return ['Version' => '1.3.6'];
        }
    }

    if (! function_exists('plugin_dir_path')) {
        function plugin_dir_path($file)
        {
            return dirname($file).'/';
        }
    }

    if (! function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512)
        {
            return \json_encode($data, $options, $depth);
        }
    }

    if (! function_exists('__')) {
        function __($text, $domain = 'default')
        {
            return $text;
        }
    }
}
