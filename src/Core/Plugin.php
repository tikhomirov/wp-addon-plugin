<?php

namespace WpAddon\Core;

/**
 * Main Plugin class for initialization and constants
 */
class Plugin
{
    /**
     * Plugin file path
     */
    private string $file;

    /**
     * Plugin directory path
     */
    private string $dir;

    /**
     * Plugin URL
     */
    private string $url;

    /**
     * Plugin version
     */
    private string $version = '1.3.6';

    /**
     * Text domain
     */
    private string $textDomain = 'wp-addon';

    /**
     * Option service
     */
    private \WpAddon\Services\OptionService $optionService;

    /**
     * Asset service
     */
    private \WpAddon\Services\AssetService $assetService;

    /**
     * Media cleanup service
     */
    private \WpAddon\Services\MediaCleanupService $mediaCleanupService;

    /**
     * Image optimization service
     */
    private \WpAddon\Services\ImageOptimizationService $imageOptimizationService;

    /**
     * Constructor
     *
     * @param string $file Plugin file path
     */
    public function __construct(string $file)
    {
        $this->file = $file;
        $this->dir = plugin_dir_path($file);
        $this->url = plugin_dir_url($file);
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        register_activation_hook($this->file, [$this, 'activate']);

        $this->defineConstants();
		$this->loadLocales();
        $this->loadDependencies();
        $this->addHooks();
    }

    /**
     * Plugin activation hook
     */
    public function activate(): void
    {
        // Check if CodeStar Framework is installed
        if (!class_exists('CSF')) {
            $this->installCodestarFramework();
        }
    }

    /**
     * Install CodeStar Framework
     */
    private function installCodestarFramework(): void
    {
        $csf_dir = $this->dir . 'lib/codestar-framework/';

        // Check if already downloaded
        if (file_exists($csf_dir . 'codestar-framework.php')) {
            require_once $csf_dir . 'codestar-framework.php';
            return;
        }

        // Download CodeStar Framework
        $zip_url = 'https://github.com/Codestar/codestar-framework/archive/refs/heads/master.zip';
        $temp_zip = download_url($zip_url);

        if (is_wp_error($temp_zip)) {
            wp_die(__('Error downloading CodeStar Framework. Please install it manually from https://github.com/Codestar/codestar-framework', 'wp-addon'));
        }

        // Unzip
        WP_Filesystem();
        global $wp_filesystem;

        $unzip_result = unzip_file($temp_zip, $this->dir . 'lib/');

        // Clean up temp file
        @unlink($temp_zip);

        if (is_wp_error($unzip_result)) {
            wp_die(__('Error unpacking CodeStar Framework. Please install it manually.', 'wp-addon'));
        }

        // Rename directory
        $extracted_dir = $this->dir . 'lib/codestar-framework-master/';
        if (file_exists($extracted_dir)) {
            $wp_filesystem->move($extracted_dir, $csf_dir);
        }

        // Include CSF
        if (file_exists($csf_dir . 'codestar-framework.php')) {
            require_once $csf_dir . 'codestar-framework.php';
        } else {
            wp_die(__('CodeStar Framework not found after installation. Please install it manually.', 'wp-addon'));
        }
    }


	private function loadLocales(): void {
		add_action( 'plugins_loaded', function () {
			$domain = 'wp-addon';
			$path = dirname( plugin_basename( RW_FILE ) ) . '/languages';
			load_plugin_textdomain( $domain, false, $path );
		}, 9 );
	}

    /**
     * Define plugin constants
     */
    private function defineConstants(): void
    {
        if (!defined('RW_LANG')) {
            define('RW_LANG', $this->textDomain);
        }

        if (!defined('RW_PLUGIN_DIR')) {
            define('RW_PLUGIN_DIR', $this->dir);
        }

        if (!defined('RW_PLUGIN_URL')) {
            define('RW_PLUGIN_URL', $this->url);
        }

        if (!defined('RW_FILE')) {
            define('RW_FILE', $this->file);
        }

        if (!defined('WP_ADDON_VERSION')) {
            define('WP_ADDON_VERSION', $this->version);
        }
    }

    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void
    {
        // Load CodeStar Framework if available
        $csf_file = $this->dir . 'lib/codestar-framework/codestar-framework.php';
        if (file_exists($csf_file)) {
            require_once $csf_file;
        }

        // Load settings
        require_once $this->dir . 'src/Config/wp-addon-settings.php';

        // Initialize services
        $this->optionService = new \WpAddon\Services\OptionService(RW_LANG);
        $this->assetService = new \WpAddon\Services\AssetService(RW_FILE, RW_PLUGIN_URL, RW_LANG, $this->version);
        $this->mediaCleanupService = new \WpAddon\Services\MediaCleanupService();
        $this->imageOptimizationService = new \WpAddon\Services\ImageOptimizationService();

        // Load functions and modules
        $this->loadModules();
    }

    /**
     * Load and initialize modules from functions directory
     */
    private function loadModules(): void {
        foreach (glob($this->dir . 'functions/*.php') as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, 'WpAddon\Interfaces\ModuleInterface')) {
                // For complex modules inject dependencies
                if ($className === 'MediaCleanup') {
                    $module = new $className($this->mediaCleanupService);
                } elseif ($className === 'PageCache' || $className === 'AssetMinification') {
                    $module = new $className($this->optionService);
                } elseif ($className === 'LazyLoading') {
                    $module = new $className($this->optionService, $this->imageOptimizationService);
                } else {
                    $module = new $className();
                }
                $module->init();
            }
        }

        foreach (glob($this->dir . 'functions/*/*.php') as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, 'WpAddon\Interfaces\ModuleInterface')) {
                $module = new $className();
                $module->init();
            }
        }
    }

    /**
     * Add plugin hooks
     */
    private function addHooks(): void
    {
        add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
        add_action('init', [$this, 'loadSeoFunctions'], 1);
    }

    /**
     * Load SEO functions early
     */
    public function loadSeoFunctions(): void
    {
        $seo_dir = $this->dir . 'functions/seo/';
        if (is_dir($seo_dir)) {
            foreach (glob($seo_dir . '*.php') as $file) {
                require_once $file;
            }
        }
        
        // Also load from functions/posts, functions/terms, etc
        $function_subdirs = ['posts', 'terms', 'comments', 'users', 'shortcodes', 'widgets', 'dashboard-widget', 'cf7', 'vc', 'TinyMCE'];
        foreach ($function_subdirs as $subdir) {
            $dir = $this->dir . 'functions/' . $subdir . '/';
            if (is_dir($dir)) {
                foreach (glob($dir . '*.php') as $file) {
                    require_once $file;
                }
            }
        }
    }

    /**
     * Callback for plugins_loaded hook
     */
    public function onPluginsLoaded(): void
    {
        // Initialize settings
        if (class_exists('\WpAddon\WP_Addon_Settings')) {
            \WpAddon\WP_Addon_Settings::getInstance()->add_actions();
        }

        // Initialize front-end logic
        $frontWP = new \WpAddon\FrontWP($this->optionService, $this->assetService);
        $frontWP->add_actions();

        // Initialize controller
        $controllerWP = new \WpAddon\ControllerWP($this->optionService);
        $controllerWP->options_loader();
    }
}
