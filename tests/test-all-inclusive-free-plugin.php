<?php
/**
 * Test suite for all-inclusive free plugin features.
 * Verifies that all advanced features run natively out of the box in the standalone free plugin.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', 'e:/wps/dorsanet/app/public/');
}
if (!defined('FITO_TRYON_VERSION')) {
    define('FITO_TRYON_VERSION', '1.7.0');
}
if (!defined('FITO_TRYON_PATH')) {
    define('FITO_TRYON_PATH', dirname(__DIR__) . '/');
}
if (!defined('FITO_TRYON_URL')) {
    define('FITO_TRYON_URL', 'http://example.com/wp-content/plugins/elfit-ai-virtual-try-on-for-woocommerce/');
}
if (!defined('FITO_TRYON_BASENAME')) {
    define('FITO_TRYON_BASENAME', 'elfit-ai-virtual-try-on-for-woocommerce/elfit-ai-virtual-try-on-for-woocommerce.php');
}

// Minimal WordPress function stubs for unit testing.
if (!function_exists('add_filter')) {
    $GLOBALS['wp_filter'] = [];
    $GLOBALS['wp_actions'] = [];

    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_filter'][$tag][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }

    function apply_filters($tag, $value, ...$args) {
        if (!isset($GLOBALS['wp_filter'][$tag])) {
            return $value;
        }
        foreach ($GLOBALS['wp_filter'][$tag] as $filter) {
            $cb = $filter['callback'];
            $params = array_merge([$value], $args);
            $value = call_user_func_array($cb, array_slice($params, 0, $filter['accepted_args']));
        }
        return $value;
    }

    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($tag, $callback, $priority, $accepted_args);
    }

    function do_action($tag, ...$args) {
        $GLOBALS['wp_actions'][$tag] = ($GLOBALS['wp_actions'][$tag] ?? 0) + 1;
        if (!isset($GLOBALS['wp_filter'][$tag])) {
            return;
        }
        foreach ($GLOBALS['wp_filter'][$tag] as $filter) {
            call_user_func_array($filter['callback'], array_slice($args, 0, $filter['accepted_args']));
        }
    }

    function did_action($tag) {
        return $GLOBALS['wp_actions'][$tag] ?? 0;
    }
}

if (!function_exists('get_option')) {
    $GLOBALS['wp_options'] = [];
    function get_option($key, $default = false) {
        return $GLOBALS['wp_options'][$key] ?? $default;
    }
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['wp_options'][$key] = $value;
        return true;
    }
    function delete_option($key) {
        unset($GLOBALS['wp_options'][$key]);
        return true;
    }
}

if (!function_exists('set_transient')) {
    $GLOBALS['wp_transients'] = [];
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][$transient] = $value;
        return true;
    }
    function get_transient($transient) {
        return $GLOBALS['wp_transients'][$transient] ?? false;
    }
    function delete_transient($transient) {
        unset($GLOBALS['wp_transients'][$transient]);
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
    function esc_attr__($text, $domain = 'default') {
        return $text;
    }
    function esc_html_e($text, $domain = 'default') {
        echo $text;
    }
    function esc_attr_e($text, $domain = 'default') {
        echo $text;
    }
    function esc_html($text) {
        return $text;
    }
    function esc_attr($text) {
        return $text;
    }
    function esc_url($text) {
        return $text;
    }
    function esc_url_raw($text) {
        return $text;
    }
    function esc_textarea($text) {
        return $text;
    }
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
    function sanitize_text_field($str) {
        return trim(strip_tags((string)$str));
    }
    function sanitize_textarea_field($str) {
        return trim(strip_tags((string)$str));
    }
    function absint($val) {
        return abs((int)$val);
    }
    function is_wp_error($thing) {
        return false;
    }
    function is_admin() {
        return true;
    }
    function is_user_logged_in() {
        return true;
    }
    function get_current_user_id() {
        return 1;
    }
    function add_rewrite_endpoint($name, $places) {}
    function add_shortcode($tag, $callback) {}
    function add_menu_page($page_title, $menu_title, $cap, $menu_slug, $callback = '', $icon_url = '', $position = null) {}
    function register_rest_route($ns, $route, $args) {}
    function wp_upload_dir() {
        return [
            'basedir' => sys_get_temp_dir() . '/fito_test_uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }
    function trailingslashit($str) {
        return rtrim($str, '/\\') . '/';
    }
    function wp_mkdir_p($target) {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }
        return true;
    }
}

// Mock wpdb
if (!isset($GLOBALS['wpdb'])) {
    class Mock_WPDB {
        public $prefix = 'wp_';
        public $options = 'wp_options';
        public $last_query = '';
        public function esc_like($text) {
            return addcslashes($text, '_%\\');
        }
        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
        public function query($q) {
            $this->last_query = $q;
            $GLOBALS['wp_transients'] = [];
            return 1;
        }
        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d', '%f'], ["'%s'", '%d', '%f'], $query), $args);
        }
        public function get_var($query = null) {
            return 1;
        }
        public function get_results($query = null, $output = 'OBJECT') {
            return [];
        }
        public function update($table, $data, $where) {
            return 1;
        }
        public function delete($table, $where) {
            return 1;
        }
    }
    $GLOBALS['wpdb'] = new Mock_WPDB();
}

// Load core classes
require_once dirname(__DIR__) . '/includes/class-fito-tryon-database.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-storage.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-rate-limiter.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-privacy.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-openrouter.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-rest.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-public.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

echo "=== Running All-Inclusive Free Plugin Test Suite ===\n\n";

$tests_passed = 0;
$tests_total = 0;

function assert_test($condition, $description) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        $tests_passed++;
        echo "[PASS] " . $description . "\n";
    } else {
        echo "[FAIL] " . $description . "\n";
    }
}

// 1. Master Class & Component Instantiation
update_option('fito_tryon_db_version', '1.4.1');
$plugin = Fito_Tryon::instance();
$plugin->boot();
assert_test($plugin instanceof Fito_Tryon, "Fito_Tryon master singleton instantiates successfully");
assert_test($plugin->openrouter instanceof Fito_Tryon_OpenRouter, "Fito_Tryon::\$openrouter is an instance of Fito_Tryon_OpenRouter");
assert_test($plugin->public instanceof Fito_Tryon_Public, "Fito_Tryon::\$public is an instance of Fito_Tryon_Public");
assert_test($plugin->db instanceof Fito_Tryon_Database, "Fito_Tryon::\$db is an instance of Fito_Tryon_Database");
assert_test($plugin->storage instanceof Fito_Tryon_Storage, "Fito_Tryon::\$storage is an instance of Fito_Tryon_Storage");
assert_test($plugin->privacy instanceof Fito_Tryon_Privacy, "Fito_Tryon::\$privacy is an instance of Fito_Tryon_Privacy");
assert_test($plugin->rate_limiter instanceof Fito_Tryon_Rate_Limiter, "Fito_Tryon::\$rate_limiter is an instance of Fito_Tryon_Rate_Limiter");

// 2. AI Provider Settings & Client Resolution
$allowed_providers = $plugin->get_allowed_providers();
assert_test($allowed_providers === ['openrouter'], "Allowed providers contains exclusively 'openrouter'");
assert_test($plugin->get_active_provider() === 'openrouter', "Active provider returns 'openrouter'");
assert_test($plugin->get_provider_client('openrouter') instanceof Fito_Tryon_OpenRouter, "Provider client for 'openrouter' resolves correctly");
assert_test($plugin->get_provider_client('unknown') === null, "Provider client for unknown provider returns null");

// 3. OpenRouter Model Resolution
$openrouter = $plugin->openrouter;
assert_test(method_exists($openrouter, 'get_primary_model'), "Fito_Tryon_OpenRouter has get_primary_model() method");
assert_test($plugin->get_active_primary_model() === $openrouter->get_primary_model(), "Active primary model delegates to OpenRouter");

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// 4. Storefront Single Product Button & Appearance
$public = $plugin->public;
assert_test(method_exists($public, 'render_product_button'), "Fito_Tryon_Public has render_product_button() method");
assert_test(method_exists($public, 'render_block'), "Fito_Tryon_Public has render_block() method");

// 5. Database Schema & Data Model
assert_test(Fito_Tryon_Database::DB_VERSION === '1.4.1', "Database schema version is 1.4.1");
$db_file_content = file_get_contents(FITO_TRYON_PATH . 'includes/class-fito-tryon-database.php');
assert_test(!str_contains($db_file_content, 'session_id'), "Jobs table schema does not define unused session_id column");
assert_test(method_exists($plugin->db, 'create_job'), "Database has create_job() method");
assert_test(method_exists($plugin->db, 'get_job_by_token'), "Database has get_job_by_token() method");
assert_test(method_exists($plugin->db, 'update_job_by_token'), "Database has update_job_by_token() method");

// 6. Fixed 24h Photo Retention Policy
$privacy = $plugin->privacy;
assert_test($privacy->get_retention_hours() === 24, "Privacy retention window is fixed 24 hours");

// 7. Admin Submenu Configuration
$admin = $plugin->admin;
$registered_submenus = [];
function add_submenu_page($parent, $page_title, $menu_title, $cap, $menu_slug, $callback = '') {
    global $registered_submenus;
    $registered_submenus[] = $menu_slug;
}
$admin->register_admin_menus();
assert_test(in_array('fito-tryon-settings', $registered_submenus, true), "Admin submenus include 'fito-tryon-settings'");
assert_test(in_array('fito-tryon-prompts', $registered_submenus, true), "Admin submenus include 'fito-tryon-prompts'");
assert_test(in_array('fito-tryon-logs', $registered_submenus, true), "Admin submenus include 'fito-tryon-logs'");

// 8. Prompts 7 Built-in Presets
$default_prompts = Fito_Tryon_OpenRouter::get_default_prompts();
assert_test(count($default_prompts) === 7, "Default prompts contains 7 built-in system presets");

// 9. Storage & Clean Execution
$storage_content = file_get_contents(FITO_TRYON_PATH . 'includes/class-fito-tryon-storage.php');
assert_test(!str_contains($storage_content, 'fito_tryon_max_upload_size'), "Storage does not reference orphaned fito_tryon_max_upload_size option");
assert_test(!str_contains($storage_content, 'get_results_dir'), "Storage does not define dead get_results_dir() method");

// 10. In-Memory Guard on Uninstall
$uninstall_content = file_get_contents(FITO_TRYON_PATH . 'uninstall.php');
assert_test(str_contains($uninstall_content, "class_exists('Fito_Tryon')"), "uninstall.php aborts if another active instance is running in memory");
assert_test(!str_contains($uninstall_content, 'elfit-ai-virtual-try-on*'), "uninstall.php does not perform sister directory sniffing");
assert_test(!str_contains($uninstall_content, 'fito_tryon_ai_provider'), "uninstall.php does not reference deleted fito_tryon_ai_provider option");
assert_test(!str_contains($uninstall_content, 'fito_tryon_max_upload_size'), "uninstall.php does not reference deleted fito_tryon_max_upload_size option");

echo "\n=======================================================\n";
echo "Test Results: {$tests_passed} / {$tests_total} tests passed.\n";

if ($tests_passed === $tests_total) {
    echo "STATUS: ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED.\n";
    exit(1);
}
