<?php
/**
 * Direct Google Gemini API integration for virtual try-on.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Gemini {
    public const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
    public const CACHE_KEY_MODELS = 'fito_tryon_gemini_models_cache';

    private Fito_Tryon $plugin;

    public function __construct(Fito_Tryon $plugin) {
        $this->plugin = $plugin;
    }

    public function get_api_key(): string {
        return trim((string) get_option('fito_tryon_gemini_api_key', ''));
    }

    public function get_primary_model(): string {
        return (string) get_option('fito_tryon_gemini_primary_model', 'gemini-2.5-flash-image');
    }

    public function get_fallback_model(): string {
        if (!Fito_Tryon::is_pro_active()) {
            return '';
        }
        return (string) get_option('fito_tryon_gemini_fallback_model', 'gemini-3.1-flash-image');
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    public function get_curated_models(): array {
        return [
            [
                'id'         => 'gemini-2.5-flash-image',
                'name'       => 'Google: Nano Banana (Gemini 2.5 Flash Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-image',
                'name'       => 'Google: Nano Banana 2 (Gemini 3.1 Flash Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-image-preview',
                'name'       => 'Google: Nano Banana 2 (Gemini 3.1 Flash Image Preview)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3.1-flash-lite-image',
                'name'       => 'Google: Nano Banana 2 Lite (Gemini 3.1 Flash Lite Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3-pro-image',
                'name'       => 'Google: Nano Banana Pro (Gemini 3 Pro Image)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gemini-3-pro-image-preview',
                'name'       => 'Google: Nano Banana Pro (Gemini 3 Pro Image Preview)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'imagen-3.0-generate-002',
                'name'       => 'Google: Imagen 3.0 Generate (Legacy)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
        ];
    }

    public function test_connection(?string $api_key = null): array {
        $key = $api_key !== null ? trim($api_key) : $this->get_api_key();
        if (empty($key)) {
            return [
                'success' => false,
                'message' => __('Google Gemini API key is empty.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $url = self::API_BASE . '/models';
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $key,
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                // translators: %s: Error message
                'message' => sprintf(__('Connection failed: %s', 'fito-try-on-with-ai-for-woocommerce'), $response->get_error_message()),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 200 && isset($body['models'])) {
            $count = count($body['models']);
            return [
                'success' => true,
                // translators: %d: Number of available AI models
                'message' => sprintf(__('Connection verified! Gemini API connected (%d models available).', 'fito-try-on-with-ai-for-woocommerce'), $count),
            ];
        }

        $err_msg = $body['error']['message'] ?? __('Authentication failed. Check your Gemini API key.', 'fito-try-on-with-ai-for-woocommerce');
        return [
            'success' => false,
            'message' => $err_msg,
        ];
    }

    public function get_cached_models(): array {
        $cached = get_transient(self::CACHE_KEY_MODELS);
        return (!empty($cached) && is_array($cached)) ? $cached : [];
    }

    public function clear_models_cache(): bool {
        return delete_transient(self::CACHE_KEY_MODELS);
    }

    public function fetch_live_models(?string $api_key = null, bool $force_refresh = false): array {
        if ($force_refresh) {
            $this->clear_models_cache();
        } else {
            $cached = $this->get_cached_models();
            if (!empty($cached)) {
                return $cached;
            }
        }

        $key = !empty($api_key) ? trim($api_key) : $this->get_api_key();
        $models = $this->get_curated_models();

        if (!empty($key)) {
            $url = self::API_BASE . '/models';
            $response = wp_remote_get($url, [
                'timeout' => 20,
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => $key,
                ],
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $api_models = $body['models'] ?? [];
                if (is_array($api_models)) {
                    $seen = [];
                    foreach ($models as $m) {
                        $seen[$m['id']] = true;
                    }
                    foreach ($api_models as $item) {
                        $name = $item['name'] ?? '';
                        $clean_id = str_replace('models/', '', $name);
                        $display_name = $item['displayName'] ?? $clean_id;
                        $desc = $item['description'] ?? '';
                        $lower = strtolower($clean_id . ' ' . $display_name . ' ' . $desc);

                        $is_image = str_contains($lower, 'image') || str_contains($lower, 'imagen') || str_contains($lower, 'banana');
                        if (str_contains($clean_id, 'embedding') || str_contains($clean_id, 'aqa') || str_contains($clean_id, 'text-')) {
                            $is_image = false;
                        }

                        if ($is_image && empty($seen[$clean_id])) {
                            $seen[$clean_id] = true;
                            $formatted_name = str_starts_with($display_name, 'Google:') ? $display_name : ('Google: ' . $display_name);
                            $models[] = [
                                'id'         => $clean_id,
                                'name'       => $formatted_name . ' (' . $clean_id . ')',
                                'type'       => 'image_gen',
                                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
                            ];
                        }
                    }
                }
            }
        }

        usort($models, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        if (!empty($key)) {
            set_transient(self::CACHE_KEY_MODELS, $models, DAY_IN_SECONDS);
        }
        return $models;
    }

    public function generate_tryon(array $params): array {
        if (!Fito_Tryon::is_pro_active()) {
            return [
                'success' => false,
                'message' => __('Google Gemini direct integration requires Fito Try-On Pro.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Google Gemini API key is not configured in settings.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $primary_model = $this->get_primary_model();
        $fallback_model = $this->get_fallback_model();

        $start_time = microtime(true);
        $result = $this->call_gemini($primary_model, $params, $api_key, false);

        if (!$result['success'] && !empty($fallback_model) && $fallback_model !== $primary_model) {
            $result = $this->call_gemini($fallback_model, $params, $api_key, true);
        }

        $result['duration'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    private function call_gemini(string $model, array $params, string $api_key, bool $is_fallback): array {
        $prompt = $this->plugin->openrouter->build_category_prompt($params);
        $user_image_b64 = $this->plugin->openrouter->image_to_base64($params['user_image_path'] ?? '');
        $product_image_url = $params['product_image_url'] ?? '';
        $product_image_id = (int) ($params['product_image_id'] ?? 0);
        $product_image_b64 = $this->plugin->openrouter->resolve_image_base64($product_image_url, $product_image_id);

        if (empty($user_image_b64)) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => __('Missing or unreadable user photo.', 'fito-try-on-with-ai-for-woocommerce')]),
                'message'       => __('Missing or unreadable user photo.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $clean_model = str_replace('models/', '', $model);
        if (str_starts_with($clean_model, 'imagen')) {
            return $this->call_imagen_predict($clean_model, $prompt, $api_key, $is_fallback);
        }

        return $this->call_gemini_content($clean_model, $prompt, $user_image_b64, $product_image_b64, $api_key, $is_fallback);
    }

    private function call_gemini_content(string $model, string $prompt, string $user_b64, string $prod_b64, string $api_key, bool $is_fallback): array {
        $url = self::API_BASE . '/models/' . $model . ':generateContent';
        $parts = [];

        $system_instruction = get_option('fito_tryon_system_prompt');
        if (empty($system_instruction)) {
            $system_instruction = Fito_Tryon_OpenRouter::get_default_system_prompt();
        }

        $full_prompt = $system_instruction . "\n\nTask: " . $prompt;
        $parts[] = ['text' => $full_prompt];

        $user_inline = $this->extract_inline_data($user_b64);
        if (!empty($user_inline)) {
            $parts[] = ['inline_data' => $user_inline];
        }

        $prod_inline = $this->extract_inline_data($prod_b64);
        if (!empty($prod_inline)) {
            $parts[] = ['inline_data' => $prod_inline];
        }

        $payload = [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE', 'TEXT'],
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $api_key,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => $response->get_error_message()]),
                'message'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            // translators: %d: HTTP status code
            $http_err = sprintf(__('Gemini API error (HTTP %d)', 'fito-try-on-with-ai-for-woocommerce'), $code);
            $err = $body['error']['message'] ?? $http_err;
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => $raw_body, 'http_code' => $code]),
                'message'       => $err,
            ];
        }

        $candidates = $body['candidates'][0]['content']['parts'] ?? [];
        $image_data = null;
        $text_desc = '';

        foreach ($candidates as $part) {
            if (isset($part['inlineData']['data'])) {
                $mime = $part['inlineData']['mimeType'] ?? 'image/png';
                $image_data = 'data:' . $mime . ';base64,' . $part['inlineData']['data'];
                break;
            }
            if (isset($part['text'])) {
                $text_desc = $part['text'];
            }
        }

        $prompt_tokens = (int) ($body['usageMetadata']['promptTokenCount'] ?? 0);
        $completion_tokens = (int) ($body['usageMetadata']['candidatesTokenCount'] ?? 0);

        if (empty($image_data)) {
            return [
                'success'           => false,
                'model'             => $model,
                'is_fallback'       => $is_fallback,
                'prompt'            => $full_prompt,
                'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => 'Gemini model responded with text but did not return image data.', 'raw' => $raw_body]),
                'prompt_tokens'     => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'cost'              => 0.02,
                'message'           => __('Gemini model responded with text but did not return image data.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        return [
            'success'           => true,
            'model'             => $model,
            'is_fallback'       => $is_fallback,
            'result_image_data' => $image_data,
            'description'       => $text_desc,
            'prompt'            => $full_prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
            'prompt_tokens'     => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'cost'              => 0.02,
        ];
    }

    private function call_imagen_predict(string $model, string $prompt, string $api_key, bool $is_fallback): array {
        $url = self::API_BASE . '/models/' . $model . ':predict';
        $payload = [
            'instances'  => [
                ['prompt' => $prompt],
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => '1:1',
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $api_key,
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => $response->get_error_message()]),
                'message'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            // translators: %d: HTTP status code
            $http_err = sprintf(__('Imagen API error (HTTP %d)', 'fito-try-on-with-ai-for-woocommerce'), $code);
            $err = $body['error']['message'] ?? $http_err;
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => $raw_body, 'http_code' => $code]),
                'message'       => $err,
            ];
        }

        $b64 = $body['predictions'][0]['bytesBase64Encoded'] ?? '';
        if (empty($b64)) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => 'Google Imagen model did not return image bytes.']),
                'message'       => __('Google Imagen model did not return image bytes.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        return [
            'success'           => true,
            'model'             => $model,
            'is_fallback'       => $is_fallback,
            'result_image_data' => 'data:image/jpeg;base64,' . $b64,
            'description'       => 'Generated via Google Imagen 3',
            'prompt'            => $prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'cost'              => 0.03,
        ];
    }

    private function extract_inline_data(string $data_url): ?array {
        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $data_url, $matches)) {
            return null;
        }
        return [
            'mime_type' => $matches[1],
            'data'      => $matches[2],
        ];
    }
}
