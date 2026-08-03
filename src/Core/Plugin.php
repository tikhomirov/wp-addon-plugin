<?php

namespace WpAddon\Core;

use WpAddon\ControllerWP;
use WpAddon\FrontWP;
use WpAddon\Services\AssetService;
use WpAddon\Services\ImageOptimizationService;
use WpAddon\Services\MediaCleanupService;
use WpAddon\Services\OptionService;
use WpAddon\WP_Addon_Settings;

/**
 * Main Plugin class for initialization and constants
 */
class Plugin
{
    private bool $initialized = false;

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
    private string $version = '1.4.0';

    /**
     * Text domain
     */
    private string $textDomain = 'wp-addon';

    /**
     * Option service
     */
    private OptionService $optionService;

    /**
     * Asset service
     */
    private AssetService $assetService;

    /**
     * Media cleanup service
     */
    private MediaCleanupService $mediaCleanupService;

    /**
     * Image optimization service
     */
    private ImageOptimizationService $imageOptimizationService;

    /**
     * Constructor
     *
     * @param  string  $file  Plugin file path
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
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
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
        if (! class_exists('CSF')) {
            add_action('admin_notices', [$this, 'renderMissingCodeStarNotice']);
        }
    }

    public function renderMissingCodeStarNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            .esc_html__('WP Addon requires the bundled CodeStar Framework. Reinstall the plugin from a complete release package.', 'wp-addon')
            .'</p></div>';
    }

    private function loadLocales(): void
    {
        add_action('plugins_loaded', function () {
            $domain = 'wp-addon';
            $path = dirname(plugin_basename(RW_FILE)).'/languages';
            load_plugin_textdomain($domain, false, $path);
        }, 9);
    }

    /**
     * Define plugin constants
     */
    private function defineConstants(): void
    {
        if (! defined('RW_LANG')) {
            define('RW_LANG', $this->textDomain);
        }

        if (! defined('RW_PLUGIN_DIR')) {
            define('RW_PLUGIN_DIR', $this->dir);
        }

        if (! defined('RW_PLUGIN_URL')) {
            define('RW_PLUGIN_URL', $this->url);
        }

        if (! defined('RW_FILE')) {
            define('RW_FILE', $this->file);
        }

        if (! defined('WP_ADDON_VERSION')) {
            define('WP_ADDON_VERSION', $this->version);
        }
    }

    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void
    {
        // Load CodeStar Framework if available
        $csf_file = $this->dir.'lib/codestar-framework/codestar-framework.php';
        if (file_exists($csf_file)) {
            require_once $csf_file;
        }

        // Load settings
        require_once $this->dir.'src/Config/wp-addon-settings.php';

        // Initialize services
        $this->optionService = new OptionService(RW_LANG);
        $this->assetService = new AssetService(RW_FILE, RW_PLUGIN_URL, RW_LANG, $this->version);
        $this->mediaCleanupService = new MediaCleanupService;
        $this->imageOptimizationService = new ImageOptimizationService;

        // Load functions and modules
        $this->loadModules();
    }

    /**
     * Load and initialize modules from functions directory
     */
    private function loadModules(): void
    {
        $initializedModules = [];
        $moduleDependencies = [
            'MediaCleanup' => [$this->mediaCleanupService],
            'PageCache' => [$this->optionService],
            'AssetMinification' => [$this->optionService],
            'LazyLoading' => [$this->optionService, $this->imageOptimizationService],
        ];

        $files = glob($this->dir.'functions/*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, 'WpAddon\Interfaces\ModuleInterface') && ! isset($initializedModules[$className])) {
                $module = new $className(...($moduleDependencies[$className] ?? []));
                $module->init();
                $initializedModules[$className] = true;
            }
        }

        $files = glob($this->dir.'functions/*/*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            require_once $file;
            $className = basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, 'WpAddon\Interfaces\ModuleInterface') && ! isset($initializedModules[$className])) {
                $module = new $className(...($moduleDependencies[$className] ?? []));
                $module->init();
                $initializedModules[$className] = true;
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
        $seo_dir = $this->dir.'functions/seo/';
        if (is_dir($seo_dir)) {
            foreach (glob($seo_dir.'*.php') as $file) {
                require_once $file;
            }
        }

        // Also load from functions/posts, functions/terms, etc
        $function_subdirs = ['posts', 'terms', 'comments', 'users', 'shortcodes', 'widgets', 'dashboard-widget', 'cf7', 'vc', 'TinyMCE'];
        foreach ($function_subdirs as $subdir) {
            $dir = $this->dir.'functions/'.$subdir.'/';
            if (is_dir($dir)) {
                foreach (glob($dir.'*.php') as $file) {
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
            WP_Addon_Settings::getInstance()->add_actions();
        }

        // Initialize front-end logic
        $frontWP = new FrontWP($this->optionService, $this->assetService);
        $frontWP->add_actions();

        // Initialize controller
        $controllerWP = new ControllerWP($this->optionService);
        $controllerWP->options_loader();
    }
}
