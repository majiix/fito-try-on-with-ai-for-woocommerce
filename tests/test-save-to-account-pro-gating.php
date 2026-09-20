<?php
/**
 * Automated Unit Tests for Save to Account Pro Gating & Setting
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-public.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-rest.php';

echo "=== Running Save to Account Pro Gating Tests ===\n";

$plugin = Fito_Tryon::instance();
if ($plugin->admin === null) {
    $plugin->admin = new Fito_Tryon_Admin($plugin);
}
if ($plugin->public === null) {
    $plugin->public = new Fito_Tryon_Public($plugin);
}
if ($plugin->rest === null) {
    $plugin->rest = new Fito_Tryon_REST($plugin);
}

// Log in as test admin
wp_set_current_user(1);
$current_user = wp_get_current_user();
$current_user->add_cap('manage_options');
$current_user->add_cap('manage_woocommerce');

// 1. Free Tier Tests (Pro Deactivated)
echo "- Testing Free Tier Behavior (Pro Deactivated)...\n";
deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
assert(!Fito_Tryon::is_pro_active(), "Failed: Pro should be inactive");

// Free Tier: Modal markup must NOT contain Save to account button
ob_start();
$plugin->public->render_modal_markup();
$free_modal_html = ob_get_clean();
assert(!str_contains($free_modal_html, 'id="fito-btn-save-account"'), "Failed: Free tier modal should not render Save to account button");

// Free Tier: WooCommerce My Account menu items must NOT contain 'fito-tryons'
$original_menu = ['dashboard' => 'Dashboard', 'orders' => 'Orders', 'edit-account' => 'Account Details'];
$filtered_menu = $plugin->public->add_account_menu_item($original_menu);
assert(!isset($filtered_menu['fito-tryons']), "Failed: Free tier should not register My Try-Ons account menu item");

// Free Tier: render_account_tryons should show unavailable message
ob_start();
$plugin->public->render_account_tryons();
$free_acct_html = ob_get_clean();
assert(str_contains($free_acct_html, 'This feature is currently unavailable'), "Failed: Free tier should display unavailable notice for account try-ons");

// Free Tier: REST endpoint /save-account must return 403 Forbidden
$request = new WP_REST_Request('POST', '/ai-tryon/v1/save-account');
$request->set_param('token', 'dummy_token_123');
$response = $plugin->rest->handle_save_account($request);
assert($response->get_status() === 403, "Failed: Free tier /save-account REST request must return 403 Forbidden");
$response_data = $response->get_data();
assert(str_contains($response_data['message'], 'Pro Addon'), "Failed: Response should mention Pro Addon requirement");

// Free Tier: Settings page rendering
ob_start();
$plugin->admin->render_settings_page();
$free_settings_html = ob_get_clean();
assert(str_contains($free_settings_html, 'id="pro-feature-save-account"'), "Failed: Free tier settings should link to pro-feature-save-account anchor");
assert(!str_contains($free_settings_html, 'name="fito_tryon_save_to_account"'), "Failed: Free tier settings should not render editable checkbox for save_to_account");

// 2. Pro Tier Tests (Pro Activated)
echo "- Testing Pro Tier Behavior (Pro Activated)...\n";
activate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
assert(Fito_Tryon::is_pro_active(), "Failed: Pro should be active");

// Pro Tier: Enabled ('yes')
update_option('fito_tryon_save_to_account', 'yes');

ob_start();
$plugin->public->render_modal_markup();
$pro_enabled_modal = ob_get_clean();
assert(str_contains($pro_enabled_modal, 'id="fito-btn-save-account"'), "Failed: Pro tier enabled should render Save to account button in modal");

$pro_enabled_menu = $plugin->public->add_account_menu_item($original_menu);
assert(isset($pro_enabled_menu['fito-tryons']), "Failed: Pro tier enabled should add My Try-Ons account menu item");

ob_start();
$plugin->admin->render_settings_page();
$pro_settings_html = ob_get_clean();
assert(str_contains($pro_settings_html, 'name="fito_tryon_save_to_account"'), "Failed: Pro tier settings must render save_to_account checkbox");

// Pro Tier: Disabled ('no')
echo "- Testing Pro Tier Disabled Setting ('no')...\n";
update_option('fito_tryon_save_to_account', 'no');

ob_start();
$plugin->public->render_modal_markup();
$pro_disabled_modal = ob_get_clean();
assert(!str_contains($pro_disabled_modal, 'id="fito-btn-save-account"'), "Failed: Pro tier disabled should NOT render Save to account button");

$pro_disabled_menu = $plugin->public->add_account_menu_item($original_menu);
assert(!isset($pro_disabled_menu['fito-tryons']), "Failed: Pro tier disabled should NOT add My Try-Ons account menu item");

$disabled_response = $plugin->rest->handle_save_account($request);
assert($disabled_response->get_status() === 403, "Failed: Pro tier disabled /save-account REST request must return 403 Forbidden");

// Restore option to default 'yes'
update_option('fito_tryon_save_to_account', 'yes');

// 3. Go Pro Page Table & Detail Card
echo "- Testing Go Pro Page Comparison Table & Anchor Card...\n";
ob_start();
$plugin->admin->render_go_pro_page();
$go_pro_html = ob_get_clean();

assert(str_contains($go_pro_html, 'Customer Account Try-On History'), "Failed: Comparison table missing Customer Account Try-On History row");
assert(str_contains($go_pro_html, 'id="pro-feature-save-account"'), "Failed: Missing #pro-feature-save-account anchor card on Go Pro page");

// 4. Em Dash Guardrail Verification
echo "- Checking Em Dash Guardrail...\n";
$pattern = '/\x{2014}/u';
assert(!preg_match($pattern, $free_modal_html), "Em dash found in free modal HTML");
assert(!preg_match($pattern, $free_settings_html), "Em dash found in free settings HTML");
assert(!preg_match($pattern, $pro_settings_html), "Em dash found in pro settings HTML");
assert(!preg_match($pattern, $go_pro_html), "Em dash found in go pro page HTML");

// Clean up: restore Pro to inactive
deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');

echo "\n[PASS] All Save to Account Pro Gating Tests Completed Successfully!\n";
