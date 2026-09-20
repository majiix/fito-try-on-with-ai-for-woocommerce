<?php
/**
 * Direct OpenAI API integration for virtual try-on.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_OpenAI {
    public const API_BASE = 'https://api.openai.com/v1';
    public const CACHE_KEY_MODELS = 'fito_tryon_openai_models_cache';

    private Fito_Tryon $plugin;

    public function __construct(Fito_Tryon $plugin) {
        $this->plugin = $plugin;
    }

    public function get_api_key(): string {
        return trim((string) get_option('fito_tryon_openai_api_key', ''));
    }

    public function get_primary_model(): string {
        return (string) get_option('fito_tryon_openai_primary_model', 'gpt-image-2.5-flare');
    }

    public function get_fallback_model(): string {
        if (!Fito_Tryon::is_pro_active()) {
            return '';
        }
        return (string) get_option('fito_tryon_openai_fallback_model', 'dall-e-3');
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    public function get_curated_models(): array {
        return [
            [
                'id'         => 'gpt-image-2.5-flare',
                'name'       => 'OpenAI: GPT Image 2.5 Flare (Fast & High Quality)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-2.5-sunburst',
                'name'       => 'OpenAI: GPT Image 2.5 Sunburst (Precision Editing)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-2',
                'name'       => 'OpenAI: GPT Image 2',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'gpt-image-1',
                'name'       => 'OpenAI: GPT Image 1 (Multi-Reference)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'dall-e-3',
                'name'       => 'OpenAI: DALL-E 3 (High Detail)',
                'type'       => 'image_gen',
                'type_label' => __('Image Generation', 'fito-try-on-with-ai-for-woocommerce'),
            ],
            [
                'id'         => 'dall-e-2',
                'name'       => 'OpenAI: DALL-E 2 (Legacy)',
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
                'message' => __('OpenAI API key is empty.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $response = wp_remote_get(self::API_BASE . '/models', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
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

        if ($status === 200 && isset($body['data'])) {
            return [
                'success' => true,
                'message' => __('Connection verified! OpenAI API key is active.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $err_msg = $body['error']['message'] ?? __('Authentication failed. Check your OpenAI API key.', 'fito-try-on-with-ai-for-woocommerce');
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
            $response = wp_remote_get(self::API_BASE . '/models', [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $api_data = $body['data'] ?? [];
                if (is_array($api_data)) {
                    $seen = [];
                    foreach ($models as $m) {
                        $seen[$m['id']] = true;
                    }
                    foreach ($api_data as $item) {
                        $id = $item['id'] ?? '';
                        $lower = strtolower($id);
                        $is_image_model = str_contains($lower, 'image') || str_contains($lower, 'dall-e');
                        if (str_contains($lower, 'embedding') || str_contains($lower, 'audio') || str_contains($lower, 'realtime') || str_contains($lower, 'tts') || str_contains($lower, 'whisper') || str_contains($lower, 'moderation') || str_contains($lower, 'babbage') || str_contains($lower, 'davinci')) {
                            $is_image_model = false;
                        }

                        if ($is_image_model && empty($seen[$id])) {
                            $seen[$id] = true;
                            $display_name = 'OpenAI: ' . $id;
                            if (str_starts_with($id, 'gpt-image-2.5-flare')) {
                                $display_name = 'OpenAI: GPT Image 2.5 Flare (' . $id . ')';
                            } elseif (str_starts_with($id, 'gpt-image-2.5-sunburst')) {
                                $display_name = 'OpenAI: GPT Image 2.5 Sunburst (' . $id . ')';
                            } elseif (str_starts_with($id, 'gpt-image-2')) {
                                $display_name = 'OpenAI: GPT Image 2 (' . $id . ')';
                            } elseif (str_starts_with($id, 'gpt-image-1')) {
                                $display_name = 'OpenAI: GPT Image 1 (' . $id . ')';
                            } elseif (str_starts_with($id, 'dall-e-3')) {
                                $display_name = 'OpenAI: DALL-E 3 (' . $id . ')';
                            } elseif (str_starts_with($id, 'dall-e-2')) {
                                $display_name = 'OpenAI: DALL-E 2 (' . $id . ')';
                            }

                            $models[] = [
                                'id'         => $id,
                                'name'       => $display_name,
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
                'message' => __('OpenAI direct integration requires Fito Try-On Pro.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('OpenAI API key is not configured in settings.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $primary_model = $this->get_primary_model();
        $fallback_model = $this->get_fallback_model();

        $start_time = microtime(true);
        $result = $this->call_openai($primary_model, $params, $api_key, false);

        if (!$result['success'] && !empty($fallback_model) && $fallback_model !== $primary_model) {
            $result = $this->call_openai($fallback_model, $params, $api_key, true);
        }

        $result['duration'] = round(microtime(true) - $start_time, 2);
        return $result;
    }

    private function call_openai(string $model, array $params, string $api_key, bool $is_fallback): array {
        $prompt = $this->plugin->openrouter->build_category_prompt($params);
        $user_image_b64 = $this->plugin->openrouter->image_to_base64($params['user_image_path'] ?? '');

        if (empty($user_image_b64)) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => __('Missing or unreadable user photo.', 'fito-try-on-with-ai-for-woocommerce')]),
                'message'       => __('Missing or unreadable user photo.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $system_instruction = get_option('fito_tryon_system_prompt');
        if (empty($system_instruction)) {
            $system_instruction = Fito_Tryon_OpenRouter::get_default_system_prompt();
        }

        $full_prompt = $system_instruction . " " . $prompt;
        if (strlen($full_prompt) > 1000 && str_contains($model, 'dall-e')) {
            $full_prompt = substr($full_prompt, 0, 990) . '...';
        }

        $payload = [
            'model'           => $model,
            'prompt'          => $full_prompt,
            'n'               => 1,
            'size'            => '1024x1024',
            'response_format' => 'b64_json',
        ];

        $response = wp_remote_post(self::API_BASE . '/images/generations', [
            'timeout' => $this->get_timeout(),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
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
            $http_err = sprintf(__('OpenAI API error (HTTP %d)', 'fito-try-on-with-ai-for-woocommerce'), $code);
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

        $data_item = $body['data'][0] ?? null;
        if (!$data_item) {
            return [
                'success'       => false,
                'model'         => $model,
                'is_fallback'   => $is_fallback,
                'prompt'        => $full_prompt,
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['raw_body' => $raw_body]),
                'message'       => __('OpenAI returned an empty response.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        $image_data = !empty($data_item['b64_json']) ? 'data:image/png;base64,' . $data_item['b64_json'] : ($data_item['url'] ?? '');
        $prompt_tokens = (int) ($body['usage']['prompt_tokens'] ?? ($body['usage']['input_tokens'] ?? 0));
        $completion_tokens = (int) ($body['usage']['completion_tokens'] ?? ($body['usage']['output_tokens'] ?? 0));

        if (empty($image_data)) {
            return [
                'success'           => false,
                'model'             => $model,
                'is_fallback'       => $is_fallback,
                'prompt'            => $full_prompt,
                'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response(!empty($body) ? $body : ['error' => 'No image data returned from OpenAI.']),
                'prompt_tokens'     => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'message'           => __('No image data returned from OpenAI.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        return [
            'success'           => true,
            'model'             => $model,
            'is_fallback'       => $is_fallback,
            'result_image_data' => $image_data,
            'description'       => 'Generated via OpenAI Image API',
            'prompt'            => $full_prompt,
            'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response($body),
            'prompt_tokens'     => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'cost'              => 0.04,
        ];
    }
}
