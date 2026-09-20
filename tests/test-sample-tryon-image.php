<?php
/**
 * Automated Unit Tests for Guest Sample Try-On Image & Pro Gating
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

echo "=== Running Guest Sample Try-On Image Tests ===\n";

$plugin = Fito_Tryon::instance();
if ($plugin->admin === null) {
    $plugin->admin = new Fito_Tryon_Admin($plugin);
}
$public = $plugin->public;

// Test 1: Verify sample-tryon.jfif exists and is valid asset
$image_path = dirname(__DIR__) . '/assets/images/sample-tryon.jfif';
assert(file_exists($image_path), "Failed: sample-tryon.jfif must exist");
assert(filesize($image_path) > 1000, "Failed: sample-tryon.jfif must be a non-empty image asset");
echo "- Testing Built-in Sample Asset Existence & Size... PASSED\n";

// Test 2: Free Tier Behavior (Pro Deactivated)
deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
assert(!Fito_Tryon::is_pro_active(), "Failed: Pro should be inactive");

// Even if a custom ID is in DB, Free tier must ignore it and return default sample-tryon.jfif
update_option('fito_tryon_sample_image_id', 99999);
$method = new ReflectionMethod('Fito_Tryon_Public', 'get_login_config');
$method->setAccessible(true);
$login_config = $method->invoke($public);
assert(str_contains($login_config['sample_image'], 'sample-tryon.jfif'), "Failed: Free tier must always use default sample-tryon.jfif");

// Free tier admin settings UI verification
update_option('fito_tryon_access_level', 'logged_in_only');
ob_start();
$plugin->admin->render_settings_page();
$free_settings_html = ob_get_clean();

assert(!str_contains($free_settings_html, 'id="fito-btn-upload-sample-image"'), "Failed: Free tier settings must not render upload button");
assert(str_contains($free_settings_html, 'id="pro-feature-sample-image"'), "Failed: Free tier settings must link to pro-feature-sample-image");

echo "- Testing Free Tier Pro Gating & UI... PASSED\n";

// Test 3: Pro Tier Behavior (Pro Activated)
activate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
if (Fito_Tryon::is_pro_active()) {
    ob_start();
    $plugin->admin->render_settings_page();
    $pro_settings_html = ob_get_clean();

    assert(str_contains($pro_settings_html, 'id="fito-btn-upload-sample-image"'), "Failed: Pro tier settings must render upload button");
    echo "- Testing Pro Tier Admin Upload UI... PASSED\n";
    deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
}

// Reset option
update_option('fito_tryon_sample_image_id', 0);
update_option('fito_tryon_access_level', 'everyone');

// Test 4: Verify modal markup contains .fito-login-sample-card
ob_start();
$prop = new ReflectionProperty('Fito_Tryon_Public', 'modal_printed');
$prop->setAccessible(true);
$prop->setValue($public, false);
$public->render_modal_markup();
$modal_html = ob_get_clean();

assert(str_contains($modal_html, 'fito-login-sample-card'), "Failed: Modal markup must contain .fito-login-sample-card");
assert(str_contains($modal_html, 'fito-login-sample-badge'), "Failed: Modal markup must contain .fito-login-sample-badge");
assert(str_contains($modal_html, 'fito-btn--login'), "Failed: Modal markup must contain login CTA button");
echo "- Testing Modal Markup Generation with Sample Card... PASSED\n";

// Test 5: Go Pro Page Comparison Table & Anchor Card
ob_start();
$plugin->admin->render_go_pro_page();
$go_pro_html = ob_get_clean();

assert(str_contains($go_pro_html, 'Custom Guest Sample Try-On Image'), "Failed: Comparison table missing Custom Guest Sample Try-On Image row");
assert(str_contains($go_pro_html, 'id="pro-feature-sample-image"'), "Failed: Missing #pro-feature-sample-image anchor card on Go Pro page");
echo "- Testing Go Pro Page Comparison Table & Detail Card... PASSED\n";

// Test 6: Em Dash Guardrail
$pattern = '/\x{2014}/u';
assert(!preg_match($pattern, $free_settings_html), "Em dash found in free settings HTML");
assert(!preg_match($pattern, $go_pro_html), "Em dash found in go pro page HTML");
echo "- Testing Em Dash Guardrail... PASSED\n";

echo "\n[PASS] All Guest Sample Try-On Image Tests Completed Successfully!\n";
