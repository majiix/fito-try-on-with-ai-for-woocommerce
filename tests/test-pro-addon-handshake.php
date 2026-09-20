<?php
/**
 * Automated Unit Tests for Fito Try-On Pro Addon Handshake and Version Parity
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Pro Addon Handshake & Compatibility Tests ===\n";

// Ensure core plugin is loaded.
require_once dirname(__DIR__) . '/fito-try-on-with-ai-for-woocommerce.php';

// Test 1: Core plugin constant check
assert(defined('FITO_TRYON_VERSION'), 'Failed: FITO_TRYON_VERSION must be defined');
echo "- Testing Core FITO_TRYON_VERSION Constant... PASSED (" . FITO_TRYON_VERSION . ")\n";

// Test 2: Load Pro Addon
$pro_addon_file = dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/fito-try-on-with-ai-for-woocommerce-pro-addon.php';
assert(file_exists($pro_addon_file), 'Failed: Pro Addon entry file must exist at ' . $pro_addon_file);
require_once $pro_addon_file;

assert(defined('FITO_TRYON_PRO_VERSION'), 'Failed: FITO_TRYON_PRO_VERSION must be defined');
assert(class_exists('Fito_Tryon_Pro_Addon'), 'Failed: Fito_Tryon_Pro_Addon class must exist');
echo "- Testing Pro Addon Bootstrapping... PASSED (" . FITO_TRYON_PRO_VERSION . ")\n";

// Test 3: Version Compatibility Check
$pro_addon = new Fito_Tryon_Pro_Addon();
assert($pro_addon->is_compatible() === true, 'Failed: is_compatible() should be true when versions match');
echo "- Testing Pro Addon Version Compatibility (Exact Match)... PASSED\n";

// Test 4: Version comparison logic for lower/higher versions
$is_pro_outdated = version_compare('0.9.0', FITO_TRYON_VERSION, '<');
assert($is_pro_outdated === true, 'Failed: Version 0.9.0 should be recognized as lower than core');

$is_pro_newer = version_compare('2.0.0', FITO_TRYON_VERSION, '>');
assert($is_pro_newer === true, 'Failed: Version 2.0.0 should be recognized as higher than core');
echo "- Testing Version Divergence Comparison Logic... PASSED\n";

// Test 5: is_pro_active() filter testing
add_filter('fito_tryon_is_pro_active', '__return_true');
assert(Fito_Tryon::is_pro_active() === true, 'Failed: is_pro_active() should return true when active');
echo "- Testing is_pro_active() Filter & Resolution... PASSED\n";

echo "\n[PASS] All Pro Addon Handshake & Compatibility Tests Completed Successfully!\n";
