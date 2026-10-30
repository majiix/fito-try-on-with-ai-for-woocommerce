<?php
/**
 * Elfit AI Virtual Try-On for WooCommerce - Uninstall Cleanup
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

/**
 * 1. Check if another edition of this plugin is present on the site.
 * If another edition is active in memory, registered in active plugins,
 * or present in the plugins directory, abort uninstallation immediately
 * to preserve all shared settings, database tables, and media files.
 */
$current_plugin_dir = basename(__DIR__);

// Check if another instance is already running in memory.
if (class_exists('Fito_Tryon') || defined('FITO_TRYON_VERSION')) {
    return;
}

// Check if another edition is active in the WordPress active plugins list.
$active_plugins = (array) get_option('active_plugins', []);
if (is_multisite()) {
    $active_plugins = array_merge($active_plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
}
foreach ($active_plugins as $active_plugin) {
    $plugin_dir = dirname($active_plugin);
    if ($plugin_dir !== '.' && $plugin_dir !== $current_plugin_dir && str_starts_with($plugin_dir, 'elfit-ai-virtual-try-on')) {
        return;
    }
}

// Check if another edition directory exists in the plugins directory.
if (defined('WP_PLUGIN_DIR')) {
    $sister_dirs = glob(WP_PLUGIN_DIR . '/elfit-ai-virtual-try-on*', GLOB_ONLYDIR);
    if (is_array($sister_dirs)) {
        foreach ($sister_dirs as $sister_dir) {
            if (basename($sister_dir) !== $current_plugin_dir) {
                return;
            }
        }
    }
}

// 2. Clear scheduled cron tasks
wp_unschedule_hook('fito_tryon_cleanup_cron');

// 3. Clean up transient caches
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_fito_') . '%',
        $wpdb->esc_like('_transient_timeout_fito_') . '%'
    )
);

// 4. Drop custom tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom table cleanup on uninstall.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}fito_tryon_jobs");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom table cleanup on uninstall.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}fito_tryon_logs");

// 5. Delete registered options
$fito_tryon_options = [
    'fito_tryon_ai_provider',
    'fito_tryon_openrouter_api_key',
    'fito_tryon_primary_model',
    'fito_tryon_timeout_seconds',
    'fito_tryon_button_text',
    'fito_tryon_button_position',
    'fito_tryon_system_prompt',
    'fito_tryon_prompts_config',
    'fito_tryon_db_version',
    'fito_tryon_rate_limit_enabled',
    'fito_tryon_rate_limit_count',
    'fito_tryon_rate_limit_period',
    'fito_tryon_rate_limit_hourly',
    'fito_tryon_log_retention',
    'fito_tryon_consent_text',
    'fito_tryon_access_level',
    'fito_tryon_login_url',
    'fito_tryon_login_notice',
    'fito_tryon_sample_image_id',
    'fito_tryon_allowed_categories',
    'fito_tryon_max_upload_size',
];

foreach ($fito_tryon_options as $fito_tryon_opt) {
    delete_option($fito_tryon_opt);
}

// 6. Clean up uploaded storage directory
$fito_tryon_upload_dir = wp_upload_dir();
$fito_tryon_storage_dir = trailingslashit($fito_tryon_upload_dir['basedir']) . 'fito-tryon';

global $wp_filesystem;
if (empty($wp_filesystem)) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
}

if ($wp_filesystem && $wp_filesystem->is_dir($fito_tryon_storage_dir)) {
    $wp_filesystem->delete($fito_tryon_storage_dir, true);
}
