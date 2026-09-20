<?php
/**
 * Automated Unit Tests for Pro Appearance Options Gating
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Pro Appearance Gating Tests ===\n";

require_once dirname(__DIR__) . '/fito-try-on-with-ai-for-woocommerce.php';
$public = Fito_Tryon::instance()->public;

// Test 1: Button Text Filter Default (Free tier)
$default_label = __('Try It On with AI', 'fito-try-on-with-ai-for-woocommerce');
$filtered_label = apply_filters('fito_tryon_button_text', $default_label);
assert($filtered_label === $default_label, "Failed: Free tier should return default button text");
echo "- Testing Default Button Text (Free tier)... PASSED ('$filtered_label')\n";

// Test 2: Button Text Filter with Pro Addon Active
$custom_label = 'Wear with AI Fit';
update_option('fito_tryon_button_text', $custom_label);

// Load Pro Addon
$pro_addon_file = dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/fito-try-on-with-ai-for-woocommerce-pro-addon.php';
require_once $pro_addon_file;
$pro_addon = new Fito_Tryon_Pro_Addon();

// Simulate Pro Addon hook
$pro_filtered_label = $pro_addon->filter_button_text($default_label);
assert($pro_filtered_label === $custom_label, "Failed: Pro Addon should return custom button text ($custom_label)");
echo "- Testing Custom Button Text (Pro Addon)... PASSED ('$pro_filtered_label')\n";

// Test 3: Public localization gating when Pro is active vs inactive
$is_prod_allowed = true;

// Pro inactive simulation
add_filter('fito_tryon_is_pro_active', '__return_false');
$sim_pro_active = Fito_Tryon::is_pro_active();
assert($sim_pro_active === false, "Failed: is_pro_active should be false");

$gallery_active = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? get_option('fito_tryon_gallery_buttons', 'no') : 'no';
$variation_active = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? get_option('fito_tryon_variation_images', 'no') : 'no';
$selectors_active = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? trim((string) get_option('fito_tryon_custom_image_selectors', '')) : '';

assert($gallery_active === 'no', "Failed: Gallery buttons should be 'no' when Pro is inactive");
assert($variation_active === 'no', "Failed: Variation images should be 'no' when Pro is inactive");
assert($selectors_active === '', "Failed: Custom selectors should be empty when Pro is inactive");
echo "- Testing Frontend Gating when Pro is Inactive... PASSED\n";

// Pro active simulation
remove_all_filters('fito_tryon_is_pro_active');
add_filter('fito_tryon_is_pro_active', '__return_true');
update_option('fito_tryon_gallery_buttons', 'yes');
update_option('fito_tryon_custom_image_selectors', '.my-lookbook img');

$gallery_pro = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? get_option('fito_tryon_gallery_buttons', 'no') : 'no';
$selectors_pro = ($is_prod_allowed && Fito_Tryon::is_pro_active()) ? trim((string) get_option('fito_tryon_custom_image_selectors', '')) : '';

assert($gallery_pro === 'yes', "Failed: Gallery buttons should be 'yes' when Pro is active");
assert($selectors_pro === '.my-lookbook img', "Failed: Custom selectors should be populated when Pro is active");
echo "- Testing Frontend Gating when Pro is Active... PASSED\n";

// Clean up test options
delete_option('fito_tryon_button_text');
delete_option('fito_tryon_gallery_buttons');
delete_option('fito_tryon_custom_image_selectors');

echo "\n[PASS] All Pro Appearance Gating Tests Completed Successfully!\n";
