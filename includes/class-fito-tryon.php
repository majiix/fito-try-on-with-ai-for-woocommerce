<?php
/**
 * Master orchestrator class for Elfit AI Virtual Try-On for WooCommerce.
 */

defined('ABSPATH') || exit;

if (!class_exists('Fito_Tryon')) {
class Fito_Tryon {
    private static ?Fito_Tryon $instance = null;

    public Fito_Tryon_Database $db;
    public Fito_Tryon_Storage $storage;
    public Fito_Tryon_Rate_Limiter $rate_limiter;
    public Fito_Tryon_Privacy $privacy;
    public Fito_Tryon_OpenRouter $openrouter;
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
        $this->privacy = new Fito_Tryon_Privacy();
        $this->openrouter = new Fito_Tryon_OpenRouter();
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
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-privacy.php';
        require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-openrouter.php';
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
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'elfit-ai-virtual-try-on-for-woocommerce') . '</a>';
        array_unshift($links, $settings_link);

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

    /**
     * Retrieve all permitted AI image generation providers.
     *
     * @return array<int, string>
     */
    public function get_allowed_providers(): array {
        return (array) apply_filters('fito_tryon_allowed_providers', ['openrouter']);
    }

    /**
     * Get active AI provider slug.
     *
     * @return string
     */
    public function get_active_provider(): string {
        $provider = (string) get_option('fito_tryon_ai_provider', 'openrouter');
        $allowed  = $this->get_allowed_providers();
        if (!in_array($provider, $allowed, true)) {
            return 'openrouter';
        }
        return $provider;
    }

    /**
     * Resolve provider client object.
     *
     * @param string $provider
     * @return object|null
     */
    public function get_provider_client(string $provider = ''): ?object {
        if (empty($provider)) {
            $provider = $this->get_active_provider();
        }
        if ($provider === 'openrouter') {
            return $this->openrouter;
        }
        return apply_filters('fito_tryon_resolve_provider_client', null, $provider);
    }

    /**
     * Retrieve active primary model for current provider.
     *
     * @return string
     */
    public function get_active_primary_model(): string {
        $provider = $this->get_active_provider();
        $client = $this->get_provider_client($provider);
        if ($client && method_exists($client, 'get_primary_model')) {
            return $client->get_primary_model();
        }
        return $this->openrouter->get_primary_model();
    }

    /**
     * Generate virtual try-on using the active provider client.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function generate_tryon(array $params): array {
        $provider = $this->get_active_provider();
        $client = $this->get_provider_client($provider);
        if ($client && method_exists($client, 'generate_tryon')) {
            return $client->generate_tryon($params);
        }
        return $this->openrouter->generate_tryon($params);
    }
}
}
