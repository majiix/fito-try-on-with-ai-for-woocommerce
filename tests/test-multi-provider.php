<?php
/**
 * Automated Unit Tests for Multi-Provider AI Architecture & Provider Neutrality.
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Multi-Provider AI & Text Neutrality Tests ===\n";

$plugin = Fito_Tryon::instance();

// Test 1: Provider-Aware Active Model Resolution
update_option('fito_tryon_ai_provider', 'openrouter');
update_option('fito_tryon_primary_model', 'google/gemini-2.5-flash-image');
assert($plugin->get_active_provider() === 'openrouter', 'Failed: active provider should be openrouter');
assert($plugin->get_active_primary_model() === 'google/gemini-2.5-flash-image', 'Failed: active primary model should be openrouter model');

update_option('fito_tryon_ai_provider', 'gemini');
update_option('fito_tryon_gemini_primary_model', 'gemini-3.1-flash-image');
assert($plugin->get_active_provider() === 'gemini', 'Failed: active provider should be gemini');
assert($plugin->get_active_primary_model() === 'gemini-3.1-flash-image', 'Failed: active primary model should be gemini model');

update_option('fito_tryon_ai_provider', 'openai');
update_option('fito_tryon_openai_primary_model', 'gpt-image-2.5-flare');
assert($plugin->get_active_provider() === 'openai', 'Failed: active provider should be openai');
assert($plugin->get_active_primary_model() === 'gpt-image-2.5-flare', 'Failed: active primary model should be openai model');

echo "- Testing Provider-Aware Active Model Resolution... PASSED\n";

// Test 2: Privacy Policy Provider Neutrality
$privacy = $plugin->privacy;
assert(method_exists($privacy, 'add_privacy_policy_content'), 'Failed: add_privacy_policy_content method missing');
$consent_text = Fito_Tryon_Privacy::get_consent_text();
assert(!str_contains(strtolower($consent_text), 'openrouter'), 'Failed: Consent text must not mention openrouter specifically');
echo "- Testing Privacy Policy Provider Neutrality... PASSED\n";

// Test 3: REST API Model Resolution Endpoints
$rest = $plugin->rest;
$request_gemini = new WP_REST_Request('GET', '/ai-tryon/v1/models');
$request_gemini->set_param('provider', 'gemini');
$response_gemini = $rest->handle_get_models($request_gemini);
assert($response_gemini->get_status() === 200, 'Failed: Gemini models endpoint should return 200');
$data_gemini = $response_gemini->get_data();
assert($data_gemini['provider'] === 'gemini', 'Failed: Response provider should be gemini');
assert(isset($data_gemini['models']) && is_array($data_gemini['models']), 'Failed: Models must be an array');

$request_openai = new WP_REST_Request('GET', '/ai-tryon/v1/models');
$request_openai->set_param('provider', 'openai');
$response_openai = $rest->handle_get_models($request_openai);
assert($response_openai->get_status() === 200, 'Failed: OpenAI models endpoint should return 200');
$data_openai = $response_openai->get_data();
assert($data_openai['provider'] === 'openai', 'Failed: Response provider should be openai');

echo "- Testing REST API Multi-Provider Model Endpoints... PASSED\n";

// Reset to default provider
update_option('fito_tryon_ai_provider', 'openrouter');

echo "\n[PASS] All Multi-Provider AI & Text Neutrality Tests Completed Successfully!\n";
