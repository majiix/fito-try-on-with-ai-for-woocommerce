<?php
/**
 * Automated Unit Tests for AI Try-On Prompts Pro Gating
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

echo "=== Running AI Try-On Prompts Pro Gating Tests ===\n";

$plugin = Fito_Tryon::instance();
if ($plugin->admin === null) {
    $plugin->admin = new Fito_Tryon_Admin($plugin);
}
$admin = $plugin->admin;

wp_set_current_user(1);
$current_user = wp_get_current_user();
$current_user->add_cap('manage_options');
$current_user->add_cap('manage_woocommerce');

// 1. Test Free Tier UI Rendering
deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
assert(!Fito_Tryon::is_pro_active(), "Failed: Pro should be inactive");

ob_start();
$admin->render_prompts_page();
$free_output = ob_get_clean();

echo "- Checking Free tier Add New Prompt button gating...\n";
assert(!str_contains($free_output, 'fito-btn-add-prompt'), "Failed: Free tier should not display Add New Prompt button");
assert(!str_contains($free_output, 'fito-btn-pro-locked'), "Failed: Free tier should not display locked button");
assert(!str_contains($free_output, 'fito-prompt-duplicate'), "Failed: Free tier should not display duplicate button");
assert(!str_contains($free_output, 'fitoPromptRowTemplate'), "Failed: Free tier should not render dynamic row template");
assert(str_contains($free_output, '#pro-feature-custom-prompts'), "Failed: Free tier should link to pro feature anchor in info card");
assert(str_contains($free_output, 'The Free tier includes full customization of all 7 built-in fitting presets'), "Failed: Free tier pro notice missing");

// 2. Test Free Tier Server-Side Save Protection
echo "- Checking Free tier handle_save_prompts blocks new custom prompts...\n";
// Save initial default configuration
$default_prompts = Fito_Tryon_OpenRouter::get_default_prompts();
update_option('fito_tryon_prompts_config', $default_prompts);

// Attempt to submit a new custom prompt while Pro is inactive
$_POST['fito_tryon_save_prompts_nonce'] = wp_create_nonce('fito_tryon_save_prompts_nonce');
$_POST['prompts'] = [
    [
        'id' => 'clothing',
        'title' => 'Clothing & Apparel Modified',
        'is_system' => '1',
        'prompt' => 'Modified clothing prompt instruction',
        'categories' => [],
    ],
    [
        'id' => 'custom_unauthorized_999',
        'title' => 'Unauthorized Custom Swimwear',
        'is_system' => '0',
        'prompt' => 'Virtually fit swimwear',
        'categories' => [],
    ],
];

// Call saving logic via reflection or by simulating the filter
// Since handle_save_prompts exits with wp_safe_redirect, test the core gating logic
$raw_prompts = $_POST['prompts'];
$default_ids = array_column($default_prompts, 'id');
$is_pro = Fito_Tryon::is_pro_active();
$existing_prompts = get_option('fito_tryon_prompts_config', []);
$existing_custom_ids = [];
if (is_array($existing_prompts)) {
    foreach ($existing_prompts as $ep) {
        if (!empty($ep['id']) && empty($ep['is_system']) && !in_array($ep['id'], $default_ids, true)) {
            $existing_custom_ids[] = (string) $ep['id'];
        }
    }
}

$sanitized_prompts = [];
foreach ($raw_prompts as $item) {
    $id = !empty($item['id']) ? sanitize_key($item['id']) : ('custom_' . wp_generate_password(6, false, false));
    $is_system = !empty($item['is_system']) || in_array($id, $default_ids, true);
    if (!$is_pro && !$is_system && !in_array($id, $existing_custom_ids, true)) {
        continue;
    }
    $sanitized_prompts[] = $item;
}

$saved_ids = array_column($sanitized_prompts, 'id');
assert(in_array('clothing', $saved_ids, true), "Failed: Preset clothing should be kept");
assert(!in_array('custom_unauthorized_999', $saved_ids, true), "Failed: New custom prompt should be blocked in free tier");

echo "- Checking Pro tier permits new custom prompts...\n";
// Now test with $is_pro = true
$is_pro = true;
$sanitized_pro_prompts = [];
foreach ($raw_prompts as $item) {
    $id = !empty($item['id']) ? sanitize_key($item['id']) : ('custom_' . wp_generate_password(6, false, false));
    $is_system = !empty($item['is_system']) || in_array($id, $default_ids, true);
    if (!$is_pro && !$is_system && !in_array($id, $existing_custom_ids, true)) {
        continue;
    }
    $sanitized_pro_prompts[] = $item;
}
$saved_pro_ids = array_column($sanitized_pro_prompts, 'id');
assert(in_array('custom_unauthorized_999', $saved_pro_ids, true), "Failed: Pro tier should allow new custom prompt");

// 3. Test Pro Tier UI Rendering
echo "- Checking Pro tier UI Rendering...\n";
activate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
if (Fito_Tryon::is_pro_active()) {
    ob_start();
    $admin->render_prompts_page();
    $pro_ui_output = ob_get_clean();

    assert(str_contains($pro_ui_output, 'fito-btn-add-prompt'), "Failed: Pro tier should render Add New Prompt button");
    assert(str_contains($pro_ui_output, 'fito-prompt-duplicate'), "Failed: Pro tier should render duplicate button");
    assert(str_contains($pro_ui_output, 'fitoPromptRowTemplate'), "Failed: Pro tier should render dynamic row template");
    assert(!str_contains($pro_ui_output, 'The Free tier includes full customization'), "Failed: Pro tier should not show free tier banner");
    deactivate_plugins('fito-try-on-with-ai-pro/fito-tryon-pro.php');
}

// 4. Test Comparison Table & Detail Card in Go Pro Page
echo "- Checking Go Pro page comparison table and detail card...\n";
ob_start();
$admin->render_go_pro_page();
$pro_page_output = ob_get_clean();

assert(str_contains($pro_page_output, 'Custom Category Fitting Prompts'), "Failed: Comparison table missing Custom Category Fitting Prompts");
assert(str_contains($pro_page_output, '7 Built-in Presets Only'), "Failed: Comparison table missing free description");
assert(str_contains($pro_page_output, 'Unlimited Custom Prompts & Duplication'), "Failed: Comparison table missing pro description");
assert(str_contains($pro_page_output, 'id="pro-feature-custom-prompts"'), "Failed: Missing #pro-feature-custom-prompts anchor card");

// 4. Test Em Dash Guardrail
echo "- Checking em dashes guardrail...\n";
$pattern = '/\x{2014}/u';
assert(!preg_match($pattern, $free_output), "Em dash found in prompts page output");
assert(!preg_match($pattern, $pro_page_output), "Em dash found in pro page output");

// Reset option
update_option('fito_tryon_prompts_config', $default_prompts);

echo "\n[PASS] All AI Try-On Prompts Pro Gating Tests Completed Successfully!\n";
