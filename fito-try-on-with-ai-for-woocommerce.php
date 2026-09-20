<?php
/**
 * Plugin Name:       Fito Try-On with AI for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/fito-try-on-with-ai-for-woocommerce/
 * Description:       Enable customers to virtually try on clothing, shoes, glasses, jewelry, and accessories with AI (OpenRouter, Google Gemini, OpenAI).
 * Version:           1.3.0
 * Author:            micromax
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fito-try-on-with-ai-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.6
 */

defined('ABSPATH') || exit;

// Core plugin constants.
define('FITO_TRYON_VERSION', '1.3.0');
define('FITO_TRYON_FILE', __FILE__);
define('FITO_TRYON_PATH', plugin_dir_path(__FILE__));
define('FITO_TRYON_URL', plugin_dir_url(__FILE__));
define('FITO_TRYON_BASENAME', plugin_basename(__FILE__));

/**
 * Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Check dependencies and initialize the plugin.
 */
function fito_tryon_init(): void {
    // Ensure WooCommerce is active.
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'fito_tryon_missing_wc_notice');
        return;
    }

    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon.php';
    Fito_Tryon::instance()->boot();
    do_action('fito_tryon_loaded');
}
add_action('plugins_loaded', 'fito_tryon_init', 20);

/**
 * Admin notice when WooCommerce is inactive.
 */
function fito_tryon_missing_wc_notice(): void {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <strong><?php esc_html_e('Fito Try-On with AI for WooCommerce', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
            <?php esc_html_e('requires WooCommerce to be installed and active.', 'fito-try-on-with-ai-for-woocommerce'); ?>
        </p>
    </div>
    <?php
}

/**
 * Plugin activation handler.
 */
register_activation_hook(__FILE__, function () {
    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-database.php';
    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-storage.php';
    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-privacy.php';

    Fito_Tryon_Database::create_tables();
    Fito_Tryon_Storage::setup_storage_directory();
    Fito_Tryon_Privacy::schedule_cleanup_cron();

    // Register My Account rewrite endpoint and flush rewrite rules
    add_rewrite_endpoint('fito-tryons', EP_ROOT | EP_PAGES);
    flush_rewrite_rules(false);
});

/**
 * Plugin deactivation handler.
 */
register_deactivation_hook(__FILE__, function () {
    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-privacy.php';
    Fito_Tryon_Privacy::clear_cleanup_cron();
    flush_rewrite_rules(false);
});
