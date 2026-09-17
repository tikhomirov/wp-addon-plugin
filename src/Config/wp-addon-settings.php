<?php

namespace WpAddon;

use Automatic_Upgrader_Skin;
use Plugin_Upgrader;
use WpAddon\Services\MediaCleanupService;
use WpPackages\PluginsApi;

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

    private function get_github_owner(): string
    {
        if (defined('GITHUB_OWNER') && GITHUB_OWNER !== '') {
            return GITHUB_OWNER;
        }

        return 'tikhomirov';
    }

    private function get_github_request_headers(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'wp-addon-plugin',
        ];

        if (defined('GITHUB_TOKEN') && GITHUB_TOKEN !== '') {
            $headers['Authorization'] = 'token '.GITHUB_TOKEN;
        }

        return $headers;
    }

    private function set_github_plugins_error(string $message): void
    {
        set_transient('wp_addon_github_plugins_error', $message, 5 * MINUTE_IN_SECONDS);
    }

    private function clear_github_plugins_error(): void
    {
        delete_transient('wp_addon_github_plugins_error');
    }

    private function get_plugins_catalog_api_url(): string
    {
        if (defined('WP_ADDON_PLUGINS_API_URL') && WP_ADDON_PLUGINS_API_URL !== '') {
            return WP_ADDON_PLUGINS_API_URL;
        }

        if (post_type_exists('plugin')) {
            return rest_url('wp-packages/v1/plugins');
        }

        return 'https://rwsite.ru/wp-json/wp-packages/v1/plugins';
    }

    private function normalize_catalog_plugin(array $plugin): array
    {
        $slug = (string) ($plugin['slug'] ?? $plugin['name'] ?? '');
        $sourceType = (string) ($plugin['source_type'] ?? 'github');
        $githubRepo = (string) ($plugin['github_repo'] ?? '');
        $htmlUrl = (string) ($plugin['html_url'] ?? '');
        $zipUrl = (string) ($plugin['zip_url'] ?? '');

        if ($htmlUrl === '' && $githubRepo !== '') {
            $htmlUrl = 'https://github.com/'.ltrim($githubRepo, '/');
        }

        if ($zipUrl === '' && $githubRepo !== '') {
            $zipUrl = $htmlUrl.'/archive/refs/heads/main.zip';
        }

        return [
            'slug' => $slug,
            'title' => (string) ($plugin['title'] ?? $slug),
            'description' => (string) ($plugin['description'] ?? ''),
            'plugin_type' => (string) ($plugin['plugin_type'] ?? 'free'),
            'source_type' => $sourceType,
            'github_repo' => $githubRepo,
            'html_url' => $htmlUrl,
            'zip_url' => $zipUrl,
            'icon' => (string) ($plugin['icon'] ?? ''),
            'version' => (string) ($plugin['version'] ?? ''),
            'stars' => (int) ($plugin['stars'] ?? 0),
            'views' => (int) ($plugin['views'] ?? 0),
            'updated_label' => (string) ($plugin['updated_label'] ?? ''),
            'categories' => is_array($plugin['categories'] ?? null) ? $plugin['categories'] : [],
            'permalink' => (string) ($plugin['permalink'] ?? ''),
            'installable' => array_key_exists('installable', $plugin)
                ? (bool) $plugin['installable']
                : ($sourceType === 'github' && preg_match('/^wp-.*-plugin$/', $slug) === 1),
        ];
    }

    /**
     * Re-normalize cached plugin rows and drop legacy/broken entries
     * (e.g. old wp_addon_github_plugins cache without slug/title).
     */
    private function sanitize_cached_catalog(array $cached_plugins): array
    {
        $normalized = [];

        foreach ($cached_plugins as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            $item = $this->normalize_catalog_plugin($plugin);
            if ($item['slug'] === '' || $item['slug'] === 'wp-addon-plugin') {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function fetch_plugins_catalog_payload(): ?array
    {
        if (post_type_exists('plugin') && class_exists('\WpPackages\PluginsApi')) {
            $api = new PluginsApi;
            $request = new \WP_REST_Request('GET', '/wp-packages/v1/plugins');
            $request->set_param('per_page', 100);
            $response = $api->getPlugins($request);
            if ($response instanceof \WP_REST_Response) {
                $data = $response->get_data();

                return is_array($data) ? $data : null;
            }
        }

        $api_url = add_query_arg(['per_page' => 100], $this->get_plugins_catalog_api_url());
        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->set_github_plugins_error($response->get_error_message());

            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = is_array($body) && ! empty($body['message'])
                ? (string) $body['message']
                : 'HTTP '.$status_code;
            $this->set_github_plugins_error($message);

            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : null;
    }

    private function get_plugins_catalog_from_api(): array
    {
        $cache_key = 'wp_addon_plugins_catalog';
        $cached_plugins = get_transient($cache_key);
        if (is_array($cached_plugins) && ! empty($cached_plugins)) {
            return $this->sanitize_cached_catalog($cached_plugins);
        }

        $body = $this->fetch_plugins_catalog_payload();
        if (! is_array($body) || empty($body['plugins']) || ! is_array($body['plugins'])) {
            if ($body !== null) {
                $this->set_github_plugins_error('Некорректный ответ API каталога плагинов.');
            }

            return [];
        }

        $plugins = [];
        foreach ($body['plugins'] as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            $normalized = $this->normalize_catalog_plugin($plugin);
            if ($normalized['slug'] === '' || $normalized['slug'] === 'wp-addon-plugin') {
                continue;
            }

            $plugins[] = $normalized;
        }

        if (! empty($plugins)) {
            $this->clear_github_plugins_error();
            set_transient($cache_key, $plugins, HOUR_IN_SECONDS);
        } else {
            delete_transient($cache_key);
            $this->set_github_plugins_error('Каталог плагинов пуст.');
        }

        return $plugins;
    }

    private function get_github_plugins()
    {
        $cache_key = 'wp_addon_github_plugins';
        $cached_plugins = get_transient($cache_key);
        if (is_array($cached_plugins) && ! empty($cached_plugins)) {
            return $this->sanitize_cached_catalog($cached_plugins);
        }

        $owner = $this->get_github_owner();
        $headers = $this->get_github_request_headers();

        $response = wp_remote_get(
            sprintf('https://api.github.com/users/%s/repos?per_page=100&type=owner&sort=updated', $owner),
            [
                'headers' => $headers,
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            $this->set_github_plugins_error($response->get_error_message());

            return [];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = is_array($body) && ! empty($body['message'])
                ? (string) $body['message']
                : 'HTTP '.$status_code;
            $this->set_github_plugins_error($message);

            return [];
        }

        $repos = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($repos)) {
            $this->set_github_plugins_error('Некорректный ответ GitHub API.');

            return [];
        }

        $plugins = [];
        foreach ($repos as $repo) {
            if (! is_array($repo)) {
                continue;
            }

            $name = $repo['name'] ?? '';
            if ($name === '' || $name === 'wp-addon-plugin') {
                continue;
            }

            if (! preg_match('/^wp-.*-plugin$/', $name)) {
                continue;
            }

            $default_branch = $repo['default_branch'] ?? 'main';
            $plugins[] = $this->normalize_catalog_plugin([
                'slug' => $name,
                'title' => $name,
                'description' => $repo['description'] ?? '',
                'source_type' => 'github',
                'github_repo' => $owner.'/'.$name,
                'html_url' => $repo['html_url'] ?? 'https://github.com/'.$owner.'/'.$name,
                'zip_url' => 'https://github.com/'.$owner.'/'.$name.'/archive/refs/heads/'.$default_branch.'.zip',
                'installable' => true,
            ]);
        }

        if (! empty($plugins)) {
            $this->clear_github_plugins_error();
            set_transient($cache_key, $plugins, DAY_IN_SECONDS);
        } else {
            delete_transient($cache_key);
            $this->set_github_plugins_error('Плагины формата wp-*-plugin не найдены у пользователя '.$owner.'.');
        }

        return $plugins;
    }

    private function get_plugins_catalog(): array
    {
        $plugins = $this->get_plugins_catalog_from_api();
        if (empty($plugins)) {
            $plugins = $this->get_github_plugins();
        }

        $normalized = [];
        foreach ($plugins as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            $item = $this->normalize_catalog_plugin($plugin);
            if ($item['slug'] === '' || $item['slug'] === 'wp-addon-plugin') {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function get_plugin_install_state(string $slug, array $installed_plugins, array $active_plugins): array
    {
        foreach ($installed_plugins as $file => $data) {
            if (dirname($file) === $slug) {
                return [
                    'is_installed' => true,
                    'plugin_file' => $file,
                    'is_active' => in_array($file, $active_plugins, true),
                ];
            }
        }

        return [
            'is_installed' => false,
            'plugin_file' => null,
            'is_active' => false,
        ];
    }

    private function render_plugin_source_badge(string $sourceType): string
    {
        $class = match ($sourceType) {
            'composer' => 'my-plugins-badge-composer',
            'external' => 'my-plugins-badge-external',
            default => 'my-plugins-badge-github',
        };

        return '<span class="my-plugins-badge '.$class.'">'.esc_html(strtoupper($sourceType)).'</span>';
    }

    private function collect_plugin_categories(array $plugins): array
    {
        $categories = [];

        foreach ($plugins as $plugin) {
            if (! is_array($plugin['categories'] ?? null)) {
                continue;
            }

            foreach ($plugin['categories'] as $category) {
                if (! is_array($category) || empty($category['slug']) || empty($category['name'])) {
                    continue;
                }

                $categories[(string) $category['slug']] = (string) $category['name'];
            }
        }

        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return $categories;
    }

    private function build_plugin_search_index(array $plugin): string
    {
        $parts = [
            $plugin['slug'] ?? '',
            $plugin['title'] ?? '',
            $plugin['description'] ?? '',
            $plugin['github_repo'] ?? '',
            $plugin['version'] ?? '',
        ];

        if (is_array($plugin['categories'] ?? null)) {
            foreach ($plugin['categories'] as $category) {
                if (! is_array($category)) {
                    continue;
                }

                $parts[] = $category['name'] ?? '';
                $parts[] = $category['slug'] ?? '';
            }
        }

        return mb_strtolower(implode(' ', array_filter(array_map('strval', $parts))));
    }

    private function build_plugin_category_slugs(array $plugin): string
    {
        $slugs = [];

        if (is_array($plugin['categories'] ?? null)) {
            foreach ($plugin['categories'] as $category) {
                if (is_array($category) && ! empty($category['slug'])) {
                    $slugs[] = (string) $category['slug'];
                }
            }
        }

        return implode(',', $slugs);
    }

    private function render_plugin_card(array $plugin, array $installed_plugins, array $active_plugins): string
    {
        $slug = (string) ($plugin['slug'] ?? '');
        if ($slug === '') {
            return '';
        }
        $state = $this->get_plugin_install_state($slug, $installed_plugins, $active_plugins);
        $card_class = 'my-plugins-card';
        if ($state['is_installed']) {
            $card_class .= $state['is_active'] ? ' is-active' : ' is-inactive';
        }

        $icon = $plugin['icon'] !== ''
            ? '<img src="'.esc_url($plugin['icon']).'" alt="" loading="lazy" width="36" height="36">'
            : esc_html(mb_strtoupper(mb_substr($plugin['title'], 0, 1)));

        $title = $plugin['permalink'] !== ''
            ? '<a href="'.esc_url($plugin['permalink']).'" target="_blank" rel="noopener">'.esc_html($plugin['title']).'</a>'
            : esc_html($plugin['title']);

        $html = '<article class="'.esc_attr($card_class).'" data-search="'.esc_attr($this->build_plugin_search_index($plugin)).'" data-categories="'.esc_attr($this->build_plugin_category_slugs($plugin)).'">';
        $html .= '<div class="my-plugins-card-body">';
        $html .= '<div class="my-plugins-card-grid">';
        $html .= '<div class="my-plugins-icon">'.$icon.'</div>';
        $html .= '<div class="my-plugins-main">';
        $html .= '<div class="my-plugins-head">';
        $html .= '<h3 class="my-plugins-title">'.$title.'</h3>';
        $html .= $this->render_plugin_source_badge($plugin['source_type']);
        $html .= $plugin['plugin_type'] === 'premium'
            ? '<span class="my-plugins-badge my-plugins-badge-paid">PAID</span>'
            : '<span class="my-plugins-badge my-plugins-badge-free">FREE</span>';
        if ($plugin['version'] !== '') {
            $html .= '<span class="my-plugins-badge my-plugins-badge-version">v'.esc_html($plugin['version']).'</span>';
        }
        $html .= '<div class="my-plugins-stats">';
        if ($plugin['stars'] > 0) {
            $html .= '<span class="my-plugins-stat" title="GitHub Stars"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span>'.esc_html(number_format_i18n($plugin['stars'])).'</span>';
        }
        if ($plugin['views'] > 0) {
            $html .= '<span class="my-plugins-stat" title="Просмотры"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>'.esc_html(number_format_i18n($plugin['views'])).'</span>';
        }
        if ($plugin['updated_label'] !== '') {
            $html .= '<span class="my-plugins-stat" title="Обновлён"><span class="dashicons dashicons-clock" aria-hidden="true"></span>'.esc_html($plugin['updated_label']).'</span>';
        }
        $html .= '</div>';
        $html .= '</div>';

        if ($plugin['description'] !== '') {
            $html .= '<p class="my-plugins-desc">'.esc_html($plugin['description']).'</p>';
        }

        $html .= '<div class="my-plugins-bottom">';
        $html .= '<div class="my-plugins-meta">';
        foreach ($plugin['categories'] as $category) {
            if (! is_array($category) || empty($category['name'])) {
                continue;
            }
            $html .= '<span class="my-plugins-badge my-plugins-badge-category">'.esc_html((string) $category['name']).'</span>';
        }
        if ($plugin['github_repo'] !== '') {
            $html .= '<a class="my-plugins-badge my-plugins-badge-external" href="'.esc_url($plugin['html_url']).'" target="_blank" rel="noopener">'.esc_html($plugin['github_repo']).'</a>';
        }
        $html .= '</div>';

        if ($plugin['installable']) {
            $html .= '<div class="my-plugins-actions">';
            if ($plugin['html_url'] !== '') {
                $html .= '<a class="button button-small" href="'.esc_url($plugin['html_url']).'" target="_blank" rel="noopener">GitHub</a>';
            }
            if ($state['is_installed']) {
                $activate_text = $state['is_active'] ? 'Деактивировать' : 'Активировать';
                $activate_class = $state['is_active'] ? 'deactivate-plugin-btn button button-small' : 'activate-plugin-btn button button-small button-primary';
                $html .= '<button class="'.esc_attr($activate_class).'" data-repo="'.esc_attr($slug).'" data-file="'.esc_attr((string) $state['plugin_file']).'">'.esc_html($activate_text).'</button>';
                $html .= '<button class="uninstall-plugin-btn button button-small button-link-delete" data-repo="'.esc_attr($slug).'">Удалить</button>';
            } else {
                $html .= '<button class="install-plugin-btn button button-small button-primary" data-repo="'.esc_attr($slug).'" data-zip="'.esc_attr($plugin['zip_url']).'">Установить</button>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    private function getRedirectsInstructionsHtml(): string
    {
        $homeExample = esc_html('/old-page/');

        return '<div style="background:#f0f6fc;border:1px solid #d0d7de;border-radius:6px;padding:16px;margin:0 0 16px;line-height:1.55;color:#1d2327;">'
            .'<h4 style="margin:0 0 12px;">'.esc_html__('Как работают перенаправления', 'wp-addon').'</h4>'
            .'<p style="margin:0 0 12px;">'.sprintf(
                esc_html__('Плагин отдаёт ответ %1$s и перенаправляет посетителя с одного адреса на другой. Указывайте пути без домена — от корня WordPress, например %2$s (не /wp/old-page/, если сайт в подпапке). Абсолютные URL (https://...) — только в поле «Куда». Запросы к %3$s и %4$s никогда не перенаправляются.', 'wp-addon'),
                '<code>301 Moved Permanently</code>',
                '<code>'.$homeExample.'</code>',
                '<code>/wp-admin</code>',
                '<code>/wp-login</code>'
            ).'</p>'
            .'<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;font-size:13px;">'
            .'<div>'
            .'<strong>'.esc_html__('Слеш в конце URL', 'wp-addon').'</strong><br>'
            .esc_html__('/page и /page/ считаются одинаковыми. Можно писать в любом виде — плагин сравнивает пути без учёта завершающего слеша.', 'wp-addon')
            .'<br><br><strong>'.esc_html__('Простые правила', 'wp-addon').'</strong><br>'
            .'<code>/old-page/ → /new-page/</code><br>'
            .'<code>/archive/2020/ → /archive/</code><br>'
            .'<code>/contact → https://t.me/username</code>'
            .'<br><br><strong>'.esc_html__('Массовый импорт CSV', 'wp-addon').'</strong><br>'
            .esc_html__('Одна строка = одно правило. Разделители: запятая, ; или табуляция. Строки с # — комментарии. После сохранения правила попадут в список ниже.', 'wp-addon')
            .'</div>'
            .'<div>'
            .'<strong>'.esc_html__('Подстановочный знак * (не regex)', 'wp-addon').'</strong><br>'
            .esc_html__('Это не полноценные регулярные выражения — поддерживается только символ *. Включите опцию «Подстановочные знаки» выше.', 'wp-addon')
            .'<br><br><code>/old-blog/* → /blog/*</code><br>'
            .'<code>/docs/*/edit → /help/*/edit</code><br>'
            .'<code>/ru/* → /en/*</code><br>'
            .'<code>/shop/category/* → /catalog/*</code><br>'
            .'<code>/files/*.pdf → /media/*.pdf</code>'
            .'<br><br><strong>'.esc_html__('Что важно помнить', 'wp-addon').'</strong><br>'
            .esc_html__('Параметры запроса (?utm=...) при сравнении не учитываются. Если правило совпало — редирект сработает и для URL с query string. Не создавайте циклы: /a → /b и /b → /a.', 'wp-addon')
            .'</div>'
            .'</div>'
            .'</div>';
    }

    public function get_plugins_html()
    {
        $plugins = $this->get_plugins_catalog();
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $source_label = get_transient('wp_addon_plugins_catalog') ? __('каталог rwsite', 'wp-addon') : __('GitHub', 'wp-addon');
        $categories = $this->collect_plugin_categories($plugins);

        $html = '<div class="my-plugins-wrap">';
        $html .= '<div class="my-plugins-toolbar">';
        $html .= '<div class="my-plugins-toolbar-meta">';
        if (! empty($plugins)) {
            $html .= sprintf(
                __('Показано: %1$s из %2$d. Источник: %3$s.', 'wp-addon'),
                '<span id="my-plugins-visible-count">'.count($plugins).'</span>',
                count($plugins),
                esc_html($source_label)
            );
        }
        $html .= '</div>';
        $html .= '<button id="refresh-plugins-list" class="button button-small">'.esc_html__('Обновить список', 'wp-addon').'</button>';
        $html .= '</div>';

        if (! empty($plugins)) {
            $html .= '<div class="my-plugins-filters">';
            $html .= '<input type="search" id="my-plugins-search" class="my-plugins-search" placeholder="'.esc_attr__('Поиск по названию, описанию или репозиторию', 'wp-addon').'" aria-label="'.esc_attr__('Поиск плагинов', 'wp-addon').'">';
            $html .= '<select id="my-plugins-category" class="my-plugins-category" aria-label="'.esc_attr__('Категория', 'wp-addon').'">';
            $html .= '<option value="">'.esc_html__('Все категории', 'wp-addon').'</option>';
            foreach ($categories as $slug => $name) {
                $html .= '<option value="'.esc_attr($slug).'">'.esc_html($name).'</option>';
            }
            $html .= '</select>';
            $html .= '</div>';
        }

        if (empty($plugins)) {
            $error = get_transient('wp_addon_github_plugins_error');
            $html .= '<div class="my-plugins-empty">';
            if ($error) {
                $html .= esc_html__('Не удалось загрузить список плагинов:', 'wp-addon').' '.esc_html($error);
            } else {
                $html .= esc_html__('Не удалось загрузить список плагинов.', 'wp-addon');
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="my-plugins-no-results">'.esc_html__('По вашему запросу ничего не найдено.', 'wp-addon').'</div>';
            $html .= '<div class="my-plugins-list">';
            foreach ($plugins as $plugin) {
                $html .= $this->render_plugin_card($plugin, $installed_plugins, $active_plugins);
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '<script type="text/javascript">
        jQuery(document).ready(function($) {
            function filterMyPlugins() {
                var query = ($("#my-plugins-search").val() || "").toLowerCase().trim();
                var category = $("#my-plugins-category").val() || "";
                var visible = 0;

                $(".my-plugins-card").each(function() {
                    var card = $(this);
                    var search = (card.data("search") || "").toString().toLowerCase();
                    var categories = (card.data("categories") || "").toString();
                    var categoryMatch = !category || categories.split(",").indexOf(category) !== -1;
                    var searchMatch = !query || search.indexOf(query) !== -1;
                    var show = categoryMatch && searchMatch;

                    card.toggle(show);
                    if (show) {
                        visible++;
                    }
                });

                $("#my-plugins-visible-count").text(visible);
                $(".my-plugins-no-results").toggle(visible === 0);
            }

            $("#my-plugins-search").on("input", filterMyPlugins);
            $("#my-plugins-category").on("change", filterMyPlugins);

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
                        location.reload();
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
        delete_transient('wp_addon_plugins_catalog');
        delete_transient('wp_addon_github_plugins');
        delete_transient('wp_addon_github_plugins_error');
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

        wp_enqueue_style(
            $this->wp_plugin_slug.'-my-plugins',
            RW_PLUGIN_URL.'assets/css/my-plugins.css',
            [$this->wp_plugin_slug],
            $this->ver,
            'all'
        );
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
            'description' => __('Page caching saves ready HTML and gzip files. Apache rules are installed automatically. For Nginx, include the generated wp-content/cache/pages/nginx.conf file inside the HTTPS server block and reload Nginx. Disable other full-page cache plugins to avoid conflicts.', 'wp-addon'),
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
                    'desc' => __('How long browsers may reuse a cached page. Cached files are also purged automatically whenever posts, terms, comments, menus, widgets or theme settings change. The recommended default is 3600 seconds.', 'wp-addon'),
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
                    'default' => "/wp-admin/\n/wp-login.php\n/wp-json/\n/xmlrpc.php\n/cart/\n/checkout/\n/my-account/",
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
                [
                    'id' => 'cache_status',
                    'type' => 'content',
                    'content' => class_exists('PageCache') ? \PageCache::renderAdminPanel() : '',
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
            'description' => __('301 redirect management for old URLs, moved pages and bulk migrations. Read the guide below for slash rules, wildcard examples and CSV import format.', 'wp-addon'),
            'fields' => [
                [
                    'type' => 'content',
                    'content' => $this->getRedirectsInstructionsHtml(),
                ],
                [
                    'id' => 'redirect_enable',
                    'type' => 'switcher',
                    'title' => __('Enable redirects', 'wp-addon'),
                    'desc' => __('When disabled, redirect rules are saved but not applied on the site.', 'wp-addon'),
                    'default' => true,
                ],
                [
                    'id' => 'redirects_wildcard',
                    'type' => 'switcher',
                    'title' => __('Use wildcard redirects', 'wp-addon'),
                    'desc' => __('Required for rules with *. Without this option only exact URL matches work, e.g. /old-page/ → /new-page/.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'redirects_csv_import',
                    'type' => 'code_editor',
                    'title' => __('Bulk CSV import', 'wp-addon'),
                    'desc' => __('Paste rules here and save settings. Format: source,destination — one rule per line. Duplicate source URLs are overwritten. The field is cleared after a successful import.', 'wp-addon'),
                    'settings' => [
                        'theme' => 'mbo',
                        'mode' => 'shell',
                        'lineNumbers' => true,
                        'tabSize' => 2,
                    ],
                    'attributes' => [
                        'rows' => 12,
                        'placeholder' => "# source,destination\n/old-page/,/new-page/\n/old-blog/*,/blog/*\n/ru/docs/*,/en/docs/*",
                    ],
                    'default' => '',
                    'sanitize' => false,
                ],
                [
                    'id' => 'redirects_rules',
                    'type' => 'repeater',
                    'title' => __('Redirect rules', 'wp-addon'),
                    'desc' => __('Manual rule list. «Request URL» is the old address visitors open. «Destination URL» is where they should land. Trailing slashes are optional.', 'wp-addon'),
                    'fields' => [
                        [
                            'id' => 'request',
                            'type' => 'text',
                            'title' => __('Request URL', 'wp-addon'),
                            'desc' => __('Old URL path relative to site root. Examples: /old-page/, /old-page (same), /old-folder/* with wildcards enabled.', 'wp-addon'),
                            'attributes' => [
                                'placeholder' => '/old-page/',
                            ],
                        ],
                        [
                            'id' => 'destination',
                            'type' => 'text',
                            'title' => __('Destination URL', 'wp-addon'),
                            'desc' => __('New address: site path (/new-page/) or full URL (https://example.com/page/). For wildcards use * in the same position as in the source.', 'wp-addon'),
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

        // Cookie Banner
        \CSF::createSection($prefix, [
            'title' => __('Cookie Banner', 'wp-addon'),
            'icon' => 'fa fa-cookie-bite',
            'description' => __('Cookie consent banner with configurable text, links, button mode and deferred analytics loading.', 'wp-addon'),
            'fields' => require_once __DIR__.'/cookie-banner.php',
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
            'description' => __('Markdown Editor позволяет писать и редактировать контент в удобном Markdown-синтаксисе. При сохранении Markdown автоматически конвертируется в HTML и сохраняется в основное поле контента — сам исходник Markdown нигде не хранится, и посты на сайте всегда отдают чистый HTML.<br><br><strong>Как работает синхронизация с обычным редактором:</strong><br>• если контент правили в Markdown — сохраненный HTML получается из Markdown<br>• если правили только в стандартном редакторе (TinyMCE) — его HTML сохраняется как есть, а Markdown-поле просто обновляется при следующем открытии', 'wp-addon'),
            'fields' => [
                [
                    'id' => 'wp_addon_markdown_enabled',
                    'type' => 'switcher',
                    'title' => __('Enable Markdown editor', 'wp-addon'),
                    'desc' => __('Включает Markdown-редактор для записей и страниц. В основном поле поста сохраняется только HTML, а исходный Markdown конвертируется при сохранении и не хранится отдельно.', 'wp-addon'),
                    'default' => false,
                ],
                [
                    'id' => 'markdown_post_types',
                    'type' => 'checkbox',
                    'title' => __('Post types', 'wp-addon'),
                    'desc' => __('Типы записей, для которых будет доступен Markdown-редактор. Конвертация в HTML и синхронизация с основным редактором применяется только к выбранным типам.', 'wp-addon'),
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
                    'title' => __('Replace the standard editor', 'wp-addon'),
                    'desc' => __('Скрывает стандартный редактор TinyMCE и оставляет только Markdown. Если выключено — оба редактора доступны, и при сохранении учитываются только те, в которых были правки.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_enable_preview',
                    'type' => 'switcher',
                    'title' => __('Live preview', 'wp-addon'),
                    'desc' => __('Показывает предпросмотр Markdown рядом с редактором и добавляет кнопку «Side-by-side». При выключении предпросмотр полностью скрывается.', 'wp-addon'),
                    'default' => true,
                    'dependency' => ['wp_addon_markdown_enabled', '==', 'true'],
                ],
                [
                    'id' => 'markdown_enable_shortcuts',
                    'type' => 'switcher',
                    'title' => __('Keyboard shortcuts', 'wp-addon'),
                    'desc' => __('Горячие клавиши для быстрого форматирования: Ctrl+B (жирный), Ctrl+I (курсив), Ctrl+K (ссылка).', 'wp-addon'),
                    'default' => true,
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
