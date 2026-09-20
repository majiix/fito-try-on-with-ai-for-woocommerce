<?php
/**
 * Automated Unit Tests for Adaptive Rate Limiting (User Account vs. Multi-Device Guest Cookie)
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Adaptive Rate Limiting Tests ===\n";

$plugin = Fito_Tryon::instance();
$limiter = $plugin->rate_limiter;

// Helper to clear test transients
function clear_test_keys(Fito_Tryon_Rate_Limiter $limiter, array $user_ids, array $device_ids, string $period): void {
    foreach ($user_ids as $uid) {
        delete_transient($limiter->get_user_transient_key($uid, $period));
    }
    foreach ($device_ids as $did) {
        delete_transient($limiter->get_device_transient_key($did, $period));
    }
    delete_transient($limiter->get_ip_transient_key($period));
}

// Test 1: Logged-in only mode (Per User Account)
update_option('fito_tryon_rate_limit_enabled', 'yes');
update_option('fito_tryon_rate_limit_count', 2);
update_option('fito_tryon_rate_limit_period', 'hour');
update_option('fito_tryon_access_level', 'logged_in_only');

$test_user = 99123;
clear_test_keys($limiter, [$test_user], [], 'hour');

// Check initial
$res1 = $limiter->check_limit($test_user);
assert($res1['allowed'] === true, "Failed: User should be allowed on initial request");
assert($res1['remaining'] === 2, "Failed: Remaining should be 2");

// Consume 1
$limiter->increment_limit($test_user);
$res2 = $limiter->check_limit($test_user);
assert($res2['allowed'] === true && $res2['remaining'] === 1, "Failed: Remaining should be 1 after 1 increment");

// Consume 2 (reaches limit)
$limiter->increment_limit($test_user);
$res3 = $limiter->check_limit($test_user);
assert($res3['allowed'] === false, "Failed: User should be blocked after 2 increments");
assert($res3['remaining'] === 0, "Failed: Remaining should be 0");
assert(str_contains($res3['message'], 'per user account'), "Failed: Message should mention 'per user account'");

echo "- Testing Logged-In User Account Rate Limiting... PASSED\n";

// Test 2: Allow Everyone mode (Multi-device guest cookie on same IP)
update_option('fito_tryon_access_level', 'everyone');
update_option('fito_tryon_rate_limit_count', 2);

$dev_phone = 'dev_phone_abc123';
$dev_laptop = 'dev_laptop_xyz789';
clear_test_keys($limiter, [], [$dev_phone, $dev_laptop], 'hour');

// Phone consumes both allowed try-ons
$limiter->increment_limit(0, $dev_phone);
$limiter->increment_limit(0, $dev_phone);

$check_phone = $limiter->check_limit(0, $dev_phone);
assert($check_phone['allowed'] === false, "Failed: Phone should be rate limited after 2 try-ons");
assert(str_contains($check_phone['message'], 'per browser device'), "Failed: Message should mention 'per browser device'");

// Laptop on the SAME IP still has its own independent allowance!
$check_laptop = $limiter->check_limit(0, $dev_laptop);
assert($check_laptop['allowed'] === true, "Failed: Laptop on same home IP should still be allowed");
assert($check_laptop['remaining'] === 2, "Failed: Laptop should have 2 remaining");

echo "- Testing Multi-Device Guest Isolation on Shared Home IP... PASSED\n";

// Test 3: Shared IP Safety Ceiling
// Safety ceiling is max(15, count * 3) = 15 for count=2
// Let's set IP transient to 15
$ip_key = $limiter->get_ip_transient_key('hour');
set_transient($ip_key, 15, HOUR_IN_SECONDS);

$dev_tablet = 'dev_tablet_new999';
$check_ceiling = $limiter->check_limit(0, $dev_tablet);
assert($check_ceiling['allowed'] === false, "Failed: IP safety ceiling should block excessive IP usage");
assert(str_contains($check_ceiling['message'], 'Network try-on limit reached'), "Failed: Should show network safety message");

echo "- Testing Shared IP Safety Ceiling... PASSED\n";

// Cleanup
clear_test_keys($limiter, [$test_user], [$dev_phone, $dev_laptop, $dev_tablet], 'hour');
update_option('fito_tryon_rate_limit_count', 10000);
update_option('fito_tryon_access_level', 'everyone');

echo "\n[PASS] All Adaptive Rate Limiting Tests Completed Successfully!\n";
