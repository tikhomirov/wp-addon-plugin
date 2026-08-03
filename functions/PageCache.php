<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Services\CacheService;
use WpAddon\Services\OptionService;

class PageCache implements ModuleInterface {
	private CacheService $cache;
	private OptionService $optionService;
	private array $config;

	public function __construct( OptionService $optionService ) {
		$this->optionService = $optionService;
		$this->loadConfig();
		$this->cache = new CacheService( $this->config['cache_dir'], $this->config['ttl'] );
	}

	private function loadConfig(): void {
		$defaultConfig = require RW_PLUGIN_DIR . 'src/Config/cache.php';

		$preloadPagesSetting = $this->optionService->getSetting( 'cache_preload_pages', '' );
		$preloadPages        = [];
		if ( ! empty( $preloadPagesSetting ) ) {
			$preloadPages = array_filter( explode( "\n", $preloadPagesSetting ) );
		}
		// Если preload пустой - будет заполнен автоматически в preloadPages()

		$this->config = [
			'enabled'            => $this->optionService->getSetting( 'cache_enabled', $defaultConfig['enabled'] ),
			'ttl'                => (int) $this->optionService->getSetting( 'cache_ttl', $defaultConfig['ttl'] ),
			'exclude_logged_in'  => $this->optionService->getSetting( 'cache_exclude_logged_in', $defaultConfig['exclude_logged_in'] ),
			'exclude_urls'       => array_filter( explode( "\n", $this->optionService->getSetting( 'cache_exclude_urls', implode( "\n", $defaultConfig['exclude_urls'] ) ) ) ),
			'preload_pages'      => $preloadPages,
			'auto_preload'       => empty( $preloadPagesSetting ),
			'clear_on_post_save' => $this->optionService->getSetting( 'cache_clear_on_post_save', true ),
			'cache_dir'          => $defaultConfig['cache_dir'],
			'max_files'          => (int) $defaultConfig['max_files'],
			'cleanup_batch_size' => (int) $defaultConfig['cleanup_batch_size'],
		];
	}

	public function init(): void {
		if ( ! $this->config['enabled'] ) {
			return;
		}

		add_action( 'init', [ $this, 'startCache' ] );
		if ( $this->config['clear_on_post_save'] ) {
			add_action( 'save_post', [ $this, 'clearCache' ] );
		}
		add_action( 'wp_loaded', [ $this, 'preloadPages' ] );

		// Preload and cleanup hooks.
		add_action( 'page_cache_preload', [ $this, 'doPreload' ] );
		add_action( 'page_cache_cleanup', [ $this, 'cleanupExpiredEntries' ] );
	}

	public function doPreload(): void {
		$preloadPagesSetting = $this->optionService->getSetting( 'cache_preload_pages', '' );
		$pages               = [];

		if ( ! empty( $preloadPagesSetting ) ) {
			$pages = array_filter( explode( "\n", $preloadPagesSetting ) );
		} else {
			// Auto preload mode
			$pages = $this->getAutoPreloadPages();
		}

		if ( ! empty( $pages ) ) {
			foreach ( $pages as $url ) {
				$response = wp_remote_get( home_url( trim( $url ) ) );
				if ( ! is_wp_error( $response ) ) {
					$key = $this->cache->generateCacheKey( $url );
					$this->cache->saveCachedContent( $key, wp_remote_retrieve_body( $response ) );
				}
			}
		}
	}

	private function getAutoPreloadPages(): array {
		$pages = [];

		// Получаем главную страницу
		$frontPageId = get_option( 'page_on_front' );
		if ( $frontPageId ) {
			$frontPageUrl = get_permalink( $frontPageId );
			if ( $frontPageUrl ) {
				$pages[] = parse_url( $frontPageUrl, PHP_URL_PATH ) ?: '/';
			}
		} else {
			$pages[] = '/';
		}

		// Получаем страницы из главного меню
		$locations = get_nav_menu_locations();
		if ( isset( $locations['primary'] ) || isset( $locations['main'] ) ) {
			$menuId    = $locations['primary'] ?? $locations['main'];
			$menuItems = wp_get_nav_menu_items( $menuId );

			if ( $menuItems ) {
				foreach ( $menuItems as $item ) {
					if ( $item->type === 'post_type' && $item->object === 'page' ) {
						$pageUrl = parse_url( $item->url, PHP_URL_PATH );
						if ( $pageUrl && $pageUrl !== '/' && ! in_array( $pageUrl, $pages ) ) {
							$pages[] = $pageUrl;
						}
					}
				}
			}
		}

		// Добавляем страницу блога, если она есть
		$blogPageId = get_option( 'page_for_posts' );
		if ( $blogPageId && $blogPageId !== $frontPageId ) {
			$blogUrl = get_permalink( $blogPageId );
			if ( $blogUrl ) {
				$blogPath = parse_url( $blogUrl, PHP_URL_PATH );
				if ( $blogPath && ! in_array( $blogPath, $pages ) ) {
					$pages[] = $blogPath;
				}
			}
		}

		return array_slice( $pages, 0, 10 ); // Ограничиваем до 10 страниц
	}

	public function preloadPages(): void {
		if ( ! wp_next_scheduled( 'page_cache_preload' ) ) {
			wp_schedule_event( time(), 'hourly', 'page_cache_preload' );
		}

		if ( ! wp_next_scheduled( 'page_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'page_cache_cleanup' );
		}
	}

	public function cleanupExpiredEntries(): void {
		$this->cache->cleanup( $this->config['max_files'], $this->config['ttl'], $this->config['cleanup_batch_size'] );
	}

	public function startCache(): void {
		if ( ! $this->shouldCache() ) {
			return;
		}

		$key    = $this->cache->generateCacheKey( $this->getCachePath() );
		$cached = $this->cache->getCachedContent( $key );

		if ( $cached ) {
			echo $cached;
			exit;
		}

		ob_start( [ $this, 'cacheOutput' ] );
	}

	public function shouldCache(): bool {
		if ( is_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'GET' ) {
			return false;
		}

		if ( $this->config['exclude_logged_in'] && is_user_logged_in() ) {
			return false;
		}

		$url = $_SERVER['REQUEST_URI'];
		if ( strpos( $url, '?' ) !== false || defined( 'REST_REQUEST' ) && REST_REQUEST || defined( 'DOING_CRON' ) && DOING_CRON || defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST || is_feed() || is_search() || is_preview() ) {
			return false;
		}

		$custom_login_slug = get_option( 'whl_page' );
		if ( ! empty( $custom_login_slug ) ) {
			$custom_login_path = '/' . ltrim( $custom_login_slug, '/' );
			if ( strpos( $url, $custom_login_path ) === 0 || isset( $_GET[ $custom_login_slug ] ) ) {
				return false;
			}
		}
		foreach ( $this->config['exclude_urls'] as $exclude ) {
			if ( strpos( $url, trim( $exclude ) ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	private function getCachePath(): string {
		return wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ?: '/';
	}

	public function cacheOutput( string $content ): string {
		if ( $this->shouldCache() ) {
			$key = $this->cache->generateCacheKey( $this->getCachePath() );
			$this->cache->saveCachedContent( $key, $content );
		}

		return $content;
	}

	public function clearCache(): void {
		$this->cache->clearCache();
	}

	public function getExcludeRules(): array {
		return $this->config['exclude_urls'];
	}
}
