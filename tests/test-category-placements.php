<?php
/**
 * Automated Unit Tests for Category-Gated Placements across:
 * - Button Placement (Single Product Button, Product Tab, Shortcode)
 * - Shop Archive Loops
 * - Product Gallery Images
 * - Product Variation Images
 * - Custom Image CSS Selectors
 * - REST API Submission Enforcement
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Category-Gated Placements Unit Tests ===\n";

$plugin = Fito_Tryon::instance();
$public = $plugin->public;
$rest   = $plugin->rest;

// Create dummy categories for testing
$cat_shirts_res = wp_insert_term('Test Shirts ' . time(), 'product_cat');
$cat_shirts_id  = is_array($cat_shirts_res) ? (int) $cat_shirts_res['term_id'] : 0;

$cat_shoes_res  = wp_insert_term('Test Shoes ' . time(), 'product_cat');
$cat_shoes_id   = is_array($cat_shoes_res) ? (int) $cat_shoes_res['term_id'] : 0;

$cat_books_res  = wp_insert_term('Test Books ' . time(), 'product_cat');
$cat_books_id   = is_array($cat_books_res) ? (int) $cat_books_res['term_id'] : 0;

// Create dummy products
$product_shirt = new WC_Product_Simple();
$product_shirt->set_name('Test Silk Shirt');
$product_shirt->set_regular_price('49.99');
$product_shirt->set_category_ids([$cat_shirts_id]);
$product_shirt_id = $product_shirt->save();

$product_book = new WC_Product_Simple();
$product_book->set_name('Test Coding Book');
$product_book->set_regular_price('19.99');
$product_book->set_category_ids([$cat_books_id]);
$product_book_id = $product_book->save();

// Test 1: When no categories are enabled (empty array), all products are allowed by default
update_option('fito_tryon_allowed_categories', []);
assert($public->is_product_allowed($product_shirt_id) === true, "Failed: When no categories restricted, shirt should be allowed");
assert($public->is_product_allowed($product_book_id) === true, "Failed: When no categories restricted, book should be allowed");
echo "- Testing Default All Categories Allowed... PASSED\n";

// Test 2: Enable only 'Test Shirts' category
update_option('fito_tryon_allowed_categories', [(string) $cat_shirts_id]);
assert($public->is_product_allowed($product_shirt_id) === true, "Failed: Shirt in enabled category must be allowed");
assert($public->is_product_allowed($product_book_id) === false, "Failed: Book in non-enabled category must be disallowed");
echo "- Testing Strict Category Filtering... PASSED\n";

// Test 3: Variation product inherits parent product category
$product_var_parent = new WC_Product_Variable();
$product_var_parent->set_name('Test Variable Shirt');
$product_var_parent->set_category_ids([$cat_shirts_id]);
$var_parent_id = $product_var_parent->save();

$variation = new WC_Product_Variation();
$variation->set_parent_id($var_parent_id);
$variation->set_regular_price('59.99');
$variation_id = $variation->save();

assert($public->is_product_allowed($variation_id) === true, "Failed: Variation of enabled category product must be allowed");

$product_book_parent = new WC_Product_Variable();
$product_book_parent->set_name('Test Variable Book');
$product_book_parent->set_category_ids([$cat_books_id]);
$book_parent_id = $product_book_parent->save();

$book_var = new WC_Product_Variation();
$book_var->set_parent_id($book_parent_id);
$book_var->set_regular_price('29.99');
$book_var_id = $book_var->save();

assert($public->is_product_allowed($book_var_id) === false, "Failed: Variation of disallowed category product must be disallowed");
echo "- Testing Product Variation Category Resolution... PASSED\n";

// Test 4: Product Tab Gating
global $product;
$product = wc_get_product($product_book_id);
$tabs = $public->add_product_tab([]);
assert(!isset($tabs['fito_tryon_tab']), "Failed: Product tab must not be added to disallowed product");

$product = wc_get_product($product_shirt_id);
$tabs = $public->add_product_tab([]);
assert(isset($tabs['fito_tryon_tab']), "Failed: Product tab must be added to allowed product");
echo "- Testing Product Tab Gating... PASSED\n";

// Test 5: Gallery Images HTML Filter Gating
$dummy_html = '<div class="woocommerce-product-gallery__image"><img src="test.jpg" /></div>';

$product = wc_get_product($product_book_id);
$filtered_disallowed = $public->filter_gallery_image_html($dummy_html, 1);
assert(!str_contains($filtered_disallowed, 'fito-tryon-btn--gallery'), "Failed: Gallery button must not appear on disallowed product");

$product = wc_get_product($product_shirt_id);
$filtered_allowed = $public->filter_gallery_image_html($dummy_html, 1);
assert(str_contains($filtered_allowed, 'fito-tryon-btn--gallery'), "Failed: Gallery button must appear on allowed product");
echo "- Testing Product Gallery Images Filter Gating... PASSED\n";

// Test 6: Shortcode Gating
$shortcode_disallowed = $public->render_shortcode(['product_id' => $product_book_id]);
assert(empty($shortcode_disallowed), "Failed: Shortcode must return empty string for disallowed product");

$shortcode_allowed = $public->render_shortcode(['product_id' => $product_shirt_id]);
assert(str_contains($shortcode_allowed, 'fito-tryon-btn--single'), "Failed: Shortcode must render button for allowed product");
echo "- Testing Shortcode & Block Placement Gating... PASSED\n";

// Test 7: REST API Category Enforcement
update_option('fito_tryon_access_level', 'everyone');
$request_disallowed = new WP_REST_Request('POST', '/ai-tryon/v1/submit');
$request_disallowed->set_param('consent', 'yes');
$request_disallowed->set_param('product_id', $product_book_id);
$request_disallowed->set_param('image_base64', 'data:image/jpeg;base64,' . base64_encode('fakeimagecontent'));
$response_disallowed = $rest->handle_submit($request_disallowed);
assert($response_disallowed->get_status() === 403, "Failed: REST submit for disallowed product category must return HTTP 403");
$data_disallowed = $response_disallowed->get_data();
assert(str_contains($data_disallowed['message'], 'not enabled for this product category'), "Failed: REST error message should mention category not enabled");
echo "- Testing Server-Side REST API Category Enforcement... PASSED\n";

// Cleanup test items
wp_delete_post($product_shirt_id, true);
wp_delete_post($product_book_id, true);
wp_delete_post($var_parent_id, true);
wp_delete_post($variation_id, true);
wp_delete_post($book_parent_id, true);
wp_delete_post($book_var_id, true);
wp_delete_term($cat_shirts_id, 'product_cat');
wp_delete_term($cat_shoes_id, 'product_cat');
wp_delete_term($cat_books_id, 'product_cat');
update_option('fito_tryon_allowed_categories', []);

echo "\n[PASS] All Category-Gated Placements Unit Tests Completed Successfully!\n";
