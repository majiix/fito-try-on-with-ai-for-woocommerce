<?php
/**
 * Automated Unit Tests for Database Log Retention
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Database Log Retention Tests ===\n";

global $wpdb;
$db = new Fito_Tryon_Database();
$storage = new Fito_Tryon_Storage();
$privacy = new Fito_Tryon_Privacy();
$table = $wpdb->prefix . 'fito_tryon_logs';

// Test 1: Whitelist validation
$allowed = ['none', '1', '3', '7', '30', 'never'];
$valid_tests = [
    ['none', 'none'],
    ['1', '1'],
    ['3', '3'],
    ['7', '7'],
    ['30', '30'],
    ['never', 'never'],
    ['invalid_99', '30'],
    ['', '30'],
];

foreach ($valid_tests as [$input, $expected]) {
    $sanitized = in_array((string) $input, $allowed, true) ? (string) $input : '30';
    assert($sanitized === $expected, "Validation failed for input: {$input}");
}
echo "- Testing Option Whitelist & Fallback... PASSED\n";

// Test 1b: "Do not log" option disables database logging
update_option('fito_tryon_log_retention', 'none');
$initial_log_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$res_log_id = $db->add_log([
    'job_id'       => 99999,
    'product_id'   => 1,
    'status'       => 'success',
    'duration_sec' => 1.2,
]);
assert($res_log_id === 0, "Failed: add_log should return 0 when log_retention is 'none'");
$new_log_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
assert($new_log_count === $initial_log_count, "Failed: No database row should be inserted when log_retention is 'none'");
update_option('fito_tryon_log_retention', '30');
echo "- Testing 'Do not log' (none) suppression in add_log... PASSED\n";

// Test 2: Database log expiration & cleanup
// Insert test logs with varying creation dates
$now = time();
$date_45d_ago = date('Y-m-d H:i:s', $now - (45 * DAY_IN_SECONDS));
$date_15d_ago = date('Y-m-d H:i:s', $now - (15 * DAY_IN_SECONDS));
$date_1d_ago  = date('Y-m-d H:i:s', $now - (1 * DAY_IN_SECONDS));

$wpdb->insert($table, [
    'job_id' => 99901,
    'product_id' => 1,
    'status' => 'success',
    'duration_sec' => 3.5,
    'cost_estimate' => 0.02,
    'created_at' => $date_45d_ago,
]);
$id_45 = $wpdb->insert_id;

$wpdb->insert($table, [
    'job_id' => 99902,
    'product_id' => 1,
    'status' => 'success',
    'duration_sec' => 4.1,
    'cost_estimate' => 0.02,
    'created_at' => $date_15d_ago,
]);
$id_15 = $wpdb->insert_id;

$wpdb->insert($table, [
    'job_id' => 99903,
    'product_id' => 1,
    'status' => 'success',
    'duration_sec' => 2.8,
    'cost_estimate' => 0.02,
    'created_at' => $date_1d_ago,
]);
$id_1 = $wpdb->insert_id;

// Verify get_expired_logs(30) returns the 45d log
$expired_30 = $db->get_expired_logs(30);
$expired_ids = array_map(fn($r) => (int) $r->id, $expired_30);
assert(in_array($id_45, $expired_ids, true), "Failed: 45d log should be in expired list for 30d retention");
assert(!in_array($id_15, $expired_ids, true), "Failed: 15d log should NOT be in expired list for 30d retention");
assert(!in_array($id_1, $expired_ids, true), "Failed: 1d log should NOT be in expired list for 30d retention");
echo "- Testing get_expired_logs(30)... PASSED\n";

// Execute delete_expired_logs(30)
$deleted_count = $db->delete_expired_logs(30);
assert($deleted_count >= 1, "Failed: At least 1 expired log should have been deleted");

// Verify that 45d log was deleted, but 15d and 1d logs remain
$check_45 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id_45));
$check_15 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id_15));
$check_1  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id_1));

assert((int) $check_45 === 0, "Failed: 45d log should be deleted");
assert((int) $check_15 === 1, "Failed: 15d log should remain");
assert((int) $check_1 === 1, "Failed: 1d log should remain");
echo "- Testing delete_expired_logs(30) selective deletion... PASSED\n";

// Test 3: Storage delete_file_by_url security and operation
Fito_Tryon_Storage::setup_storage_directory();
$test_filename = 'retention_test_' . wp_generate_password(8, false) . '.jpg';
$test_path = trailingslashit(Fito_Tryon_Storage::get_base_dir()) . 'results/' . $test_filename;
$test_url  = trailingslashit(Fito_Tryon_Storage::get_base_url()) . 'results/' . $test_filename;

file_put_contents($test_path, 'dummy test content');
assert(file_exists($test_path), "Failed: Test file could not be created");

$del_res = $storage->delete_file_by_url($test_url);
assert($del_res === true, "Failed: delete_file_by_url should return true");
assert(!file_exists($test_path), "Failed: File should be deleted from disk");

// Security check: URLs outside base directory should be rejected
$disallowed_url = 'https://example.com/wp-content/uploads/other-dir/secret.php';
assert($storage->delete_file_by_url($disallowed_url) === false, "Failed: Foreign URL must be rejected");
echo "- Testing delete_file_by_url with Path Protection... PASSED\n";

// Clean up remaining test logs
$wpdb->delete($table, ['id' => $id_15]);
$wpdb->delete($table, ['id' => $id_1]);

echo "\n[PASS] All Database Log Retention Tests Completed Successfully!\n";
