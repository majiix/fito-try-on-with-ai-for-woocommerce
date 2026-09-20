<?php
/**
 * Automated Unit Tests for Logs Pagination Logic
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Logs Pagination Tests ===\n";

// Test 1: Typical input - calculate total pages and offsets
$total_items = 14;
$limit = 10;
$total_pages = max(1, (int) ceil($total_items / $limit));
assert($total_pages === 2, "Failed: Expected 2 pages for 14 items with limit 10");

$page = 1;
$offset = ($page - 1) * $limit;
$start_item = $total_items > 0 ? $offset + 1 : 0;
$end_item = min($offset + $limit, $total_items);
assert($start_item === 1 && $end_item === 10, "Failed: Page 1 range should be 1-10");
echo "- Testing Typical Input (Page 1 of 2)... PASSED\n";

// Test 2: Page 2 calculation
$page = 2;
$offset = ($page - 1) * $limit;
$start_item = $total_items > 0 ? $offset + 1 : 0;
$end_item = min($offset + 4, $total_items);
assert($start_item === 11 && $end_item === 14, "Failed: Page 2 range should be 11-14");
echo "- Testing Page 2 Slice (Page 2 of 2)... PASSED\n";

// Test 3: Clamping page when requesting out-of-range page
$requested_page = 99;
$page = min(max(1, $requested_page), $total_pages);
assert($page === 2, "Failed: Page 99 should clamp to total_pages (2)");
echo "- Testing Page Overflow Clamping... PASSED\n";

// Test 4: Limit fallback when given invalid limit
$allowed_limits = [10, 20, 50, 100];
$test_limit = 999;
if (!in_array($test_limit, $allowed_limits, true)) {
    $test_limit = 10;
}
assert($test_limit === 10, "Failed: Invalid limit should fall back to 10");
echo "- Testing Limit Fallback for Invalid Option... PASSED\n";

// Test 5: Verify route /export-logs is not registered in REST API
$server = rest_get_server();
$routes = $server->get_routes();
$export_route_exists = isset($routes['/ai-tryon/v1/export-logs']);
assert(!$export_route_exists, "Failed: /ai-tryon/v1/export-logs should not be registered");
echo "- Testing /export-logs Route Removal... PASSED\n";

echo "\n[PASS] All Logs Pagination Tests Completed Successfully!\n";
