<?php
/**
 * Automated Unit Tests for Log Details Prompt and Response Persistence and Error Logging
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== Running Log Details Prompt and Response Tests ===\n";

global $wpdb;
$logs_table = $wpdb->prefix . 'fito_tryon_logs';

// 1. Trigger maybe_upgrade_schema to ensure columns are migrated
Fito_Tryon_Database::maybe_upgrade_schema();
$db_version = get_option('fito_tryon_db_version');
assert($db_version === Fito_Tryon_Database::DB_VERSION, "Failed: DB version should be " . Fito_Tryon_Database::DB_VERSION);
echo "- Testing Schema Upgrade to " . Fito_Tryon_Database::DB_VERSION . "... PASSED\n";

// Verify columns exist in the database table
$columns = $wpdb->get_col("DESCRIBE {$logs_table}", 0);
assert(in_array('prompt_text', $columns, true), "Failed: prompt_text column must exist in {$logs_table}");
assert(in_array('response_text', $columns, true), "Failed: response_text column must exist in {$logs_table}");
echo "- Testing Database Columns Existence (prompt_text, response_text)... PASSED\n";

$db = new Fito_Tryon_Database();

// 2. Test inserting a successful try-on with prompt and response
$test_prompt = "Perform a virtual fitting of the apparel 'Classic Denim Jacket' on the customer. Maintain natural fabric folds.";
$test_response = "Generated photorealistic try-on preview successfully. Preserved customer pose and garment drape.";

$log_id_1 = $db->add_log([
    'job_id'            => 101,
    'user_id'           => 1,
    'ip_address'        => '127.0.0.1',
    'product_id'        => 50,
    'variation_id'      => 0,
    'model_used'        => 'google/gemini-2.5-flash-image',
    'status'            => 'success',
    'duration_sec'      => 2.45,
    'prompt_tokens'     => 120,
    'completion_tokens' => 350,
    'cost_estimate'     => 0.0035,
    'prompt_text'       => $test_prompt,
    'response_text'     => $test_response,
]);

assert($log_id_1 > 0, "Failed: Log entry 1 insertion failed");

$fetched_log_1 = $db->get_log($log_id_1);
assert($fetched_log_1 !== null, "Failed: Could not retrieve log entry 1");
assert($fetched_log_1->prompt_text === $test_prompt, "Failed: prompt_text does not match expected string");
assert($fetched_log_1->response_text === $test_response, "Failed: response_text does not match expected string");
echo "- Testing Log Insertion & Retrieval with Prompt & Response... PASSED\n";

// 3. Test logging error when AI model failed to generate an image
$error_prompt = "Perform virtual fitting for 'Silk Evening Gown' on Image 1.";
$model_text_response = "I can see the customer in Image 1 and the dress in Image 2. However, due to lighting reflections, I cannot reconstruct the garment accurately.";
$error_message = "The AI model responded with text but was unable to generate an image.";

$log_id_2 = $db->add_log([
    'job_id'            => 102,
    'user_id'           => 2,
    'ip_address'        => '192.168.1.100',
    'product_id'        => 75,
    'variation_id'      => 0,
    'model_used'        => 'openai/gpt-image-2.5-flare',
    'status'            => 'failed',
    'duration_sec'      => 3.12,
    'prompt_tokens'     => 150,
    'completion_tokens' => 85,
    'cost_estimate'     => 0.0020,
    'error_message'     => $error_message,
    'prompt_text'       => $error_prompt,
    'response_text'     => $model_text_response,
]);

$fetched_log_2 = $db->get_log($log_id_2);
assert($fetched_log_2 !== null, "Failed: Could not retrieve log entry 2");
assert($fetched_log_2->status === 'failed', "Failed: Status should be failed");
assert($fetched_log_2->error_message === $error_message, "Failed: Error message mismatch");
assert($fetched_log_2->prompt_text === $error_prompt, "Failed: Error prompt_text mismatch");
assert($fetched_log_2->response_text === $model_text_response, "Failed: Error response_text mismatch");
echo "- Testing Failed Image Generation Logging (Model Text Fallback Failure)... PASSED\n";

// 4. Test JSON payload preserving in response_text
$raw_json_response = [
    'error' => [
        'code'    => 'content_policy_violation',
        'message' => 'The prompt or input image triggered safety filters.',
        'details' => ['category' => 'HATE_SPEECH', 'severity' => 'LOW'],
    ],
    'usage' => [
        'prompt_tokens'     => 95,
        'completion_tokens' => 0,
    ],
];

$log_id_3 = $db->add_log([
    'job_id'            => 103,
    'user_id'           => 0,
    'ip_address'        => '10.0.0.5',
    'product_id'        => 90,
    'variation_id'      => 0,
    'model_used'        => 'gemini-2.5-flash-image',
    'status'            => 'failed',
    'duration_sec'      => 0.85,
    'error_message'     => 'The prompt or input image triggered safety filters.',
    'prompt_text'       => "Fit 'Casual Hoodie' on user.",
    'response_text'     => $raw_json_response,
]);

$fetched_log_3 = $db->get_log($log_id_3);
assert($fetched_log_3 !== null, "Failed: Could not retrieve log entry 3");
$decoded_response = json_decode($fetched_log_3->response_text, true);
assert(is_array($decoded_response), "Failed: response_text should be valid decoded JSON");
assert($decoded_response['error']['code'] === 'content_policy_violation', "Failed: JSON code mismatch in response_text");
echo "- Testing JSON Payload Preservation in response_text... PASSED\n";

// 5. Test Fito_Tryon_OpenRouter::sanitize_json_response with base64 stripping
$mock_raw_payload = [
    'id' => 'gen-123456789',
    'model' => 'x-ai/grok-imagine-image-2.0',
    'choices' => [
        [
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => '',
                'images' => [
                    [
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . str_repeat('A', 800),
                        ],
                    ],
                ],
            ],
            'finish_reason' => 'stop',
        ],
    ],
    'usage' => [
        'prompt_tokens' => 450,
        'completion_tokens' => 120,
    ],
];

$sanitized_json = Fito_Tryon_OpenRouter::sanitize_json_response($mock_raw_payload);
assert(!empty($sanitized_json), "Failed: sanitize_json_response returned empty string");
assert(!str_contains($sanitized_json, str_repeat('A', 100)), "Failed: Large base64 data was not stripped");
assert(str_contains($sanitized_json, '[BASE64_IMAGE_DATA_OMITTED'), "Failed: Replacement placeholder missing");
assert(str_contains($sanitized_json, 'x-ai/grok-imagine-image-2.0'), "Failed: Model metadata omitted");
assert(str_contains($sanitized_json, '"prompt_tokens": 450'), "Failed: Usage metadata omitted");
echo "- Testing sanitize_json_response Base64 Stripping & Envelope Preservation... PASSED\n";

// 6. Test add_log auto-fallback when response_text is empty and error_message is provided
$log_id_4 = $db->add_log([
    'job_id'            => 104,
    'user_id'           => 1,
    'ip_address'        => '127.0.0.1',
    'product_id'        => 88,
    'variation_id'      => 0,
    'model_used'        => 'x-ai/grok-imagine-image-2.0',
    'status'            => 'failed',
    'duration_sec'      => 1.2,
    'error_message'     => 'Unable to generate try-on. Check your connection and try again.',
    'prompt_text'       => 'Test prompt',
    'response_text'     => '', // intentionally empty
]);

$fetched_log_4 = $db->get_log($log_id_4);
assert($fetched_log_4 !== null, "Failed: Could not retrieve log entry 4");
assert(!empty($fetched_log_4->response_text), "Failed: response_text should not be empty when error_message is provided");
$decoded_fallback = json_decode($fetched_log_4->response_text, true);
assert(is_array($decoded_fallback) && isset($decoded_fallback['error']), "Failed: response_text must be valid JSON with error field");
assert(str_contains($decoded_fallback['error'], 'Unable to generate try-on'), "Failed: fallback error mismatch");
// 7. Test Specific Error Classification (Moderation, Undergarment, Invalid Model)
$plugin = Fito_Tryon::instance();
$openrouter = $plugin->openrouter;
$reflect = new ReflectionClass($openrouter);

// Test 7a: xAI content moderation inside metadata.raw
$xai_body = [
    'error' => [
        'message' => 'Provider returned error',
        'code'    => 400,
        'metadata'=> [
            'raw'           => '{"code":"imagine:content-moderated","error":"Generated image rejected by content moderation.","usage":{"cost_in_usd_ticks":800000000}}',
            'provider_name' => 'xAI',
            'is_byok'       => false,
        ],
    ],
];
$parse_method = $reflect->getMethod('parse_error_code');
$parse_method->setAccessible(true);
$shopper_msg_xai = $parse_method->invoke($openrouter, 400, $xai_body);
assert(str_contains($shopper_msg_xai, 'content safety/moderation policies'), "Failed: xAI content moderation message mismatch");

$raw_extract_method = $reflect->getMethod('extract_raw_error_message');
$raw_extract_method->setAccessible(true);
$raw_xai_err = $raw_extract_method->invoke($openrouter, $xai_body);
assert($raw_xai_err === 'Generated image rejected by content moderation.', "Failed: xAI raw error extraction mismatch");
echo "- Testing xAI Nested Content Moderation Error Parsing... PASSED\n";

// Test 7b: Invalid Model ID
$invalid_model_body = [
    'error' => [
        'message' => 'black-forest-labs/flux-1-schnell is not a valid model ID',
        'code'    => 400,
    ],
];
$shopper_msg_model = $parse_method->invoke($openrouter, 400, $invalid_model_body);
assert(str_contains($shopper_msg_model, 'temporarily unavailable'), "Failed: Invalid model ID shopper message mismatch");
echo "- Testing Invalid Model ID User-Friendly Masking... PASSED\n";

// Test 7c: Undergarment / Intimate Apparel Refusal
$refusal_text = "I cannot fulfill this request. The product in Image 2 is an undergarment that is not suitable for being worn as an outer garment or being visibly displayed on the person in Image 1.";
$is_undergarment = str_contains(strtolower($refusal_text), 'undergarment');
assert($is_undergarment === true, "Failed: Undergarment detection failed");
echo "- Testing Undergarment Category Refusal Detection... PASSED\n";

// 8. Test get_logs list retrieval with search
$logs = $db->get_logs(['limit' => 5]);
assert(!empty($logs), "Failed: get_logs should return records");
$found_log_1 = false;
foreach ($logs as $l) {
    if ((int) $l->id === $log_id_1) {
        $found_log_1 = true;
        assert($l->prompt_text === $test_prompt, "Failed: List prompt_text mismatch");
        assert($l->response_text === $test_response, "Failed: List response_text mismatch");
        break;
    }
}
assert($found_log_1, "Failed: Created log entry was not found in get_logs list");
echo "- Testing List Query with prompt_text and response_text... PASSED\n";

// Clean up test records
$db->delete_log($log_id_1);
$db->delete_log($log_id_2);
$db->delete_log($log_id_3);
$db->delete_log($log_id_4);

assert($db->get_log($log_id_1) === null, "Failed: Log 1 should be deleted");
echo "- Testing Cleanup... PASSED\n";

echo "\n[PASS] All Log Details Prompt & Response Tests Completed Successfully!\n";
