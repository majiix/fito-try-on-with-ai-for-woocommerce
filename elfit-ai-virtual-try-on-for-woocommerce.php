<?php
/**
 * Plugin Name:       Elfit AI Virtual Try-On for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/elfit-ai-virtual-try-on-for-woocommerce/
 * Description:       Enable customers to virtually try on clothing, shoes, glasses, jewelry, and accessories directly in WooCommerce using OpenRouter AI.
 * Version:           1.7.0
 * Author:            micromax
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elfit-ai-virtual-try-on-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

// Core plugin constants (guarded against duplicate definition).
if (!defined('FITO_TRYON_VERSION')) {
    define('FITO_TRYON_VERSION', '1.7.0');
}
if (!defined('FITO_TRYON_FILE')) {
    define('FITO_TRYON_FILE', __FILE__);
}
if (!defined('FITO_TRYON_PATH')) {
    define('FITO_TRYON_PATH', plugin_dir_path(__FILE__));
}
if (!defined('FITO_TRYON_URL')) {
    define('FITO_TRYON_URL', plugin_dir_url(__FILE__));
}
if (!defined('FITO_TRYON_BASENAME')) {
    define('FITO_TRYON_BASENAME', plugin_basename(__FILE__));
}

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
if (!function_exists('fito_tryon_init')) {
    function fito_tryon_init(): void {
        // Prevent collision if another version was initialized earlier.
        if (class_exists('Fito_Tryon', false)) {
            return;
        }

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
}

/**
 * Admin notice when WooCommerce is inactive.
 */
if (!function_exists('fito_tryon_missing_wc_notice')) {
    function fito_tryon_missing_wc_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e('Elfit AI Virtual Try-On for WooCommerce', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></strong>
                <?php esc_html_e('requires WooCommerce to be installed and active.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Plugin activation handler.
 */
register_activation_hook(__FILE__, function () {
    $plugin_dir = plugin_dir_path(__FILE__);
    if (!class_exists('Fito_Tryon_Database')) {
        require_once $plugin_dir . 'includes/class-fito-tryon-database.php';
    }
    if (!class_exists('Fito_Tryon_Storage')) {
        require_once $plugin_dir . 'includes/class-fito-tryon-storage.php';
    }
    if (!class_exists('Fito_Tryon_Privacy')) {
        require_once $plugin_dir . 'includes/class-fito-tryon-privacy.php';
    }

    if (is_callable(['Fito_Tryon_Database', 'create_tables'])) {
        Fito_Tryon_Database::create_tables();
    }
    if (is_callable(['Fito_Tryon_Storage', 'setup_storage_directory'])) {
        Fito_Tryon_Storage::setup_storage_directory();
    }
    if (is_callable(['Fito_Tryon_Privacy', 'schedule_cleanup_cron'])) {
        Fito_Tryon_Privacy::schedule_cleanup_cron();
    }
});

/**
 * Plugin deactivation handler.
 */
register_deactivation_hook(__FILE__, function () {
    require_once FITO_TRYON_PATH . 'includes/class-fito-tryon-privacy.php';
    Fito_Tryon_Privacy::clear_cleanup_cron();
});
