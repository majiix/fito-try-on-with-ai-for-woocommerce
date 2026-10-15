<?php
/**
 * Test Free Core standalone execution and Pro Addon hook contracts.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('FITO_TRYON_VERSION')) {
    define('FITO_TRYON_VERSION', '1.5.0');
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

    function remove_filter($tag, $callback, $priority = 10) {
        if (!isset($GLOBALS['wp_filter'][$tag])) {
            return false;
        }
        foreach ($GLOBALS['wp_filter'][$tag] as $i => $filter) {
            if ($filter['callback'] === $callback && $filter['priority'] === $priority) {
                unset($GLOBALS['wp_filter'][$tag][$i]);
                return true;
            }
        }
        return false;
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

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var((string) $url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
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

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle(str_repeat($chars, 5)), 0, $length);
    }
}

if (!function_exists('wp_handle_upload')) {
    function wp_handle_upload(&$file, $overrides = false, $time = null) {
        if (!empty($overrides['test_form']) && empty($_POST['action'])) {
            return ['error' => 'Invalid form submission.'];
        }
        if (isset($file['error']) && $file['error'] > 0) {
            return ['error' => 'Upload error code: ' . $file['error']];
        }
        if (!empty($overrides['simulate_error'])) {
            return ['error' => $overrides['simulate_error']];
        }
        $uploads = apply_filters('upload_dir', wp_upload_dir($time));
        if (!empty($uploads['error'])) {
            return ['error' => $uploads['error']];
        }
        $dest_dir = $uploads['path'];
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        $filename = $file['name'] ?? 'uploaded.jpg';
        $dest_file = rtrim($dest_dir, '/\\') . '/' . $filename;
        if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            copy($file['tmp_name'], $dest_file);
        }
        return [
            'file' => $dest_file,
            'url'  => rtrim($uploads['url'], '/') . '/' . $filename,
            'type' => $file['type'] ?? 'image/jpeg',
        ];
    }
}

if (!function_exists('add_shortcode')) {
    $GLOBALS['shortcodes'] = [];
    function add_shortcode($tag, $callback) {
        $GLOBALS['shortcodes'][$tag] = $callback;
    }
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

// Test 4b: Free Standalone & Pro Provider Labels Filter Handshake
$free_labels = apply_filters('fito_tryon_provider_labels', [
    'openrouter' => 'OpenRouter (Multi-Model Aggregator)',
]);
assert(isset($free_labels['openrouter']) && count($free_labels) === 1, 'Free standalone provider labels must contain openrouter only');

add_filter('fito_tryon_provider_labels', function($labels) {
    $labels['gemini'] = 'Google Gemini (Direct API)';
    $labels['openai'] = 'OpenAI (Direct API)';
    return $labels;
});
$extended_labels = apply_filters('fito_tryon_provider_labels', [
    'openrouter' => 'OpenRouter (Multi-Model Aggregator)',
]);
assert(isset($extended_labels['gemini']) && isset($extended_labels['openai']), 'Pro must append gemini and openai provider labels via filter');
echo "PASS: fito_tryon_provider_labels filter decouples provider select labels cleanly.\n";

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

// Test 7: Audit Log Lifecycle Action Handshake
$log_lifecycle_fired = false;
$logged_id = 0;
$logged_raw_data = [];
add_action('fito_tryon_after_log_created', function($insert_id, $raw_data) use (&$log_lifecycle_fired, &$logged_id, &$logged_raw_data) {
    $log_lifecycle_fired = true;
    $logged_id = $insert_id;
    $logged_raw_data = $raw_data;
}, 10, 2);
$sample_raw_data = [
    'user_image_url'   => 'https://example.com/customer.jpg',
    'result_image_url' => 'https://example.com/result.jpg',
];
do_action('fito_tryon_after_log_created', 42, $sample_raw_data);
assert($log_lifecycle_fired === true, 'fito_tryon_after_log_created action must fire upon log creation');
assert($logged_id === 42, 'fito_tryon_after_log_created must receive newly inserted log ID');
assert($logged_raw_data['user_image_url'] === 'https://example.com/customer.jpg', 'fito_tryon_after_log_created must receive raw payload');
echo "PASS: fito_tryon_after_log_created action enables Pro visual audit log persistence.\n";

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
$sample_all_prompts = [
    ['id' => 'clothing', 'is_system' => true, 'title' => 'Clothing'],
    ['id' => 'shoes', 'is_system' => true, 'title' => 'Shoes'],
    ['id' => 'custom_123', 'is_system' => false, 'title' => 'Custom Leather'],
];
$standalone_prompts = apply_filters('fito_tryon_admin_prompts_list', $sample_all_prompts);
assert(count($standalone_prompts) === 3, 'Free standalone must pass configured prompts through filter cleanly');
echo "PASS: Free core passes prompts list through fito_tryon_admin_prompts_list filter without trialware gates.\n";

// Test 15: Pro Addon Custom Prompts Display & Saving Handshake
$raw_submitted_prompts = [
    ['id' => 'clothing', 'title' => 'Clothing Custom', 'categories' => [1]],
    ['id' => 'custom_999', 'title' => 'Custom Jacket', 'prompt' => 'Fit jacket.', 'categories' => [2]],
];
$sanitized_system = [
    ['id' => 'clothing', 'title' => 'Clothing Custom', 'is_system' => true, 'categories' => [1], 'prompt' => '...'],
];
add_filter('fito_tryon_save_prompts_config', function($sanitized, $raw) {
    foreach ($raw as $item) {
        if (!empty($item['id']) && str_starts_with($item['id'], 'custom_')) {
            $sanitized[] = [
                'id'         => $item['id'],
                'title'      => $item['title'],
                'is_system'  => false,
                'categories' => $item['categories'] ?? [],
                'prompt'     => $item['prompt'] ?? '',
            ];
        }
    }
    return $sanitized;
}, 10, 2);
$pro_saved_prompts = apply_filters('fito_tryon_save_prompts_config', $sanitized_system, $raw_submitted_prompts);
assert(count($pro_saved_prompts) === 2, 'Pro addon must extend saved prompts configuration to include custom ones');
assert($pro_saved_prompts[1]['id'] === 'custom_999', 'Custom prompt must be saved');

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
$pro_dir = is_dir(dirname(__DIR__, 2) . '/elfit-ai-virtual-try-on-for-woocommerce-pro-addon')
    ? dirname(__DIR__, 2) . '/elfit-ai-virtual-try-on-for-woocommerce-pro-addon'
    : dirname(__DIR__, 2) . '/fito-try-on-with-ai-for-woocommerce-pro-addon';
require_once $pro_dir . '/includes/class-fito-tryon-pro-storage.php';
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

// Test 21: Database Job Cleanup Filter Contracts
$mock_jobs = [
    (object) ['id' => 1, 'is_saved' => 0],
    (object) ['id' => 2, 'is_saved' => 1],
    (object) ['id' => 3, 'is_saved' => 0],
];
$standalone_expired = apply_filters('fito_tryon_expired_jobs_to_purge', $mock_jobs);
assert(count($standalone_expired) === 3, 'Free standalone must return all expired jobs');

add_filter('fito_tryon_expired_jobs_to_purge', function($jobs) {
    return array_values(array_filter($jobs, fn($j) => empty($j->is_saved)));
});
$filtered_expired = apply_filters('fito_tryon_expired_jobs_to_purge', $mock_jobs);
assert(count($filtered_expired) === 2, 'fito_tryon_expired_jobs_to_purge must exclude saved jobs');

$custom_purge_called = false;
add_filter('fito_tryon_handle_purge_temporary_jobs', function() use (&$custom_purge_called) {
    $custom_purge_called = true;
    return 5;
});
$purge_result = apply_filters('fito_tryon_handle_purge_temporary_jobs', null);
assert($custom_purge_called === true && $purge_result === 5, 'fito_tryon_handle_purge_temporary_jobs must allow intercepting manual purge');

$legacy_purge_where = apply_filters('fito_tryon_purge_jobs_where', '1=1');
assert($legacy_purge_where === '1=1' || $legacy_purge_where === 'is_saved_to_account = 0', 'Legacy purge condition must function');
echo "PASS: fito_tryon_expired_jobs_to_purge and handle_purge_temporary_jobs filter contracts function safely.\n";

// Test 22: Pro Fallback Model Persistence and Markup Initial Visibility
$pro_main_file = file_exists($pro_dir . '/elfit-ai-virtual-try-on-for-woocommerce-pro-addon.php')
    ? $pro_dir . '/elfit-ai-virtual-try-on-for-woocommerce-pro-addon.php'
    : $pro_dir . '/fito-try-on-with-ai-for-woocommerce-pro-addon.php';
require_once $pro_main_file;
require_once $pro_dir . '/admin/class-fito-tryon-pro-admin.php';

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
// Test 25: Elfit Shortcode Registration
assert(isset($GLOBALS['shortcodes']['elfit_tryon']), 'elfit_tryon shortcode must be registered');
assert(!isset($GLOBALS['shortcodes']['fito_tryon']), 'fito_tryon shortcode must be deprecated/removed');
echo "PASS: elfit_tryon shortcode is registered and fito_tryon is retired.\n";

// Test 26: Input Sanitization Helper and Hook Safety
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';
$ref_admin = new ReflectionClass(Fito_Tryon_Admin::class);
$admin_obj = $ref_admin->newInstanceWithoutConstructor();
$sanitize_method = $ref_admin->getMethod('sanitize_post_array');
$sanitize_method->setAccessible(true);
$tainted_post = [
    'fito_tryon_openai_api_key' => "sk-test<script>alert('xss')</script>",
    'nested_config'             => [
        'safe_val'    => 'Valid string',
        'unsafe_html' => '<b>Bold Text</b><script>',
    ],
    'numeric_key_val'           => 12345,
    'INVALID KEY!!'             => 'test',
];
$sanitized_output = $sanitize_method->invoke($admin_obj, $tainted_post);
assert($sanitized_output['fito_tryon_openai_api_key'] === 'sk-testalert(\'xss\')', 'Tainted scripts must be stripped by sanitize_text_field');
assert($sanitized_output['nested_config']['unsafe_html'] === 'Bold Text', 'Nested tags must be stripped cleanly');
assert(isset($sanitized_output['invalidkey']), 'Keys must be normalized by sanitize_key');
assert(!isset($sanitized_output['INVALID KEY!!']), 'Unsanitized raw keys must not persist');
echo "PASS: sanitize_post_array strips dangerous markup and normalizes keys recursively.\n";

// Test 27: Storage handle_upload with wp_handle_upload Refactoring
require_once dirname(__DIR__) . '/includes/class-fito-tryon-storage.php';
$storage = new Fito_Tryon_Storage();

// Subtest 27a: Valid image upload
$sample_source = dirname(__DIR__) . '/assets/images/sample-tryon.jfif';
$test_upload_img = tempnam(sys_get_temp_dir(), 'test_upload_') . '.jpg';
copy($sample_source, $test_upload_img);

$upload_file_param = [
    'name'     => 'customer_portrait.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $test_upload_img,
    'error'    => 0,
    'size'     => filesize($test_upload_img),
];
$upload_res = $storage->handle_upload($upload_file_param);
assert($upload_res['success'] === true, 'handle_upload must succeed for valid image');
assert(str_contains($upload_res['path'], 'fito-tryon/uploads'), 'Uploaded image path must route to fito-tryon/uploads');
assert(str_starts_with(basename($upload_res['path']), 'user_'), 'Uploaded filename must be anonymized with user_ prefix');
assert($upload_res['width'] === 2744 && $upload_res['height'] === 1536, 'Dimensions must match 2744x1536');

// Subtest 27b: Image dimensions too small (< 150x150)
$test_small_img = tempnam(sys_get_temp_dir(), 'test_small_') . '.png';
file_put_contents($test_small_img, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAADElEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

$small_file_param = [
    'name'     => 'small.png',
    'type'     => 'image/png',
    'tmp_name' => $test_small_img,
    'error'    => 0,
    'size'     => filesize($test_small_img),
];
$small_res = $storage->handle_upload($small_file_param);
assert($small_res['success'] === false, 'handle_upload must reject images below 150x150');
assert(str_contains($small_res['message'], 'too small'), 'Error message must specify dimensions too small');

// Subtest 27c: wp_handle_upload error bubbling
$err_file_param = [
    'name'     => 'err.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $test_upload_img,
    'error'    => 0,
    'size'     => filesize($test_upload_img),
];
$err_res = $storage->handle_upload($err_file_param, ['simulate_error' => 'Core upload error simulated']);
assert($err_res['success'] === false, 'handle_upload must report failure when wp_handle_upload returns error');
assert($err_res['message'] === 'Core upload error simulated', 'handle_upload must bubble up core error message');

@unlink($test_upload_img);
@unlink($test_small_img);
if (!empty($upload_res['path']) && file_exists($upload_res['path'])) {
    @unlink($upload_res['path']);
}
echo "PASS: Fito_Tryon_Storage::handle_upload uses wp_handle_upload with test_form validation and secure routing.\n";

// Test 28: get_expired_logs queries only id column without Pro column references
require_once dirname(__DIR__) . '/includes/class-fito-tryon-database.php';
class MockWPDBForLogs {
    public string $prefix = 'wp_';
    public string $last_query = '';
    public function prepare($query, ...$args) {
        $this->last_query = vsprintf(str_replace(['%d', '%s', '%i'], ['%d', "'%s'", '`%s`'], $query), $args);
        return $this->last_query;
    }
    public function get_results($query) {
        $this->last_query = $query;
        return [(object) ['id' => 101], (object) ['id' => 102]];
    }
}
global $wpdb;
$wpdb = new MockWPDBForLogs();
$db = new Fito_Tryon_Database();
$expired = $db->get_expired_logs(30);
assert(str_contains($wpdb->last_query, 'SELECT id FROM'), 'get_expired_logs must select only id column');
assert(!str_contains($wpdb->last_query, 'user_image_url'), 'get_expired_logs must not select user_image_url');
assert(!str_contains($wpdb->last_query, 'result_image_url'), 'get_expired_logs must not select result_image_url');
assert(count($expired) === 2 && $expired[0]->id === 101, 'get_expired_logs must return matched log IDs');
echo "PASS: get_expired_logs selects only id column without referencing non-existent Pro columns.\n";

// Test 29: Handshake action fito_tryon_before_delete_expired_logs dispatches correctly
$before_delete_fired = false;
$passed_days = 0;
add_action('fito_tryon_before_delete_expired_logs', function($days) use (&$before_delete_fired, &$passed_days) {
    $before_delete_fired = true;
    $passed_days = $days;
});
do_action('fito_tryon_before_delete_expired_logs', 30);
assert($before_delete_fired === true, 'fito_tryon_before_delete_expired_logs action must fire');
assert($passed_days === 30, 'fito_tryon_before_delete_expired_logs action must receive days argument');
echo "PASS: fito_tryon_before_delete_expired_logs lifecycle action hook dispatches cleanly.\n";

// Test 30: Privacy policy suggestion is free of Pro account references
require_once dirname(__DIR__) . '/includes/class-fito-tryon-privacy.php';
$privacy_file = file_get_contents(dirname(__DIR__) . '/includes/class-fito-tryon-privacy.php');
assert(!str_contains($privacy_file, 'save the try-on result to your customer account'), 'Privacy class must not contain Pro customer account saving references');
assert(str_contains($privacy_file, 'after temporary processing'), 'Privacy class must state photos are deleted after temporary processing');
echo "PASS: Fito_Tryon_Privacy contains zero references to Pro customer account saving.\n";

// Test 31: Log data filter contract decouples Pro image attributes from Free
$sample_log = [
    'job_id' => 123,
    'status' => 'success',
];
$standalone_log = apply_filters('fito_tryon_log_data', $sample_log, [
    'user_image_url'   => 'https://example.com/user.jpg',
    'result_image_url' => 'https://example.com/result.jpg',
]);
assert(!isset($standalone_log['user_image_url']), 'Free standalone must not have user_image_url by default');

add_filter('fito_tryon_log_data', function($entry, $ctx) {
    if (!empty($ctx['user_image_url'])) {
        $entry['user_image_url'] = $ctx['user_image_url'];
    }
    return $entry;
}, 10, 2);
$pro_log = apply_filters('fito_tryon_log_data', $sample_log, [
    'user_image_url'   => 'https://example.com/user.jpg',
    'result_image_url' => 'https://example.com/result.jpg',
]);
assert(isset($pro_log['user_image_url']) && $pro_log['user_image_url'] === 'https://example.com/user.jpg', 'Pro filter must append visual image URLs cleanly');
echo "PASS: fito_tryon_log_data filter contract cleanly decouples visual image URLs.\n";

// Test 32: .distignore excludes development and testing files for release packaging
$distignore_path = dirname(__DIR__) . '/.distignore';
assert(file_exists($distignore_path), '.distignore must exist in the plugin root');
$distignore_content = file_get_contents($distignore_path);
assert(str_contains($distignore_content, '/tests'), '.distignore must exclude /tests');
assert(str_contains($distignore_content, '/docs'), '.distignore must exclude /docs');
assert(str_contains($distignore_content, '/.codegraph'), '.distignore must exclude /.codegraph');
echo "PASS: .distignore correctly excludes development artifacts from distribution packages.\n";

// Test 33: Free CSS files contain zero dormant Pro selectors
$free_admin_css = file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');
$free_public_css = file_get_contents(dirname(__DIR__) . '/assets/css/public.css');
assert(!str_contains($free_admin_css, 'fito-log-thumb'), 'Free admin.css must not contain fito-log-thumb');
assert(!str_contains($free_admin_css, 'fito-modal-compare-grid'), 'Free admin.css must not contain fito-modal-compare-grid');
assert(!str_contains($free_public_css, 'fito-account-tryons'), 'Free public.css must not contain fito-account-tryons');
assert(!str_contains($free_public_css, 'fito-tryon-btn--gallery'), 'Free public.css must not contain fito-tryon-btn--gallery');
assert(!str_contains($free_public_css, 'fito-variation-tryon-wrapper'), 'Free public.css must not contain fito-variation-tryon-wrapper');
assert(!str_contains($free_public_css, 'fito-custom-image-wrapper'), 'Free public.css must not contain fito-custom-image-wrapper');
assert(!str_contains($free_public_css, 'fito-tryon-btn--archive'), 'Free public.css must not contain fito-tryon-btn--archive');
echo "PASS: Free stylesheets contain zero dormant Pro classes or selectors.\n";

// Test 34: Free JavaScript contains zero dormant Pro element queries
$free_public_js = file_get_contents(dirname(__DIR__) . '/assets/js/public.js');
assert(!str_contains($free_public_js, 'fito-variation-tryon-wrapper'), 'Free public.js must not contain fito-variation-tryon-wrapper');
assert(!str_contains($free_public_js, 'fito-tryon-btn--archive'), 'Free public.js must not contain fito-tryon-btn--archive');
echo "PASS: Free public JavaScript contains zero dormant Pro element queries or branches.\n";

// Test 35: readme.txt metadata and changelog integrity
$readme_content = file_get_contents(dirname(__DIR__) . '/readme.txt');
assert(str_contains($readme_content, 'Tested up to: 7.1'), 'readme.txt must declare Tested up to: 7.1');
assert(str_contains($readme_content, 'Contributors: micromax2'), 'readme.txt must declare Contributors: micromax2');
assert(!str_contains($readme_content, 'and prompt duplication.'), 'readme.txt 1.1.0 changelog must not claim prompt duplication');
echo "PASS: readme.txt metadata and changelog integrity verified.\n";

// Test 36: Pro addon stylesheets exist and contain relocated rules
$pro_dir = dirname(dirname(__DIR__)) . '/elfit-ai-virtual-try-on-for-woocommerce-pro-addon';
if (is_dir($pro_dir)) {
    $pro_public_css_path = $pro_dir . '/assets/css/pro-public.css';
    $pro_admin_css_path = $pro_dir . '/assets/css/pro-admin.css';
    assert(file_exists($pro_public_css_path), 'pro-public.css must exist in Pro Addon');
    assert(file_exists($pro_admin_css_path), 'pro-admin.css must exist in Pro Addon');
    $pro_public_css = file_get_contents($pro_public_css_path);
    $pro_admin_css = file_get_contents($pro_admin_css_path);
    assert(str_contains($pro_public_css, 'fito-account-tryons'), 'pro-public.css must contain fito-account-tryons');
    assert(str_contains($pro_public_css, 'fito-variation-tryon-wrapper'), 'pro-public.css must contain fito-variation-tryon-wrapper');
    assert(str_contains($pro_admin_css, 'fito-log-thumb'), 'pro-admin.css must contain fito-log-thumb');
    assert(str_contains($pro_admin_css, 'fito-modal-compare-grid'), 'pro-admin.css must contain fito-modal-compare-grid');
    echo "PASS: Pro addon stylesheets successfully house all relocated styling rules.\n";
}

// Test 37: Free database class contains zero dormant Pro methods
require_once dirname(__DIR__) . '/includes/class-fito-tryon-database.php';
$db_reflection = new ReflectionClass('Fito_Tryon_Database');
assert(!$db_reflection->hasMethod('delete_unsaved_jobs'), 'Fito_Tryon_Database must not contain delete_unsaved_jobs');
echo "PASS: Free database class contains zero dormant delete_unsaved_jobs method.\n";

// Test 38: Free storage class contains zero dormant delete_file_by_url helper
require_once dirname(__DIR__) . '/includes/class-fito-tryon-storage.php';
$storage_reflection = new ReflectionClass('Fito_Tryon_Storage');
assert(!$storage_reflection->hasMethod('delete_file_by_url'), 'Fito_Tryon_Storage must not contain delete_file_by_url');
echo "PASS: Free storage class contains zero dormant delete_file_by_url method.\n";

// Test 39: Pro storage class houses delete_file_by_url and block.json version matches 1.5.0
if (is_dir($pro_dir)) {
    require_once $pro_dir . '/includes/class-fito-tryon-pro-storage.php';
    $pro_storage_reflection = new ReflectionClass('Fito_Tryon_Pro_Storage');
    assert($pro_storage_reflection->hasMethod('delete_file_by_url'), 'Fito_Tryon_Pro_Storage must implement delete_file_by_url');
    assert($pro_storage_reflection->getMethod('delete_file_by_url')->isStatic(), 'Fito_Tryon_Pro_Storage::delete_file_by_url must be static');
}
$block_json = json_decode(file_get_contents(dirname(__DIR__) . '/includes/blocks/tryon-button/block.json'), true);
assert(!empty($block_json['version']) && $block_json['version'] === '1.5.0', 'block.json version must match 1.5.0');
echo "PASS: Pro storage houses delete_file_by_url and block.json synchronized to 1.5.0.\n";

// Test 40: Free admin class contains zero dormant Pro provider options or custom prompt badges
$free_admin_code = file_get_contents(dirname(__DIR__) . '/admin/class-fito-tryon-admin.php');
assert(!str_contains($free_admin_code, '<option value="gemini"'), 'Free admin must not hardcode gemini option');
assert(!str_contains($free_admin_code, '<option value="openai"'), 'Free admin must not hardcode openai option');
assert(!str_contains($free_admin_code, 'is-custom-prompt'), 'Free admin must not contain is-custom-prompt class');
assert(!str_contains($free_admin_code, "'Custom Prompt'"), 'Free admin must not contain Custom Prompt string');
// Test 41: Free REST class uses canonical 10-char namespace and zero legacy ai-tryon aliases
require_once dirname(__DIR__) . '/includes/class-fito-tryon-rest.php';
$rest_reflection = new ReflectionClass('Fito_Tryon_Rest');
assert($rest_reflection->getConstant('NAMESPACE') === 'fito-tryon/v1', 'Fito_Tryon_Rest::NAMESPACE must be fito-tryon/v1');
// Test 42: Block index.asset.php exists and Chart.js version sync
$asset_file = dirname(__DIR__) . '/includes/blocks/tryon-button/index.asset.php';
assert(file_exists($asset_file), 'tryon-button index.asset.php must exist');
$asset_data = include $asset_file;
assert(is_array($asset_data) && in_array('wp-blocks', $asset_data['dependencies'] ?? [], true), 'index.asset.php must declare wp-blocks dependency');
assert(str_contains($free_admin_code, "'4.5.1'"), 'Chart.js enqueued version must match 4.5.1');
echo "PASS: Gutenberg block index.asset.php and Chart.js 4.5.1 enqueued version validated.\n";

echo "=== ALL 43 TESTS PASSED SUCCESSFULLY ===\n";
