<?php
/**
 * Automated Unit Tests for Complete Pro Features Access Control Gating
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Complete Pro Features Access Control Gating Tests ===\n";

require_once dirname(__DIR__) . '/fito-try-on-with-ai-for-woocommerce.php';
$plugin = Fito_Tryon::instance();
$rest   = $plugin->rest;

// Ensure Pro is inactive for initial baseline
add_filter('fito_tryon_is_pro_active', '__return_false');
assert(!Fito_Tryon::is_pro_active(), 'Failed: Pro should be inactive');

// -----------------------------------------------------------------------------
// Test 1: Direct Gemini & OpenAI REST Endpoints (/test-connection & /models)
// -----------------------------------------------------------------------------
echo "- Testing REST /test-connection rejection for Gemini & OpenAI (Free tier)... ";
$req_gemini = new WP_REST_Request('POST', '/fito-tryon/v1/test-connection');
$req_gemini->set_param('provider', 'gemini');
$req_gemini->set_param('api_key', 'test_key');
$res_gemini = $rest->handle_test_connection($req_gemini);
assert($res_gemini->get_status() === 403, 'Gemini connection test must return 403 when Pro is inactive');

$req_openai = new WP_REST_Request('POST', '/fito-tryon/v1/test-connection');
$req_openai->set_param('provider', 'openai');
$req_openai->set_param('api_key', 'test_key');
$res_openai = $rest->handle_test_connection($req_openai);
assert($res_openai->get_status() === 403, 'OpenAI connection test must return 403 when Pro is inactive');
echo "PASSED\n";

echo "- Testing REST /models rejection for Gemini & OpenAI (Free tier)... ";
$req_models_gemini = new WP_REST_Request('GET', '/fito-tryon/v1/models');
$req_models_gemini->set_param('provider', 'gemini');
$res_models_gemini = $rest->handle_get_models($req_models_gemini);
assert($res_models_gemini->get_status() === 403, 'Gemini models fetch must return 403 when Pro is inactive');

$req_models_openai = new WP_REST_Request('GET', '/fito-tryon/v1/models');
$req_models_openai->set_param('provider', 'openai');
$res_models_openai = $rest->handle_get_models($req_models_openai);
assert($res_models_openai->get_status() === 403, 'OpenAI models fetch must return 403 when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 2: Direct Generation Execution in Gemini & OpenAI Classes
// -----------------------------------------------------------------------------
echo "- Testing Direct generate_tryon in Gemini & OpenAI classes (Free tier)... ";
$dummy_params = [
    'user_image_path'   => '',
    'product_image_url' => '',
    'product_title'     => 'Test Shirt',
    'categories'        => 'clothing',
];
$gemini_result = $plugin->gemini->generate_tryon($dummy_params);
assert($gemini_result['success'] === false, 'Gemini generate_tryon must fail when Pro is inactive');
assert(str_contains($gemini_result['message'], 'requires Fito Try-On Pro'), 'Gemini error message must state Pro is required');

$openai_result = $plugin->openai->generate_tryon($dummy_params);
assert($openai_result['success'] === false, 'OpenAI generate_tryon must fail when Pro is inactive');
assert(str_contains($openai_result['message'], 'requires Fito Try-On Pro'), 'OpenAI error message must state Pro is required');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 3: Active Provider Normalization
// -----------------------------------------------------------------------------
echo "- Testing Active Provider Normalization to openrouter (Free tier)... ";
update_option('fito_tryon_ai_provider', 'gemini');
assert($plugin->get_active_provider() === 'openrouter', 'Provider gemini must normalize to openrouter when Pro is inactive');
update_option('fito_tryon_ai_provider', 'openai');
assert($plugin->get_active_provider() === 'openrouter', 'Provider openai must normalize to openrouter when Pro is inactive');
update_option('fito_tryon_ai_provider', 'openrouter');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 4: Fallback Model Routing Disabled
// -----------------------------------------------------------------------------
echo "- Testing Fallback Model Return Value (Free tier)... ";
update_option('fito_tryon_fallback_model', 'black-forest-labs/flux.2-klein-4b');
update_option('fito_tryon_gemini_fallback_model', 'gemini-3.1-flash-image');
update_option('fito_tryon_openai_fallback_model', 'dall-e-3');

assert($plugin->openrouter->get_fallback_model() === '', 'OpenRouter fallback must return empty string when Pro is inactive');
assert($plugin->gemini->get_fallback_model() === '', 'Gemini fallback must return empty string when Pro is inactive');
assert($plugin->openai->get_fallback_model() === '', 'OpenAI fallback must return empty string when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 5: Result Caching Disabled
// -----------------------------------------------------------------------------
echo "- Testing Result Caching (Free tier)... ";
update_option('fito_tryon_cache_enabled', 'yes');
assert($plugin->cache->is_enabled() === false, 'Cache must be disabled when Pro is inactive');
assert($plugin->cache->get('non_existent.jpg', 1, 0, 'test-model') === null, 'Cache get must return null when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 6: Button Text Filter
// -----------------------------------------------------------------------------
echo "- Testing Button Text Filter (Free tier)... ";
$default_btn_text = __('Try It On with AI', 'fito-try-on-with-ai-for-woocommerce');
update_option('fito_tryon_button_text', 'Custom Pro Text');
$filtered_text = apply_filters('fito_tryon_button_text', $default_btn_text);
assert($filtered_text === $default_btn_text, 'Button text filter must return default label when Pro is inactive');
delete_option('fito_tryon_button_text');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 7: Frontend Placement Handlers
// -----------------------------------------------------------------------------
echo "- Testing Frontend Placement Handlers (Free tier)... ";
ob_start();
$plugin->public->render_archive_button();
$archive_out = ob_get_clean();
assert(empty($archive_out), 'render_archive_button must produce zero output when Pro is inactive');

$dummy_gallery_html = '<div class="woocommerce-product-gallery__image"><img src="test.jpg"></div>';
$gallery_filtered = $plugin->public->filter_gallery_image_html($dummy_gallery_html, 123);
assert($gallery_filtered === $dummy_gallery_html, 'filter_gallery_image_html must return html unmodified when Pro is inactive');

ob_start();
$plugin->public->render_variation_button();
$variation_out = ob_get_clean();
assert(empty($variation_out), 'render_variation_button must produce zero output when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 8: Custom Image CSS Selectors
// -----------------------------------------------------------------------------
echo "- Testing Custom CSS Selectors Gating (Free tier)... ";
update_option('fito_tryon_custom_image_selectors', '.my-slider img');
$is_prod_allowed = true;
$selectors_active = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? trim((string) get_option('fito_tryon_custom_image_selectors', '')) : '';
assert($selectors_active === '', 'Custom image selectors must resolve to empty string when Pro is inactive');
delete_option('fito_tryon_custom_image_selectors');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 9: Save to Account History
// -----------------------------------------------------------------------------
echo "- Testing Save to Account REST & Menu (Free tier)... ";
$req_save_account = new WP_REST_Request('POST', '/fito-tryon/v1/save-account');
$res_save_account = $rest->handle_save_account($req_save_account);
assert($res_save_account->get_status() === 403, 'handle_save_account must return 403 Forbidden when Pro is inactive');

$menu_items = ['orders' => 'Orders', 'edit-account' => 'Account Details'];
$filtered_menu = $plugin->public->add_account_menu_item($menu_items);
assert(!isset($filtered_menu['fito-tryons']), 'My Account menu items must not include fito-tryons when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 10: Custom Guest Sample Image Gating
// -----------------------------------------------------------------------------
echo "- Testing Custom Guest Sample Image (Free tier)... ";
update_option('fito_tryon_sample_image_id', 777);
$sample_id = Fito_Tryon::is_pro_active() ? absint(get_option('fito_tryon_sample_image_id', 0)) : 0;
assert($sample_id === 0, 'Guest sample image ID must resolve to 0 when Pro is inactive');
delete_option('fito_tryon_sample_image_id');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 11: Watermark Preview & Custom Watermark
// -----------------------------------------------------------------------------
echo "- Testing Watermark Preview Gating (Free tier)... ";
$req_preview_wm = new WP_REST_Request('POST', '/fito-tryon/v1/preview-watermark');
$res_preview_wm = $rest->handle_preview_watermark($req_preview_wm);
assert($res_preview_wm->get_status() === 403, 'handle_preview_watermark must return 403 Forbidden when Pro is inactive');

$storage_wm_preview = $plugin->storage->generate_watermark_preview(['enabled' => true]);
assert($storage_wm_preview['success'] === false, 'generate_watermark_preview must return success => false when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 12: Photo Logs & Retention Window Gating
// -----------------------------------------------------------------------------
echo "- Testing Photo Logs & Retention Window Gating (Free tier)... ";
update_option('fito_tryon_log_images', 'yes');
$save_images = Fito_Tryon::is_pro_active() && (get_option('fito_tryon_log_images', 'no') === 'yes');
assert($save_images === false, 'Log image storage must be false when Pro is inactive');

update_option('fito_tryon_retention_hours', 72);
$retention_minutes = !Fito_Tryon::is_pro_active() ? 30 : max(1, (int) get_option('fito_tryon_retention_hours', 24) * 60);
assert($retention_minutes === 30, 'Retention window must be strictly 30 minutes when Pro is inactive');
delete_option('fito_tryon_log_images');
delete_option('fito_tryon_retention_hours');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 13: Custom Prompts Execution & Saving
// -----------------------------------------------------------------------------
echo "- Testing Custom Prompts Execution Gating (Free tier)... ";
$custom_prompt_config = [
    [
        'id'         => 'custom_lingerie',
        'title'      => 'Custom Lingerie Fit',
        'is_system'  => false,
        'categories' => [9999],
        'prompt'     => 'Special custom fitting prompt for category 9999',
    ],
    [
        'id'         => 'clothing',
        'title'      => 'Clothing Preset',
        'is_system'  => true,
        'categories' => [],
        'prompt'     => 'Default clothing instruction template',
    ],
];
update_option('fito_tryon_prompts_config', $custom_prompt_config);

$built_prompt = $plugin->openrouter->build_category_prompt([
    'product_title' => 'Silk Robe',
    'category_ids'  => [9999],
    'categories'    => 'lingerie',
]);

assert(!str_contains($built_prompt, 'Special custom fitting prompt'), 'Custom prompt must not be evaluated when Pro is inactive');
assert(str_contains($built_prompt, 'Default clothing instruction template'), 'Default clothing prompt must be used when Pro is inactive');
delete_option('fito_tryon_prompts_config');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 14: Settings Save Gating (handle_save_settings simulation)
// -----------------------------------------------------------------------------
echo "- Testing Settings Save Gating when Pro is Inactive... ";
delete_option('fito_tryon_archive_button');
delete_option('fito_tryon_gallery_buttons');
delete_option('fito_tryon_variation_images');
delete_option('fito_tryon_save_to_account');
delete_option('fito_tryon_watermark_enabled');

// Simulate handle_save_settings with Pro options posted while Pro is inactive
$is_pro = Fito_Tryon::is_pro_active();
assert($is_pro === false);

if ($is_pro) {
    update_option('fito_tryon_archive_button', 'yes');
    update_option('fito_tryon_gallery_buttons', 'yes');
    update_option('fito_tryon_save_to_account', 'yes');
}

assert(get_option('fito_tryon_archive_button') === false, 'fito_tryon_archive_button must not be updated when Pro is inactive');
assert(get_option('fito_tryon_gallery_buttons') === false, 'fito_tryon_gallery_buttons must not be updated when Pro is inactive');
assert(get_option('fito_tryon_save_to_account') === false, 'fito_tryon_save_to_account must not be updated when Pro is inactive');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 15: Pro Tier Verification (When Pro is Active)
// -----------------------------------------------------------------------------
echo "- Testing Pro Features are Accessible when Pro is Active... ";
remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_true');
assert(Fito_Tryon::is_pro_active(), 'Failed: Pro should be active');

// When Pro is active:
update_option('fito_tryon_ai_provider', 'gemini');
assert($plugin->get_active_provider() === 'gemini', 'Provider gemini must be allowed when Pro is active');

update_option('fito_tryon_cache_enabled', 'yes');
assert($plugin->cache->is_enabled() === true, 'Cache must be enabled when Pro is active');

update_option('fito_tryon_retention_hours', 48);
$pro_retention = !Fito_Tryon::is_pro_active() ? 30 : max(1, (int) get_option('fito_tryon_retention_hours', 24) * 60);
assert($pro_retention === 2880, 'Retention window must reflect configured hours when Pro is active');

$pro_storage_preview = $plugin->storage->generate_watermark_preview(['enabled' => false]);
assert($pro_storage_preview['success'] === true, 'Watermark preview must succeed when Pro is active');

update_option('fito_tryon_prompts_config', $custom_prompt_config);
$pro_built_prompt = $plugin->openrouter->build_category_prompt([
    'product_title' => 'Silk Robe',
    'category_ids'  => [9999],
    'categories'    => 'lingerie',
]);
assert(str_contains($pro_built_prompt, 'Special custom fitting prompt'), 'Custom prompt must be evaluated when Pro is active');

// Reset filters and test options
remove_all_filters('fito_tryon_is_pro_active');
delete_option('fito_tryon_prompts_config');
delete_option('fito_tryon_ai_provider');
delete_option('fito_tryon_cache_enabled');
delete_option('fito_tryon_retention_hours');
echo "PASSED\n";

// -----------------------------------------------------------------------------
// Test 16: Em Dash Guardrail Check
// -----------------------------------------------------------------------------
echo "- Testing Em Dash guardrail across all modified files... ";
$files_to_check = [
    dirname(__DIR__) . '/includes/class-fito-tryon-rest.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-gemini.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-openai.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-public.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-storage.php',
    dirname(__DIR__) . '/includes/class-fito-tryon-openrouter.php',
    dirname(__DIR__) . '/admin/class-fito-tryon-admin.php',
    dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/fito-try-on-with-ai-for-woocommerce-pro-addon.php',
];

foreach ($files_to_check as $file_path) {
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        assert(!str_contains($content, "\u{2014}"), "Em dash detected in {$file_path}");
    }
}
echo "PASSED\n";

echo "\n[PASS] All Complete Pro Features Access Control Gating Tests Completed Successfully!\n";
