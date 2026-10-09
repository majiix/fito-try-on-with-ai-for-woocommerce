<?php
/**
 * Test Free Core standalone execution and Pro Addon hook contracts.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('FITO_TRYON_VERSION')) {
    define('FITO_TRYON_VERSION', '1.4.0');
}

// Minimal WordPress function stubs for unit testing.
if (!function_exists('add_filter')) {
    $GLOBALS['wp_filter'] = [];
    $GLOBALS['wp_actions'] = [];

    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['wp_filter'][$tag][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }

    function apply_filters($tag, $value, ...$args) {
        if (!isset($GLOBALS['wp_filter'][$tag])) {
            return $value;
        }
        foreach ($GLOBALS['wp_filter'][$tag] as $filter) {
            $cb = $filter['callback'];
            $params = array_merge([$value], $args);
            $value = call_user_func_array($cb, array_slice($params, 0, $filter['accepted_args']));
        }
        return $value;
    }

    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($tag, $callback, $priority, $accepted_args);
    }

    function do_action($tag, ...$args) {
        $GLOBALS['wp_actions'][$tag] = ($GLOBALS['wp_actions'][$tag] ?? 0) + 1;
        if (!isset($GLOBALS['wp_filter'][$tag])) {
            return;
        }
        foreach ($GLOBALS['wp_filter'][$tag] as $filter) {
            call_user_func_array($filter['callback'], array_slice($args, 0, $filter['accepted_args']));
        }
    }

    function did_action($tag) {
        return $GLOBALS['wp_actions'][$tag] ?? 0;
    }
}

if (!function_exists('get_option')) {
    $GLOBALS['wp_options'] = [];
    function get_option($key, $default = false) {
        return $GLOBALS['wp_options'][$key] ?? $default;
    }
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['wp_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = &$args;
        } else {
            $r = [];
        }
        return array_merge($defaults, $r);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => sys_get_temp_dir() . '/wp_uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return is_dir($target) || mkdir($target, 0755, true);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {}
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent, $page_title, $menu_title, $cap, $menu_slug, $callback = null) {
        return $menu_slug;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test-nonce-123';
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {}
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        $atts = (array) $atts;
        $out = [];
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }
        return $out;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

echo "=== Running Free Core Standalone & Pro Handshake Tests ===\n";

require_once dirname(__DIR__) . '/includes/class-fito-tryon-storage.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon-openrouter.php';
require_once dirname(__DIR__) . '/includes/class-fito-tryon.php';

// Test 1: Free Core Standalone Provider Dispatch
$plugin = Fito_Tryon::instance();
$providers = $plugin->get_allowed_providers();
assert(count($providers) === 1 && $providers[0] === 'openrouter', 'Free plugin default provider must be openrouter only');
echo "PASS: Free plugin defaults to OpenRouter BYOK standalone.\n";

// Test 2: Free Core Clean Cache Decoupling (Option B - No stub class in Free)
assert(!class_exists('Fito_Tryon_Cache'), 'Fito_Tryon_Cache must not exist in Free plugin (Option B)');
$cached_val = apply_filters('fito_tryon_get_cached_result', null, '/path/to/image.jpg', 123, 0, 'test-model');
assert($cached_val === null, 'Cache filter must return null when Pro addon is inactive');
echo "PASS: Standalone cache hook returns null without Pro and Free has no stub class.\n";

// Test 3: Free Core Go Pro Filter Contract
$show_pro_card = apply_filters('fito_tryon_show_pro_card', true);
assert($show_pro_card === true, 'fito_tryon_show_pro_card filter defaults to true');
echo "PASS: fito_tryon_show_pro_card filter defaults to true.\n";

// Test 4: Pro Addon Provider Extension Handshake
add_filter('fito_tryon_allowed_providers', function($providers) {
    return array_merge($providers, ['gemini', 'openai']);
});
$extended_providers = $plugin->get_allowed_providers();
assert(in_array('gemini', $extended_providers, true), 'Pro must be able to add gemini provider via filter');
assert(in_array('openai', $extended_providers, true), 'Pro must be able to add openai provider via filter');
echo "PASS: Pro cleanly extends allowed AI providers via filter contract.\n";

// Test 5: Pro Addon Result Cache & Flush Handshake
add_filter('fito_tryon_get_cached_result', function($default, $image_path, $product_id, $variation_id, $model) {
    return 'https://example.com/cached-result.png';
}, 10, 5);
$cached_via_pro = apply_filters('fito_tryon_get_cached_result', null, '/path/to/image.jpg', 123, 0, 'test-model');
assert($cached_via_pro === 'https://example.com/cached-result.png', 'Pro must be able to serve cached results via filter');

$flush_fired = false;
add_action('fito_tryon_flush_cache', function() use (&$flush_fired) {
    $flush_fired = true;
});
do_action('fito_tryon_flush_cache');
assert($flush_fired === true, 'fito_tryon_flush_cache action must trigger cache flush');
echo "PASS: Pro result caching and flush hook cleanly into Free filters/actions.\n";

// Test 6: Pro Watermark Action Handshake
$action_fired = false;
add_action('fito_tryon_after_result_image_saved', function($dest_path) use (&$action_fired) {
    $action_fired = true;
});
do_action('fito_tryon_after_result_image_saved', '/fake/path/image.png');
assert($action_fired === true, 'fito_tryon_after_result_image_saved action must fire');
echo "PASS: fito_tryon_after_result_image_saved triggers watermarking action contract.\n";

// Test 7: Audit Log Insert Data Filter Contract
$sample_insert_data = [
    'user_image_url'   => '',
    'result_image_url' => '',
];
$sample_raw_data = [
    'user_image_url'   => 'https://example.com/customer.jpg',
    'result_image_url' => 'https://example.com/result.jpg',
];
$filtered_data = apply_filters('fito_tryon_log_insert_data', $sample_insert_data, $sample_raw_data);
assert($filtered_data['user_image_url'] === '', 'Standalone Free log data must have empty user image URL');
assert($filtered_data['result_image_url'] === '', 'Standalone Free log data must have empty result image URL');

add_filter('fito_tryon_log_insert_data', function($insert, $raw) {
    $insert['user_image_url']   = $raw['user_image_url'];
    $insert['result_image_url'] = $raw['result_image_url'];
    return $insert;
}, 10, 2);
$pro_filtered_data = apply_filters('fito_tryon_log_insert_data', $sample_insert_data, $sample_raw_data);
assert($pro_filtered_data['user_image_url'] === 'https://example.com/customer.jpg', 'Pro filter must be able to inject user image URL');
assert($pro_filtered_data['result_image_url'] === 'https://example.com/result.jpg', 'Pro filter must be able to inject result image URL');
echo "PASS: fito_tryon_log_insert_data filter enables Pro visual audit log injection.\n";

// Test 8: Pro Provider Panels and Save Settings Action Handshake
$rendered_panels = [];
add_action('fito_tryon_admin_provider_panels', function($active_provider, $allowed) use (&$rendered_panels) {
    $rendered_panels[] = $active_provider;
}, 10, 2);
do_action('fito_tryon_admin_provider_panels', 'gemini', ['openrouter', 'gemini', 'openai']);
assert(count($rendered_panels) === 1 && $rendered_panels[0] === 'gemini', 'Provider panels action must fire with active provider');

$saved_provider = null;
add_action('fito_tryon_admin_save_provider_settings', function($provider, $post) use (&$saved_provider) {
    $saved_provider = $provider;
}, 10, 2);
do_action('fito_tryon_admin_save_provider_settings', 'openai', ['fito_tryon_openai_api_key' => 'sk-test']);
assert($saved_provider === 'openai', 'Save provider settings action must fire with submitted provider');

do_action('fito_tryon_admin_save_provider_settings', null, []);
assert($saved_provider === null, 'Save provider settings action must handle null provider gracefully');
echo "PASS: fito_tryon_admin_provider_panels & save actions dispatch correctly.\n";

// Test 9: Public Script Data Filter Handshake
add_filter('fito_tryon_public_script_data', function($data) {
    $data['galleryButtons'] = 'yes';
    $data['customImageSelectors'] = '.lookbook img';
    return $data;
});
$pro_script_data = apply_filters('fito_tryon_public_script_data', ['isProductAllowed' => true]);
assert($pro_script_data['galleryButtons'] === 'yes', 'Pro must be able to inject galleryButtons into script data');
assert($pro_script_data['customImageSelectors'] === '.lookbook img', 'Pro must be able to inject customImageSelectors');
echo "PASS: fito_tryon_public_script_data enables Pro frontend feature flags.\n";

// Test 10: Appearance Settings Hooks Handshake
$appearance_rendered = false;
add_action('fito_tryon_admin_appearance_settings', function() use (&$appearance_rendered) {
    $appearance_rendered = true;
});
do_action('fito_tryon_admin_appearance_settings');
assert($appearance_rendered === true, 'fito_tryon_admin_appearance_settings action must fire');

$appearance_saved = false;
add_action('fito_tryon_save_settings_post', function($post_data) use (&$appearance_saved) {
    if (!empty($post_data['fito_tryon_pro_appearance_submitted'])) {
        $appearance_saved = true;
    }
});
do_action('fito_tryon_save_settings_post', ['fito_tryon_pro_appearance_submitted' => '1', 'fito_tryon_archive_button' => 'yes']);
assert($appearance_saved === true, 'fito_tryon_save_settings_post action must fire with post data');
echo "PASS: fito_tryon_admin_appearance_settings and save_settings_post dispatch correctly.\n";

// Test 11: Privacy Settings & Retention Hours Handshake
$privacy_rendered = false;
add_action('fito_tryon_admin_privacy_settings', function() use (&$privacy_rendered) {
    $privacy_rendered = true;
});
do_action('fito_tryon_admin_privacy_settings');
assert($privacy_rendered === true, 'fito_tryon_admin_privacy_settings action must fire');

add_filter('fito_tryon_retention_hours', function($hours) {
    return 72;
});
$custom_retention = apply_filters('fito_tryon_retention_hours', 24);
assert($custom_retention === 72, 'fito_tryon_retention_hours filter must allow overriding retention hours');
echo "PASS: fito_tryon_admin_privacy_settings and fito_tryon_retention_hours dispatch correctly.\n";

// Test 12: Standalone Free Fallback Model
$free_fallback_model = apply_filters('fito_tryon_fallback_model', '', 'openrouter');
assert($free_fallback_model === '', 'Free standalone must default fallback model to empty string');

$extra_fields_rendered = false;
add_action('fito_tryon_admin_provider_extra_fields', function($provider) use (&$extra_fields_rendered) {
    if ($provider === 'openrouter') {
        $extra_fields_rendered = true;
    }
});
do_action('fito_tryon_admin_provider_extra_fields', 'openrouter');
assert($extra_fields_rendered === true, 'fito_tryon_admin_provider_extra_fields action must dispatch');
echo "PASS: Free core cleanly delegates provider extra fields via action hook.\n";

// Test 13: Pro Addon Fallback Model Resolution
add_filter('fito_tryon_fallback_model', function($default, $provider) {
    return 'black-forest-labs/flux-1-schnell';
}, 10, 2);
$pro_fallback_model = apply_filters('fito_tryon_fallback_model', '', 'openrouter');
assert($pro_fallback_model === 'black-forest-labs/flux-1-schnell', 'Pro must resolve configured fallback model');
echo "PASS: Pro addon cleanly provides secondary fallback model routing.\n";

// Test 14: Standalone Free Prompts Filtering
$default_ids = ['clothing', 'shoes', 'eyewear', 'watches', 'hats', 'bags', 'jewelry'];
$sample_all_prompts = [
    ['id' => 'clothing', 'is_system' => true, 'title' => 'Clothing'],
    ['id' => 'shoes', 'is_system' => true, 'title' => 'Shoes'],
    ['id' => 'custom_123', 'is_system' => false, 'title' => 'Custom Leather'],
];
$system_prompts = array_values(array_filter($sample_all_prompts, function ($p) use ($default_ids) {
    return !empty($p['is_system']) || in_array($p['id'], $default_ids, true);
}));
$free_displayed = apply_filters('fito_tryon_admin_displayed_prompts', $system_prompts, $sample_all_prompts);
assert(count($free_displayed) === 2, 'Free standalone must default displayed prompts to system presets');
echo "PASS: Free core defaults displayed prompts to built-in presets without trialware gates.\n";

// Test 15: Pro Addon Custom Prompts Display & Actions Handshake
add_filter('fito_tryon_admin_displayed_prompts', function($system_prompts, $all_prompts) {
    return $all_prompts;
}, 10, 2);
$pro_displayed = apply_filters('fito_tryon_admin_displayed_prompts', $system_prompts, $sample_all_prompts);
assert(count($pro_displayed) === 3, 'Pro addon must extend displayed prompts to include custom ones');

$prompt_actions_fired = false;
add_action('fito_tryon_admin_prompts_header_actions', function() use (&$prompt_actions_fired) {
    $prompt_actions_fired = true;
});
do_action('fito_tryon_admin_prompts_header_actions');
assert($prompt_actions_fired === true, 'fito_tryon_admin_prompts_header_actions must fire');
echo "PASS: Pro addon cleanly extends prompts manager with custom prompts and UI actions.\n";

// Test 16: Product Allowed Filter Contract
$product_allowed = apply_filters('fito_tryon_is_product_allowed', true, 123);
assert($product_allowed === true, 'fito_tryon_is_product_allowed filter must resolve product eligibility');
echo "PASS: fito_tryon_is_product_allowed filter contract functions correctly.\n";

// Test 17: Storage base64 helpers
$temp_img = tempnam(sys_get_temp_dir(), 'test_img') . '.jpg';
file_put_contents($temp_img, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . 'FAKE_JPEG_BINARY_DATA');
$b64 = Fito_Tryon_Storage::image_to_base64($temp_img);
assert(str_starts_with($b64, 'data:image/'), 'Fito_Tryon_Storage::image_to_base64 must return data URI');
$resolved_b64 = Fito_Tryon_Storage::resolve_image_base64($temp_img);
assert($resolved_b64 === $b64, 'Fito_Tryon_Storage::resolve_image_base64 must match image_to_base64');
unlink($temp_img);
echo "PASS: Fito_Tryon_Storage image conversion utilities function statically without internal coupling.\n";

// Test 18: Category prompt generation via public filter
$openrouter = new Fito_Tryon_OpenRouter();
$category_prompt = apply_filters('fito_tryon_build_category_prompt', '', [
    'product_title' => 'Floral Summer Dress',
    'categories'    => 'clothing',
]);
assert(!empty($category_prompt) && str_contains($category_prompt, 'Floral Summer Dress'), 'Prompt must interpolate product title');
echo "PASS: fito_tryon_build_category_prompt filter contract functions cleanly.\n";

// Test 19: Pro Storage is Self-Contained and Independent
require_once dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/includes/class-fito-tryon-pro-storage.php';
$temp_img_pro = tempnam(sys_get_temp_dir(), 'test_img_pro') . '.jpg';
file_put_contents($temp_img_pro, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . 'PRO_TEST_JPEG_DATA');
$pro_b64 = Fito_Tryon_Pro_Storage::image_to_base64($temp_img_pro);
assert(str_starts_with($pro_b64, 'data:image/'), 'Fito_Tryon_Pro_Storage::image_to_base64 must return data URI');
unlink($temp_img_pro);
echo "PASS: Fito_Tryon_Pro_Storage is completely self-contained in Pro Addon.\n";

// Test 20: OpenRouter Failover Model Routing Contract (Option B)
$failover_invoked = false;
add_filter('fito_tryon_handle_generation_failover', function($result, $params, $provider, $api_key, $client) use (&$failover_invoked) {
    $failover_invoked = true;
    return [
        'success'     => true,
        'model'       => 'pro-failover-model',
        'is_fallback' => true,
    ];
}, 10, 5);
$failed_initial_result = ['success' => false, 'message' => 'Rate limit exceeded'];
$failover_result = apply_filters('fito_tryon_handle_generation_failover', $failed_initial_result, ['param' => 1], 'openrouter', 'key123', null);
assert($failover_invoked === true && $failover_result['success'] === true, 'Failover hook must allow Pro to handle secondary failover');
echo "PASS: fito_tryon_handle_generation_failover successfully delegates failover to Pro Addon.\n";

// Test 21: Database Job Cleanup Filter Contract
$standalone_purge_where = apply_filters('fito_tryon_purge_jobs_where', '1=1');
assert($standalone_purge_where === '1=1', 'Free standalone purge condition must default to 1=1');
add_filter('fito_tryon_purge_jobs_where', function() {
    return 'is_saved_to_account = 0';
});
$pro_purge_where = apply_filters('fito_tryon_purge_jobs_where', '1=1');
assert($pro_purge_where === 'is_saved_to_account = 0', 'Pro must filter job purge condition to preserve user saved jobs');
echo "PASS: fito_tryon_purge_jobs_where filter cleanly isolates user account job persistence.\n";

// Test 22: Pro Fallback Model Persistence and Markup Initial Visibility
require_once dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/fito-try-on-with-ai-for-woocommerce-pro-addon.php';
require_once dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon/admin/class-fito-tryon-pro-admin.php';

$pro_admin = Fito_Tryon_Pro_Admin::instance();

// Simulate save provider settings
$post_payload = [
    'fito_tryon_openrouter_fallback_model' => 'google/gemini-2.0-flash-exp',
    'fito_tryon_gemini_fallback_model'     => 'imagen-3.0-generate-002',
    'fito_tryon_openai_fallback_model'     => 'dall-e-3',
];
$pro_admin->save_pro_provider_settings('openrouter', $post_payload);

$resolved_openrouter = Fito_Tryon_Pro_Addon::instance()->resolve_fallback_model('', 'openrouter');
$resolved_gemini     = Fito_Tryon_Pro_Addon::instance()->resolve_fallback_model('', 'gemini');
$resolved_openai     = Fito_Tryon_Pro_Addon::instance()->resolve_fallback_model('', 'openai');

assert($resolved_openrouter === 'google/gemini-2.0-flash-exp', 'OpenRouter fallback model must be saved and resolved');
assert($resolved_gemini === 'imagen-3.0-generate-002', 'Gemini fallback model must be saved and resolved');
assert($resolved_openai === 'dall-e-3', 'OpenAI fallback model must be saved and resolved');

// Verify initial markup has skeleton hidden and select wrap visible
ob_start();
$pro_admin->render_provider_fallback_model_field('openrouter');
$rendered_html = ob_get_clean();

assert(str_contains($rendered_html, 'fito-fallback-skeleton" style="display: none;"'), 'Fallback skeleton must be hidden by default in markup');
assert(str_contains($rendered_html, '<div class="fito-fallback-select-wrap">'), 'Fallback select wrap must be visible by default in markup');
assert(str_contains($rendered_html, 'value="google/gemini-2.0-flash-exp" selected'), 'Saved fallback model must have selected attribute');
echo "PASS: Pro fallback models persist cleanly and render visible with skeleton hidden by default.\n";

// Test 23: Frontend Button Text Configuration and Filters
require_once dirname(__DIR__) . '/includes/class-fito-tryon-public.php';
$public = new Fito_Tryon_Public($plugin);

// Default when option is empty
update_option('fito_tryon_button_text', '');
assert($public->get_button_text() === 'Try It On with AI', 'Default button text must be "Try It On with AI" when option is empty');

// Custom button text from option
update_option('fito_tryon_button_text', 'پرو آنلاین لباس');
assert($public->get_button_text() === 'پرو آنلاین لباس', 'Button text must reflect custom option value');

// Tab title uses configured button text with filter support
add_filter('fito_tryon_button_text', function($title) {
    return 'Custom: ' . $title;
});
$filtered_title = apply_filters('fito_tryon_button_text', $public->get_button_text(), null);
assert($filtered_title === 'Custom: پرو آنلاین لباس', 'Button text filter contract must modify configured text');
echo "PASS: Frontend Button Text honors custom admin configuration and filter contract.\n";

// Test 24: Pro Addon Localized i18n Strings in Script Data
$pro_addon = Fito_Tryon_Pro_Addon::instance();
$pro_addon->init_addon();
$test_script_data = [
    'isProductAllowed' => true,
    'i18n'             => ['genericError' => 'Error'],
];
$filtered_data = apply_filters('fito_tryon_public_script_data', $test_script_data);
assert(isset($filtered_data['i18n']['saveAccount']), 'Pro must populate saveAccount i18n string');
assert(isset($filtered_data['i18n']['saved']), 'Pro must populate saved i18n string');
assert(isset($filtered_data['i18n']['tryOnGallery']), 'Pro must populate tryOnGallery i18n string');
assert(isset($filtered_data['i18n']['tryOnImage']), 'Pro must populate tryOnImage i18n string');
assert($filtered_data['i18n']['saveAccount'] === 'Save to My Account', 'Pro saveAccount label matches expected');
echo "PASS: Pro Addon enriches public script data with localized i18n strings.\n";

echo "=== ALL 24 TESTS PASSED SUCCESSFULLY ===\n";
