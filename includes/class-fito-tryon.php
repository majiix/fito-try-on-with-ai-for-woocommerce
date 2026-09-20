<?php
/**
 * Master orchestrator class for Fito Try-On with AI for WooCommerce.
 */

defined('ABSPATH') || exit;

class Fito_Tryon {
    private static ?Fito_Tryon $instance = null;

    public Fito_Tryon_Database $db;
    public Fito_Tryon_Storage $storage;
    public Fito_Tryon_Rate_Limiter $rate_limiter;
    public Fito_Tryon_Cache $cache;
    public Fito_Tryon_Privacy $privacy;
    public Fito_Tryon_OpenRouter $openrouter;
    public Fito_Tryon_Gemini $gemini;
    public Fito_Tryon_OpenAI $openai;
    public Fito_Tryon_Rest $rest;
    public Fito_Tryon_Public $public;
    public ?Fito_Tryon_Admin $admin = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Boot the plugin subsystems.
     */
    public function boot(): void {
        $this->load_dependencies();
        $this->init_hooks();

        Fito_Tryon_Database::maybe_upgrade_schema();
        $this->db = new Fito_Tryon_Database();
        $this->storage = new Fito_Tryon_Storage();
        $this->rate_limiter = new Fito_Tryon_Rate_Limiter();
        $this->cache = new Fito_Tryon_Cache();
        $this->privacy = new Fito_Tryon_Privacy();
        $this->openrouter = new Fito_Tryon_OpenRouter();
        $this->gemini = new Fito_Tryon_Gemini($this);
        $this->openai = new Fito_Tryon_OpenAI($this);
        $this->rest = new Fito_Tryon_Rest($this);
        $this->public = new Fito_Tryon_Public($this);

        if (is_admin()) {
            $this->admin = new Fito_Tryon_Admin($this);
        }

        $this->register_blocks();

        do_action('fito_tryon_init', $this);
    }

    private function load_dependencies(): void {
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-database.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-storage.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-rate-limiter.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-cache.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-privacy.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-openrouter.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-gemini.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-openai.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-rest.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-public.php';

        if (is_admin()) {
            require_once FITO_TRYON_PATH . 'admin/class-fito-tryon-admin.php';
        }
    }

    private function init_hooks(): void {
        add_filter('plugin_action_links_' . FITO_TRYON_BASENAME, [$this, 'add_action_links']);
    }

    /**
     * Add settings link to the plugins page.
     *
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public function add_action_links(array $links): array {
        $settings_url = admin_url('admin.php?page=fito-tryon-settings');
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'fito-try-on-with-ai-for-woocommerce') . '</a>';
        array_unshift($links, $settings_link);

        if (!self::is_pro_active()) {
            $pro_url = admin_url('admin.php?page=fito-tryon-pro');
            $links['go_pro'] = '<a href="' . esc_url($pro_url) . '">' . esc_html__('Go Pro', 'fito-try-on-with-ai-for-woocommerce') . '</a>';
        }

        return $links;
    }

    private function register_blocks(): void {
        add_action('init', function () {
            if (function_exists('register_block_type')) {
                register_block_type(FITO_TRYON_PATH . 'includes/blocks/tryon-button', [
                    'render_callback' => [$this->public, 'render_block'],
                ]);
            }
        });
    }

    public function get_active_provider(): string {
        $provider = (string) get_option('fito_tryon_ai_provider', 'openrouter');
        if (!self::is_pro_active() && in_array($provider, ['gemini', 'openai'], true)) {
            return 'openrouter';
        }
        return $provider;
    }

    public function get_active_primary_model(): string {
        $provider = $this->get_active_provider();
        if ($provider === 'gemini' && isset($this->gemini)) {
            return $this->gemini->get_primary_model();
        }
        if ($provider === 'openai' && isset($this->openai)) {
            return $this->openai->get_primary_model();
        }
        return $this->openrouter->get_primary_model();
    }

    public function generate_tryon(array $params): array {
        $provider = $this->get_active_provider();
        if ($provider === 'gemini' && isset($this->gemini)) {
            return $this->gemini->generate_tryon($params);
        }
        if ($provider === 'openai' && isset($this->openai)) {
            return $this->openai->generate_tryon($params);
        }
        return $this->openrouter->generate_tryon($params);
    }

    /**
     * Check whether the Pro Addon is active and compatible.
     */
    public static function is_pro_active(): bool {
        $filtered = apply_filters('fito_tryon_is_pro_active', null);
        if ($filtered !== null) {
            return (bool) $filtered;
        }

        static $cached_pro = null;
        if ($cached_pro !== null) {
            return $cached_pro;
        }

        if (!defined('FITO_TRYON_VERSION') || !defined('FITO_TRYON_PRO_VERSION')) {
            $cached_pro = false;
            return false;
        }

        if (FITO_TRYON_PRO_VERSION !== FITO_TRYON_VERSION) {
            $cached_pro = false;
            return false;
        }

        if (!class_exists('Fito_Tryon_Pro_Addon')) {
            $cached_pro = false;
            return false;
        }

        $pro_basename = defined('FITO_TRYON_PRO_FILE')
            ? plugin_basename(FITO_TRYON_PRO_FILE)
            : 'fito-try-on-with-ai-for-woocommerce-pro-addon/fito-try-on-with-ai-for-woocommerce-pro-addon.php';

        $pro_file = defined('FITO_TRYON_PRO_FILE')
            ? FITO_TRYON_PRO_FILE
            : (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/' . $pro_basename : '');

        if (empty($pro_file) || !file_exists($pro_file)) {
            $cached_pro = false;
            return false;
        }

        if (!function_exists('is_plugin_active') && defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_active_plugin = false;
        if (function_exists('is_plugin_active')) {
            $is_active_plugin = is_plugin_active($pro_basename);
        } else {
            if (!function_exists('get_option')) {
                $cached_pro = false;
                return false;
            }
            $active_plugins = (array) get_option('active_plugins', []);
            $is_active_plugin = in_array($pro_basename, $active_plugins, true);
            if (!$is_active_plugin && function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
                $network_plugins = (array) get_site_option('active_sitewide_plugins', []);
                $is_active_plugin = isset($network_plugins[$pro_basename]);
            }
        }

        if (!$is_active_plugin) {
            $cached_pro = false;
            return false;
        }

        $cached_pro = (bool) apply_filters('fito_tryon_is_pro_active', true);
        return $cached_pro;
    }
}
