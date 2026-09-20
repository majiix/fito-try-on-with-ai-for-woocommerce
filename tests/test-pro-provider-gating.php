<?php
/**
 * Automated Unit Tests for Pro Provider & Fallback Model Gating
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Pro Provider & Fallback Model Gating Tests ===\n";

require_once dirname(__DIR__) . '/fito-try-on-with-ai-for-woocommerce.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

$plugin = Fito_Tryon::instance();
$admin  = new Fito_Tryon_Admin($plugin);

// Ensure admin capability
wp_set_current_user(1);

// Clean up filters on start
remove_all_filters('fito_tryon_is_pro_active');

// ==========================================
// Test 1: get_active_provider() when Pro is Inactive
// ==========================================
add_filter('fito_tryon_is_pro_active', '__return_false');
assert(Fito_Tryon::is_pro_active() === false, "Assertion failed: Pro should be inactive");

update_option('fito_tryon_ai_provider', 'gemini');
$active_provider = $plugin->get_active_provider();
assert($active_provider === 'openrouter', "Failed: gemini should normalize to openrouter when Pro is inactive. Got: $active_provider");

update_option('fito_tryon_ai_provider', 'openai');
$active_provider = $plugin->get_active_provider();
assert($active_provider === 'openrouter', "Failed: openai should normalize to openrouter when Pro is inactive. Got: $active_provider");

update_option('fito_tryon_ai_provider', 'openrouter');
$active_provider = $plugin->get_active_provider();
assert($active_provider === 'openrouter', "Failed: openrouter should remain openrouter");
echo "- Testing get_active_provider normalization (Free tier)... PASSED\n";

// ==========================================
// Test 2: get_active_provider() when Pro is Active
// ==========================================
remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_true');
assert(Fito_Tryon::is_pro_active() === true, "Assertion failed: Pro should be active");

update_option('fito_tryon_ai_provider', 'gemini');
$active_provider = $plugin->get_active_provider();
assert($active_provider === 'gemini', "Failed: gemini should be allowed when Pro is active. Got: $active_provider");

update_option('fito_tryon_ai_provider', 'openai');
$active_provider = $plugin->get_active_provider();
assert($active_provider === 'openai', "Failed: openai should be allowed when Pro is active. Got: $active_provider");
echo "- Testing get_active_provider direct APIs (Pro tier)... PASSED\n";

// ==========================================
// Test 3: get_fallback_model() when Pro is Inactive vs Active
// ==========================================
remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_false');

update_option('fito_tryon_fallback_model', 'custom-fallback-model');
update_option('fito_tryon_gemini_fallback_model', 'gemini-fallback');
update_option('fito_tryon_openai_fallback_model', 'openai-fallback');

assert($plugin->openrouter->get_fallback_model() === '', "Failed: OpenRouter fallback model should be empty string when Pro is inactive");
assert($plugin->gemini->get_fallback_model() === '', "Failed: Gemini fallback model should be empty string when Pro is inactive");
assert($plugin->openai->get_fallback_model() === '', "Failed: OpenAI fallback model should be empty string when Pro is inactive");
echo "- Testing get_fallback_model disabled (Free tier)... PASSED\n";

remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_true');

assert($plugin->openrouter->get_fallback_model() === 'custom-fallback-model', "Failed: OpenRouter fallback model should return configured string when Pro is active");
assert($plugin->gemini->get_fallback_model() === 'gemini-fallback', "Failed: Gemini fallback model should return configured string when Pro is active");
assert($plugin->openai->get_fallback_model() === 'openai-fallback', "Failed: OpenAI fallback model should return configured string when Pro is active");
echo "- Testing get_fallback_model enabled (Pro tier)... PASSED\n";

// ==========================================
// Test 4: Server-Side POST Gating in handle_save_settings()
// ==========================================
remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_false');

// Simulate POST attempt by unauthorized client
$_POST = [
    'fito_tryon_ai_provider'          => 'gemini',
    'fito_tryon_fallback_model'       => 'hacked-fallback',
    'fito_tryon_gemini_api_key'       => 'fake-gemini-key',
    'fito_tryon_openai_api_key'       => 'fake-openai-key',
];

// Re-set default options to verify they don't change
update_option('fito_tryon_ai_provider', 'openrouter');
update_option('fito_tryon_fallback_model', 'original-fallback');
update_option('fito_tryon_gemini_api_key', 'original-gemini-key');
update_option('fito_tryon_openai_api_key', 'original-openai-key');

// Nonce and Request Setup
$_POST['_wpnonce']    = wp_create_nonce('fito_tryon_save_settings_nonce');
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

add_filter('wp_redirect', function ($location) {
    throw new Exception("Redirect: $location");
});

try {
    $admin->handle_save_settings();
} catch (Exception $e) {
    // Caught redirect exception
}

remove_all_filters('wp_redirect');

assert(get_option('fito_tryon_ai_provider') === 'openrouter', "Failed: Unauthorized POST must not set provider to gemini when Pro is inactive");
assert(get_option('fito_tryon_fallback_model') === 'original-fallback', "Failed: Unauthorized POST must not update fallback model when Pro is inactive");
assert(get_option('fito_tryon_gemini_api_key') === 'original-gemini-key', "Failed: Unauthorized POST must not update gemini api key when Pro is inactive");
assert(get_option('fito_tryon_openai_api_key') === 'original-openai-key', "Failed: Unauthorized POST must not update openai api key when Pro is inactive");
echo "- Testing Server-Side POST Gating when Pro is Inactive... PASSED\n";

// ==========================================
// Test 5: UI Elements & Go Pro Page Badges
// ==========================================
ob_start();
$admin->render_settings_page();
$settings_html = ob_get_clean();

assert(str_contains($settings_html, 'pro-feature-direct-apis'), "Failed: pro-feature-direct-apis badge missing from free settings page");
assert(str_contains($settings_html, 'pro-feature-fallback-model'), "Failed: pro-feature-fallback-model badge missing from free settings page");
echo "- Testing Settings Page Pro Showcase Badges... PASSED\n";

ob_start();
$admin->render_go_pro_page();
$go_pro_html = ob_get_clean();

assert(str_contains($go_pro_html, 'id="pro-feature-direct-apis"'), "Failed: id=\"pro-feature-direct-apis\" missing from Go Pro page");
assert(str_contains($go_pro_html, 'id="pro-feature-fallback-model"'), "Failed: id=\"pro-feature-fallback-model\" missing from Go Pro page");
assert(str_contains($go_pro_html, 'Automatic Fallback Model Routing'), "Failed: Comparison table missing Fallback Model row");
echo "- Testing Go Pro Page Feature Cards & Anchors... PASSED\n";

// ==========================================
// Test 6: Em Dash Guardrail
// ==========================================
$files_to_check = [
    dirname(__DIR__) . '/includes/class-fito-tryon.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-openrouter.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-gemini.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-openai.php',
    dirname(__DIR__) . '/admin/class-fito-tryon-admin.php',
];

foreach ($files_to_check as $file_path) {
    $content = file_get_contents($file_path);
    assert(!str_contains($content, "\xE2\x80\x94"), "Failed: Em dash found in $file_path");
}
echo "- Testing Em Dash guardrail across modified files... PASSED\n";

// Cleanup options
update_option('fito_tryon_ai_provider', 'openrouter');
remove_all_filters('fito_tryon_is_pro_active');

echo "\n[PASS] All Pro Provider & Fallback Model Gating Tests Completed Successfully!\n";
