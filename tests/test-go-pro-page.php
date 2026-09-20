<?php
/**
 * Automated Unit Tests for Go Pro Submenu and Showcase Page
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Go Pro Page & Badges Tests ===\n";

require_once dirname(__DIR__) . '/fito-try-on-with-ai-for-woocommerce.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

$admin = new Fito_Tryon_Admin(Fito_Tryon::instance());

// Ensure admin user capability is present for testing
$admin_user_id = 1;
wp_set_current_user($admin_user_id);

// Test 1: Render Go Pro Page Output
ob_start();
$admin->render_go_pro_page();
$output = ob_get_clean();

assert(is_string($output) && strlen($output) > 500, "Failed: render_go_pro_page produced empty output");
echo "- Testing render_go_pro_page output generation... PASSED (" . strlen($output) . " bytes)\n";

// Test 2: Verify Comparison Table is Present
assert(str_contains($output, 'fito-pro-comparison-table'), "Failed: Comparison table class not found");
assert(str_contains($output, 'Free vs Pro Feature Comparison'), "Failed: Comparison table heading not found");
echo "- Testing Free vs Pro Comparison Table... PASSED\n";

// Test 3: Verify Buy Link
assert(str_contains($output, 'https://violo.ir/?p=721'), "Failed: Official purchase link violo.ir/?p=721 not found");
echo "- Testing Official Purchase Link (https://violo.ir/?p=721)... PASSED\n";

// Test 4: Verify Anchor IDs for all 15 Verified Pro Features
$required_anchors = [
    'pro-feature-direct-apis',
    'pro-feature-fallback-model',
    'pro-feature-caching',
    'pro-feature-button-text',
    'pro-feature-archive-loops',
    'pro-feature-gallery-images',
    'pro-feature-variations',
    'pro-feature-custom-selectors',
    'pro-feature-save-account',
    'pro-feature-sample-image',
    'pro-feature-watermark',
    'pro-feature-log-images',
    'pro-feature-retention',
    'pro-feature-custom-prompts',
    'pro-feature-support',
];

foreach ($required_anchors as $anchor) {
    assert(str_contains($output, 'id="' . $anchor . '"'), "Failed: Anchor ID '$anchor' not found in Go Pro page");
}
echo "- Testing all 15 verified Pro feature anchor targets... PASSED\n";

// Test 4b: Verify Non-Existent Features are Purged
$purged_anchors = [
    'pro-feature-analytics',
    'pro-feature-quotas',
    'pro-feature-bulk',
];

foreach ($purged_anchors as $anchor) {
    assert(!str_contains($output, 'id="' . $anchor . '"'), "Failed: Non-existent feature '$anchor' should be purged from Go Pro page");
}
assert(!str_contains($output, 'WP-CLI'), "Failed: Non-existent WP-CLI reference found in Go Pro page");
assert(!str_contains($output, 'User Role Quotas'), "Failed: Non-existent User Role Quotas reference found in Go Pro page");
assert(!str_contains($output, 'Conversion & Funnel Analytics'), "Failed: Non-existent Funnel Analytics reference found in Go Pro page");
echo "- Testing non-existent features (WP-CLI, Quotas, Funnel Analytics) are purged... PASSED\n";

// Test 4c: Verify Comparison Table Section Headings
$required_headings = [
    'AI Engine & Performance',
    'Storefront, Widget & Placement Controls',
    'Customer Account & Guest Experience',
    'Watermark & Store Branding',
    'Abuse Prevention, Privacy & Logs',
    'AI Prompts & Category Profiles',
    'Updates & Priority Support',
];

foreach ($required_headings as $heading) {
    assert(str_contains($output, $heading), "Failed: Table section heading '$heading' not found in comparison table");
}
echo "- Testing all 7 comparison table section categories... PASSED\n";

// Test 5: Verify Settings Page Card 2 Badge Links
ob_start();
$admin->render_settings_page();
$settings_output = ob_get_clean();

$settings_pro_badges = [
    'admin.php?page=fito-tryon-pro#pro-feature-button-text',
    'admin.php?page=fito-tryon-pro#pro-feature-archive-loops',
    'admin.php?page=fito-tryon-pro#pro-feature-gallery-images',
    'admin.php?page=fito-tryon-pro#pro-feature-variations',
    'admin.php?page=fito-tryon-pro#pro-feature-custom-selectors',
];

foreach ($settings_pro_badges as $badge_link) {
    assert(str_contains($settings_output, $badge_link), "Failed: Badge link to '$badge_link' not found in settings page");
}
echo "- Testing Settings Page Card 2 Pro Badge deep links... PASSED\n";

// Test 6: Verify Plugin Action Links include Go Pro when Free
$links = Fito_Tryon::instance()->add_action_links([]);
assert(isset($links['go_pro']), "Failed: Go Pro link missing from plugin action links in Free mode");
assert(str_contains($links['go_pro'], 'page=fito-tryon-pro'), "Failed: Plugin action link does not point to fito-tryon-pro");
echo "- Testing Plugin Action Links Go Pro entry in Free mode... PASSED\n";

// Test 7: Verify Go Pro Submenu is registered when Pro is INACTIVE
global $submenu;
$submenu = [];
add_filter('fito_tryon_is_pro_active', '__return_false');
$admin->register_admin_menus();
$registered_slugs = isset($submenu['fito-tryon']) ? array_column($submenu['fito-tryon'], 2) : [];
assert(in_array('fito-tryon-pro', $registered_slugs, true), "Failed: fito-tryon-pro should be registered in submenu when Pro is inactive");
echo "- Testing Go Pro submenu is present when Pro is inactive... PASSED\n";

// Test 8: Verify Go Pro Submenu is NOT registered when Pro is ACTIVE
$submenu = [];
add_filter('fito_tryon_is_pro_active', '__return_true');
$admin->register_admin_menus();
$registered_slugs = isset($submenu['fito-tryon']) ? array_column($submenu['fito-tryon'], 2) : [];
assert(!in_array('fito-tryon-pro', $registered_slugs, true), "Failed: fito-tryon-pro must NOT be registered in submenu when Pro is active");
echo "- Testing Go Pro submenu is hidden when Pro is active... PASSED\n";

// Test 9: Verify Plugin Action Links omit Go Pro when Pro is ACTIVE
$links_pro = Fito_Tryon::instance()->add_action_links([]);
assert(!isset($links_pro['go_pro']), "Failed: Go Pro link must NOT appear in plugin action links when Pro is active");
echo "- Testing Plugin Action Links Go Pro entry omitted when Pro is active... PASSED\n";

remove_filter('fito_tryon_is_pro_active', '__return_true');
remove_filter('fito_tryon_is_pro_active', '__return_false');

// Test 10: Verify no em dashes in files
$files_to_check = [
    dirname(__DIR__) . '/admin/class-fito-tryon-admin.php',
    dirname(__DIR__) . '/includes/class-fito-tryon.php',
    dirname(__DIR__) . '/assets/css/admin.css',
];

foreach ($files_to_check as $file_path) {
    $content = file_get_contents($file_path);
    assert(!str_contains($content, "\xE2\x80\x94"), "Failed: Em dash found in $file_path");
}
echo "- Testing Em Dash guardrail across modified files... PASSED\n";

echo "\n[PASS] All Go Pro Page & Badge Tests Completed Successfully!\n";
