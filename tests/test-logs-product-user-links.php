<?php
/**
 * Automated Unit Tests for Audit Logs:
 * 1. Product column contains links to Edit (wp-admin edit post) and View (frontend permalink).
 * 2. Product #0 is NEVER displayed in table or modal markup.
 * 3. Logged in users have links to user-edit.php in User column and modal.
 * 4. Guest users display "Guest" cleanly without broken links.
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

echo "=== Running Audit Logs Product & User Links Tests ===\n";

global $wpdb;
$plugin = Fito_Tryon::instance();
if ($plugin->admin === null) {
    $plugin->admin = new Fito_Tryon_Admin($plugin);
}
$admin = $plugin->admin;
$db = $plugin->db;
$table = $wpdb->prefix . 'fito_tryon_logs';

// 1. Setup test data: a test product, a test user, and log entries
$test_user_id = wp_create_user('fitologtestuser', 'Password123!', 'fitologtest@example.com');
if (is_wp_error($test_user_id)) {
    $existing = get_user_by('login', 'fitologtestuser');
    $test_user_id = $existing ? $existing->ID : 1;
}

$test_product = new WC_Product_Simple();
$test_product->set_name('Vintage Silk Kimono');
$test_product->set_regular_price('79.00');
$test_product_id = $test_product->save();

// Insert test log 1: with real product & real user
$log_id_1 = $db->add_log([
    'job_id'       => 88801,
    'user_id'      => $test_user_id,
    'ip_address'   => '127.0.0.1',
    'product_id'   => $test_product_id,
    'model_used'   => 'google/gemini-2.5-flash-image',
    'status'       => 'success',
    'duration_sec' => 3.5,
]);

// Insert test log 2: with product_id = 0 & user_id = 0 (Guest)
$log_id_2 = $db->add_log([
    'job_id'       => 88802,
    'user_id'      => 0,
    'ip_address'   => '192.168.1.50',
    'product_id'   => 0,
    'model_used'   => 'google/gemini-2.5-flash-image',
    'status'       => 'failed',
    'error_message' => 'Zero product failure',
]);

// Insert test log 3: with deleted/non-existent product_id = 99999999 & guest
$log_id_3 = $db->add_log([
    'job_id'       => 88803,
    'user_id'      => 0,
    'ip_address'   => '192.168.1.51',
    'product_id'   => 99999999,
    'model_used'   => 'google/gemini-2.5-flash-image',
    'status'       => 'success',
]);

// 2. Render logs page and capture output
wp_set_current_user($test_user_id);
// Grant edit_users cap temporarily to test link rendering
$user_obj = get_userdata($test_user_id);
$user_obj->add_cap('edit_users');

ob_start();
$admin->render_logs_page();
$output = ob_get_clean();

// 3. Assertions
echo "- Checking table columns (User and Product)...\n";
assert(str_contains($output, '<th>User</th>'), "Failed: Table header missing User column");
assert(str_contains($output, '<th>Product</th>'), "Failed: Table header missing Product column");

echo "- Checking NO 'Product #0' appears anywhere in output...\n";
assert(!str_contains($output, 'Product #0'), "Failed: 'Product #0' found in output!");

echo "- Checking frontend View link and wp-admin Edit link for real product...\n";
$expected_edit_url = get_edit_post_link($test_product_id);
$expected_view_url = get_permalink($test_product_id);
assert(str_contains($output, $expected_edit_url), "Failed: Product edit link not found in output");
assert(str_contains($output, $expected_view_url), "Failed: Product frontend view link not found in output");
assert(str_contains($output, 'Vintage Silk Kimono'), "Failed: Product name not rendered");
assert(str_contains($output, 'fito-action-view'), "Failed: View action class missing");

echo "- Checking User profile link for logged in user...\n";
$expected_user_url = get_edit_user_link($test_user_id);
assert(str_contains($output, $expected_user_url), "Failed: User edit link not found in table");
assert(str_contains($output, 'fitologtestuser'), "Failed: User login name not found in table");

echo "- Checking Guest display for guest logs...\n";
assert(str_contains($output, '<span class="fito-muted">Guest</span>'), "Failed: Guest label not found for guest log");

echo "- Checking deleted product fallback...\n";
assert(str_contains($output, 'Deleted Product (ID 99999999)'), "Failed: Deleted product fallback not rendered properly");

echo "- Checking zero product fallback...\n";
assert(str_contains($output, 'General / No Product'), "Failed: Zero product fallback not rendered properly");

echo "- Checking inspect button data attributes for User & Product links...\n";
assert(str_contains($output, 'data-user-name="'), "Failed: data-user-name missing from inspect button");
assert(str_contains($output, 'data-prod-view="'), "Failed: data-prod-view missing from inspect button");
assert(str_contains($output, 'data-prod-edit="'), "Failed: data-prod-edit missing from inspect button");

// 4. Cleanup test data
wp_delete_post($test_product_id, true);
$wpdb->delete($table, ['id' => $log_id_1]);
$wpdb->delete($table, ['id' => $log_id_2]);
$wpdb->delete($table, ['id' => $log_id_3]);

echo "\n[PASS] All Audit Logs Product & User Links Tests Completed Successfully!\n";
