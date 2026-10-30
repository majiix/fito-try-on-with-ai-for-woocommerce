<?php
/**
 * OpenRouter API client and prompt synthesis engine.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_OpenRouter {
    public const API_BASE = 'https://openrouter.ai/api/v1';
    public const CACHE_KEY_MODELS = 'fito_tryon_live_models_cache';

    public function get_api_key(): string {
        return trim((string) get_option('fito_tryon_openrouter_api_key', ''));
    }

    public function get_primary_model(): string {
        return get_option('fito_tryon_primary_model', 'google/gemini-2.5-flash-image');
    }

    public function get_timeout(): int {
        return (int) get_option('fito_tryon_timeout_seconds', 60);
    }

    public function get_curated_models(): array {
        $curated = [
            [
                'id'          => 'black-forest-labs/flux-1-dev',
                'name'        => 'FLUX.1 Dev (Diffusion)',
                'type'        => 'image_gen',
                'type_label'  => 'Image Generation',
                'speed'       => 'Moderate (5-10s)',
                'cost'        => '$$',
                'quality'     => 'Maximum',
                'note'        => __('State-of-the-art photorealism, skin textures, and fabric lighting.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'          => 'black-forest-labs/flux-1-schnell',
                'name'        => 'FLUX.1 Schnell (Diffusion)',
                'type'        => 'image_gen',
                'type_label'  => 'Image Generation',
                'speed'       => 'Fast (2-4s)',
                'cost'        => '$',
                'quality'     => 'High',
                'note'        => __('Direct diffusion generation optimized for speed.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'          => 'black-forest-labs/flux.2-klein-4b',
                'name'        => 'FLUX.2 Klein 4B (Diffusion)',
                'type'        => 'image_gen',
                'type_label'  => 'Image Generation',
                'speed'       => 'Ultra Fast (1-3s)',
                'cost'        => '$',
                'quality'     => 'High',
                'note'        => __('Ultra-fast diffusion try-on generation.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'          => 'google/gemini-2.5-flash-image',
                'name'        => 'Google: Nano Banana (Gemini 2.5 Flash Image)',
                'type'        => 'image_gen',
                'type_label'  => 'Image Generation',
                'speed'       => 'Fast (2-4s)',
                'cost'        => '$',
                'quality'     => 'High',
                'note'        => __('Native Google image generation and multi-reference editing model.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
            [
                'id'          => 'openai/gpt-5-image-mini',
                'name'        => 'OpenAI: GPT-5 Image Mini',
                'type'        => 'image_gen',
                'type_label'  => 'Image Generation',
                'speed'       => 'Fast (2-3s)',
                'cost'        => '$$',
                'quality'     => 'High',
                'note'        => __('OpenAI native image generation with multi-reference image-to-image support.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
        ];

        // Sort alphabetically by name
        usort($curated, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $curated;
    }

    public function test_connection(?string $api_key = null): array {
        $key = $api_key !== null ? trim($api_key) : $this->get_api_key();
        if (empty($key)) {
            return [
                'success' => false,
                'message' => __('Please enter an OpenRouter API key before testing.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $response = wp_remote_get(self::API_BASE . '/auth/key', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo('name') . ' Try-On',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                // translators: %s: Error message
                'message' => sprintf(__('Connection failed: %s', 'elfit-ai-virtual-try-on-for-woocommerce'), $response->get_error_message()),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code === 200 && isset($body['data'])) {
            $data = $body['data'];
            $label = !empty($data['label']) ? $data['label'] : __('Default Key', 'elfit-ai-virtual-try-on-for-woocommerce');
            $usage = isset($data['usage']) ? floatval($data['usage']) : 0;
            $limit = isset($data['limit']) ? floatval($data['limit']) : null;
            $limit_str = $limit !== null ? sprintf(' / $%s limit', number_format($limit, 2)) : '';

            return [
                'success' => true,
                'message' => sprintf(
                    // translators: 1: API key label, 2: Total spend in dollars, 3: Optional credit limit info
                    __('Connection verified! Key: "%1$s" (Total spend: $%2$s%3$s)', 'elfit-ai-virtual-try-on-for-woocommerce'),
                    esc_html($label),
                    number_format($usage, 2),
                    $limit_str
                ),
                'data'    => $data,
            ];
        }

        $error_msg = !empty($body['error']['message']) ? $body['error']['message'] : __('Authentication failed. Check your API key.', 'elfit-ai-virtual-try-on-for-woocommerce');
        return [
            'success' => false,
            'message' => $error_msg,
        ];
    }

    /**
     * Clear the cached OpenRouter models list from WordPress transients.
     */
    public function clear_models_cache(): bool {
        return delete_transient(self::CACHE_KEY_MODELS);
    }

    /**
     * Retrieve currently cached models from WordPress transient if available.
     */
    public function get_cached_models(): array {
        $cached = get_transient(self::CACHE_KEY_MODELS);
        if (!empty($cached) && is_array($cached)) {
            $filtered = array_values(array_filter($cached, function ($m) {
                return ($m['type'] ?? '') === 'image_gen';
            }));
            if (!empty($filtered)) {
                return $filtered;
            }
        }
        return [];
    }

    /**
     * Fetch models directly from OpenRouter API and sort alphabetically by name.
     * Caches in WordPress transients for 1 day (24 hours).
     */
    public function fetch_live_models(bool $force_refresh = false): array {
        if ($force_refresh) {
            $this->clear_models_cache();
        } else {
            $cached = $this->get_cached_models();
            if (!empty($cached)) {
                return $cached;
            }
        }

        $key = $this->get_api_key();
        $headers = [
            'HTTP-Referer' => home_url(),
            'X-Title'      => get_bloginfo('name'),
        ];
        if (!empty($key)) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        $all_models = [];
        $seen = [];

        // 1. Fetch dedicated image-generation models from /api/v1/images/models
        $images_response = wp_remote_get(self::API_BASE . '/images/models', [
            'timeout' => 20,
            'headers' => $headers,
        ]);

        if (!is_wp_error($images_response) && wp_remote_retrieve_response_code($images_response) === 200) {
            $images_body = json_decode(wp_remote_retrieve_body($images_response), true);
            $img_data = $images_body['data'] ?? [];
            if (is_array($img_data)) {
                foreach ($img_data as $m) {
                    $id = $m['id'] ?? '';
                    if (!empty($id)) {
                        $seen[$id] = true;
                        $all_models[] = [
                            'id'          => $id,
                            'name'        => $m['name'] ?? $id,
                            'type'        => 'image_gen',
                            'type_label'  => __('Image Generation', 'elfit-ai-virtual-try-on-for-woocommerce'),
                            'description' => !empty($m['description']) ? wp_trim_words($m['description'], 15) : '',
                        ];
                    }
                }
            }
        }

        // 2. Fetch general models from /api/v1/models and filter for image-capable models
        $general_response = wp_remote_get(self::API_BASE . '/models', [
            'timeout' => 25,
            'headers' => $headers,
        ]);

        if (!is_wp_error($general_response) && wp_remote_retrieve_response_code($general_response) === 200) {
            $general_body = json_decode(wp_remote_retrieve_body($general_response), true);
            $gen_data = $general_body['data'] ?? [];
            if (is_array($gen_data)) {
                foreach ($gen_data as $m) {
                    $id = $m['id'] ?? '';
                    if (empty($id) || isset($seen[$id])) {
                        continue;
                    }

                    $arch = $m['architecture'] ?? [];
                    $output_mods = (array) ($arch['output_modalities'] ?? []);
                    $modality = (string) ($arch['modality'] ?? '');

                    // Strict filter: model MUST support image output (image generation)
                    $has_image_output = in_array('image', $output_mods, true) || str_ends_with($modality, 'image') || str_contains($modality, '->image') || str_contains($modality, '->text+image');

                    if (!$has_image_output) {
                        continue;
                    }

                    $lower_id = strtolower($id);
                    if (str_contains($lower_id, 'embedding') || str_contains($lower_id, 'moderation') || str_contains($lower_id, 'whisper') || str_contains($lower_id, 'rerank')) {
                        continue;
                    }

                    $seen[$id] = true;
                    $all_models[] = [
                        'id'          => $id,
                        'name'        => $m['name'] ?? $id,
                        'type'        => 'image_gen',
                        'type_label'  => __('Image Generation', 'elfit-ai-virtual-try-on-for-woocommerce'),
                        'description' => !empty($m['description']) ? wp_trim_words($m['description'], 15) : '',
                    ];
                }
            }
        }

        if (empty($all_models)) {
            $all_models = $this->get_curated_models();
        }

        // Sort all models alphabetically by name (case-insensitive)
        usort($all_models, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        // Cache in WordPress for 1 day (24 hours)
        set_transient(self::CACHE_KEY_MODELS, $all_models, DAY_IN_SECONDS);

        return $all_models;
    }

    public function is_image_gen_model(string $model): bool {
        $lower = strtolower($model);
        // Multimodal chat completion models MUST use /chat/completions, NOT the /images endpoint:
        if (str_contains($lower, 'grok') || str_contains($lower, 'gemini') || str_contains($lower, 'gpt') || str_contains($lower, 'claude') || str_contains($lower, 'qwen')) {
            return false;
        }

        // Only dedicated diffusion/image-generation models use /images
        $models = $this->fetch_live_models();
        foreach ($models as $m) {
            if ($m['id'] === $model) {
                return ($m['type'] ?? '') === 'diffusion' || ($m['type'] ?? '') === 'image_gen';
            }
        }
        return str_contains($lower, 'flux') || str_contains($lower, 'recraft') || str_contains($lower, 'imagen') || str_contains($lower, 'seedream');
    }

    public function generate_tryon(array $params): array {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('OpenRouter API key is not configured in settings.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $primary_model = $this->get_primary_model();

        $start_time = microtime(true);
        $result = $this->call_openrouter($primary_model, $params, $api_key);

        $duration = round(microtime(true) - $start_time, 2);
        $result['duration'] = $duration;

        return $result;
    }

    public function call_openrouter(string $model, array $params, string $api_key): array {
        $prompt = $this->build_category_prompt($params);
        $user_image_b64 = Fito_Tryon_Storage::image_to_base64($params['user_image_path'] ?? '');
        $product_image_url = $params['product_image_url'] ?? '';
        $product_image_id = (int) ($params['product_image_id'] ?? 0);
        $product_image_b64 = Fito_Tryon_Storage::resolve_image_base64($product_image_url, $product_image_id);

        if (empty($user_image_b64)) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(['error' => __('Missing or unreadable user photo.', 'elfit-ai-virtual-try-on-for-woocommerce')]),
                'message'       => __('Missing or unreadable user photo.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $timeout = $this->get_timeout();

        // Check if model is a dedicated image generation model
        if ($this->is_image_gen_model($model)) {
            $image_api_result = $this->call_image_generation_api($model, $prompt, $user_image_b64, $product_image_b64, $api_key, $timeout);
            if ($image_api_result['success']) {
                return $image_api_result;
            }
            // If dedicated image endpoint failed, continue to chat completions as fallback
        }

        // Standard Multimodal Vision / Chat Completions
        $system_instruction = get_option('fito_tryon_system_prompt');
        if (empty($system_instruction)) {
            $system_instruction = self::get_default_system_prompt();
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => $system_instruction,
            ],
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    [
                        'type'      => 'image_url',
                        'image_url' => [
                            'url' => $user_image_b64,
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($product_image_b64)) {
            $messages[1]['content'][] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => $product_image_b64,
                ],
            ];
        } elseif (!empty($product_image_url)) {
            $messages[1]['content'][] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => $product_image_url,
                ],
            ];
        }

        $payload = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => 1200,
        ];

        $response = wp_remote_post(self::API_BASE . '/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo('name') . ' Try-On',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success'       => false,
                'model'         => $model,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(['error' => $response->get_error_message()]),
                // translators: %s: Error message
                'message'       => sprintf(__('AI request timed out or network error: %s', 'elfit-ai-virtual-try-on-for-woocommerce'), $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            $raw_msg = $this->extract_raw_error_message($body);
            $msg = $this->parse_error_code($code, $body);
            return [
                'success'       => false,
                'model'         => $model,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(!empty($body) ? $body : ['error' => $raw_body, 'http_code' => $code]),
                'message'       => $msg,
                'raw_error'     => !empty($raw_msg) ? $raw_msg : $msg,
            ];
        }

        $choice = $body['choices'][0]['message'] ?? null;
        if (!$choice) {
            return [
                'success'       => false,
                'model'         => $model,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(!empty($body) ? $body : ['raw_body' => $raw_body]),
                'message'       => __('Empty response returned from AI model.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $content = $choice['content'] ?? '';
        $tokens_prompt = $body['usage']['prompt_tokens'] ?? 0;
        $tokens_completion = $body['usage']['completion_tokens'] ?? 0;
        $cost = $this->estimate_cost($model, $tokens_prompt, $tokens_completion);

        $extracted_image = $this->extract_image_from_choice($choice);
        if (empty($extracted_image)) {
            $refusal_text = '';
            if (is_string($content) && !empty(trim($content))) {
                $refusal_text = trim($content);
            } elseif (!empty($choice['refusal']) && is_string($choice['refusal'])) {
                $refusal_text = trim($choice['refusal']);
            }

            $is_moderated = false;
            $is_undergarment = false;
            if (!empty($refusal_text)) {
                $lower_refusal = strtolower($refusal_text);
                $is_undergarment = str_contains($lower_refusal, 'undergarment') ||
                                   str_contains($lower_refusal, 'underwear') ||
                                   str_contains($lower_refusal, 'lingerie') ||
                                   str_contains($lower_refusal, 'swimwear') ||
                                   str_contains($lower_refusal, 'swimsuit') ||
                                   str_contains($lower_refusal, 'intimate');
                $is_moderated = $is_undergarment ||
                                str_contains($lower_refusal, 'cannot fulfill') ||
                                str_contains($lower_refusal, 'safety') ||
                                str_contains($lower_refusal, 'policy') ||
                                str_contains($lower_refusal, 'moderation') ||
                                str_contains($lower_refusal, 'not suitable') ||
                                str_contains($lower_refusal, 'violation');
            }

            if ($is_undergarment) {
                $fail_message = __('This item cannot be virtually fitted due to AI clothing category restrictions. Please try another product.', 'elfit-ai-virtual-try-on-for-woocommerce');
            } elseif ($is_moderated) {
                $fail_message = __('The AI provider rejected the request due to content safety/moderation policies. Please try a different photo or clothing item.', 'elfit-ai-virtual-try-on-for-woocommerce');
            } elseif (!empty($refusal_text)) {
                // translators: %s: AI refusal notice text
                $fail_message = sprintf(__('AI Model Notice: %s', 'elfit-ai-virtual-try-on-for-woocommerce'), $refusal_text);
            } else {
                $fail_message = __('The AI model responded with text but was unable to generate an image.', 'elfit-ai-virtual-try-on-for-woocommerce');
            }

            return [
                'success'           => false,
                'model'             => $model,
                'is_moderated'      => $is_moderated,
                'prompt'            => $prompt,
                'response_text'     => self::sanitize_json_response(!empty($body) ? $body : ['content' => $content, 'refusal' => $refusal_text]),
                'prompt_tokens'     => $tokens_prompt,
                'completion_tokens' => $tokens_completion,
                'cost'              => $cost,
                'message'           => $fail_message,
                'raw_error'         => !empty($refusal_text) ? $refusal_text : $fail_message,
            ];
        }

        $response_desc = is_string($content) ? $content : (is_array($content) ? wp_json_encode($content) : '');

        return [
            'success'           => true,
            'model'             => $model,
            'result_image_data' => $extracted_image,
            'description'       => $response_desc,
            'prompt'            => $prompt,
            'response_text'     => self::sanitize_json_response($body),
            'prompt_tokens'     => $tokens_prompt,
            'completion_tokens' => $tokens_completion,
            'cost'              => $cost,
        ];
    }

    private function call_image_generation_api(string $model, string $prompt, string $user_b64, string $prod_b64, string $api_key, int $timeout): array {
        $input_references = [];
        if (!empty($user_b64)) {
            $input_references[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $user_b64],
            ];
        }
        if (!empty($prod_b64)) {
            $input_references[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $prod_b64],
            ];
        }

        $payload = [
            'model'            => $model,
            'prompt'           => $prompt,
            'input_references' => $input_references,
        ];

        $response = wp_remote_post(self::API_BASE . '/images', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo('name') . ' Try-On',
            ],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $err_body = !is_wp_error($response) ? wp_remote_retrieve_body($response) : $response->get_error_message();
            $decoded_err = json_decode($err_body, true);
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(!empty($decoded_err) ? $decoded_err : ['error' => $err_body]),
                'message'       => __('OpenRouter Image API request failed.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);
        $data_item = $body['data'][0] ?? null;

        if (!$data_item) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(!empty($body) ? $body : ['raw_body' => $raw_body]),
                'message'       => __('OpenRouter Image API did not return data.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $img_result = !empty($data_item['b64_json']) ? 'data:image/jpeg;base64,' . $data_item['b64_json'] : ($data_item['url'] ?? '');
        if (empty($img_result)) {
            return [
                'success'       => false,
                'prompt'        => $prompt,
                'response_text' => self::sanitize_json_response(!empty($body) ? $body : ['error' => 'Empty image result returned']),
                'message'       => __('OpenRouter Image API returned empty image.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ];
        }

        $tokens_prompt = (int) ($body['usage']['prompt_tokens'] ?? ($body['usage']['input_tokens'] ?? 0));
        $tokens_completion = (int) ($body['usage']['completion_tokens'] ?? ($body['usage']['output_tokens'] ?? 0));
        $cost = isset($body['usage']['total_cost']) ? (float) $body['usage']['total_cost'] : (isset($body['usage']['cost']) ? (float) $body['usage']['cost'] : 0.02);

        return [
            'success'           => true,
            'model'             => $model,
            'result_image_data' => $img_result,
            'description'       => 'Generated via OpenRouter Image API',
            'prompt'            => $prompt,
            'response_text'     => self::sanitize_json_response($body),
            'prompt_tokens'     => $tokens_prompt,
            'completion_tokens' => $tokens_completion,
            'cost'              => $cost,
        ];
    }

    /**
     * Sanitize and format an AI response data structure into pretty-printed JSON string,
     * stripping out large inline base64 image data payloads.
     *
     * @param mixed $response_data Raw API response data, array, or JSON string.
     * @return string Formatted JSON string ready for database storage and inspection.
     */
    public static function sanitize_json_response(mixed $response_data): string {
        if (is_string($response_data)) {
            $decoded = json_decode($response_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $response_data = $decoded;
            } else {
                return $response_data;
            }
        }

        if (!is_array($response_data)) {
            return (string) $response_data;
        }

        $sanitized = self::recursive_strip_base64($response_data);
        $encoded = wp_json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$encoded) {
            $encoded = wp_json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $encoded ?: (is_string($response_data) ? $response_data : '');
    }

    private static function recursive_strip_base64(mixed $data): mixed {
        if (!is_array($data)) {
            if (is_string($data)) {
                if (str_contains($data, 'data:image/') && strlen($data) > 100) {
                    return preg_replace_callback('/data:image\/[a-zA-Z0-9+.\-]+;base64,[A-Za-z0-9+\/=\s]{100,}/', function ($m) {
                        return '[BASE64_IMAGE_DATA_OMITTED (' . strlen($m[0]) . ' bytes)]';
                    }, $data);
                }
                if (strlen($data) > 500 && preg_match('/^[A-Za-z0-9+\/=\s]{500,}$/', $data)) {
                    return '[BASE64_IMAGE_DATA_OMITTED (' . strlen($data) . ' bytes)]';
                }
            }
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['b64_json', 'bytesBase64Encoded'], true) && is_string($value)) {
                $result[$key] = '[BASE64_IMAGE_DATA_OMITTED (' . strlen($value) . ' bytes)]';
            } elseif ($key === 'inlineData' && is_array($value) && isset($value['data']) && is_string($value['data'])) {
                $result[$key] = $value;
                $result[$key]['data'] = '[BASE64_IMAGE_DATA_OMITTED (' . strlen($value['data']) . ' bytes)]';
            } else {
                $result[$key] = self::recursive_strip_base64($value);
            }
        }
        return $result;
    }

    public static function get_default_system_prompt(): string {
        return "You are a professional photorealistic virtual try-on engine for high-end e-commerce. " .
            "Your objective is to generate or describe with absolute photographic accuracy how the customer in Image 1 looks wearing or utilizing the exact product shown in Image 2. " .
            "Retain the customer's face, identity, hair, skin tone, body dimensions, pose, and background lighting. " .
            "Accurately match the product's color, fabric texture, weave, hardware, labels, and fit.";
    }

    public static function get_default_prompts(): array {
        return [
            [
                'id'         => 'clothing',
                'title'      => 'Clothing & Apparel',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Perform a virtual fitting of the apparel '{product_title}' on the person in Image 1. Replace or overlay their existing upper/lower garment with this exact item. Maintain natural fabric folds, tension, neckline, sleeves, and texture consistent with the person's pose and lighting.",
            ],
            [
                'id'         => 'shoes',
                'title'      => 'Footwear & Shoes',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Virtually fit the footwear '{product_title}' on the person in Image 1. Position the shoes naturally on their feet, matching the ground contact shadows, stance angle, and shoe proportions exactly.",
            ],
            [
                'id'         => 'glasses',
                'title'      => 'Eyewear & Glasses',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Place the eyewear '{product_title}' naturally onto the person's face in Image 1. Align the bridge with their nasal bone, wrap temple arms over the ears, and preserve realistic glass lens reflections and cheek shadows.",
            ],
            [
                'id'         => 'hats',
                'title'      => 'Hats & Headwear',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Place the headwear '{product_title}' naturally on the person's head in Image 1. Fit the crown to head curvature, interact realistically with hair strands, and cast subtle brim shadows across the forehead.",
            ],
            [
                'id'         => 'watches',
                'title'      => 'Watches & Timepieces',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Place the timepiece '{product_title}' onto the person's visible wrist in Image 1. Wrap the band snug to the wrist contour with metallic/leather luster matching ambient light.",
            ],
            [
                'id'         => 'bags',
                'title'      => 'Bags & Handbags',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Place the bag '{product_title}' held by or slung across the person in Image 1. Render realistic strap drop, hand grip tension, and natural body occlusion.",
            ],
            [
                'id'         => 'jewelry',
                'title'      => 'Jewelry & Accessories',
                'is_system'  => true,
                'categories' => [],
                'prompt'     => "Adorn the person in Image 1 with the jewelry piece '{product_title}'. Align precisely to earlobe/neck/wrist/finger with authentic gemstone sparkle, metal reflectivity, and skin contact shadows.",
            ],
        ];
    }

    public function build_category_prompt(array $params): string {
        $product_title = $params['product_title'] ?? __('Product', 'elfit-ai-virtual-try-on-for-woocommerce');
        $variation_desc = $params['variation_desc'] ?? '';
        $category_ids = array_map('intval', (array) ($params['category_ids'] ?? []));
        $category_str = strtolower($params['categories'] ?? '');

        $configured_prompts = get_option('fito_tryon_prompts_config');
        if (!is_array($configured_prompts) || empty($configured_prompts)) {
            $configured_prompts = self::get_default_prompts();
        }

        $selected_prompt_template = '';

        // 1. Match by WooCommerce category IDs (independent of site language)
        if (!empty($category_ids)) {
            foreach ($configured_prompts as $prompt_item) {
                $assigned_cats = array_map('intval', (array) ($prompt_item['categories'] ?? []));
                if (!empty($assigned_cats) && !empty(array_intersect($category_ids, $assigned_cats))) {
                    $selected_prompt_template = $prompt_item['prompt'] ?? '';
                    break;
                }
            }
        }

        // 2. Secondary fallback: keyword match if store admin has not yet mapped categories
        if (empty($selected_prompt_template) && !empty($category_str)) {
            $keyword_map = [
                'shoes'   => ['shoe', 'sneaker', 'boot', 'footwear'],
                'glasses' => ['glass', 'sunglass', 'eyewear'],
                'hats'    => ['hat', 'cap', 'beanie', 'headwear'],
                'watches' => ['watch'],
                'bags'    => ['bag', 'backpack', 'purse', 'tote'],
                'jewelry' => ['jewel', 'necklace', 'ring', 'earring', 'bracelet'],
            ];

            foreach ($configured_prompts as $prompt_item) {
                $pid = $prompt_item['id'] ?? '';
                if (!empty($keyword_map[$pid])) {
                    foreach ($keyword_map[$pid] as $kw) {
                        if (str_contains($category_str, $kw)) {
                            $selected_prompt_template = $prompt_item['prompt'] ?? '';
                            break 2;
                        }
                    }
                }
            }
        }

        // 3. Fallback: clothing preset or first prompt in configuration
        if (empty($selected_prompt_template)) {
            foreach ($configured_prompts as $prompt_item) {
                if (($prompt_item['id'] ?? '') === 'clothing') {
                    $selected_prompt_template = $prompt_item['prompt'] ?? '';
                    break;
                }
            }
            if (empty($selected_prompt_template) && !empty($configured_prompts[0]['prompt'])) {
                $selected_prompt_template = $configured_prompts[0]['prompt'];
            }
        }

        if (empty($selected_prompt_template)) {
            $defaults = self::get_default_prompts();
            $selected_prompt_template = $defaults[0]['prompt'];
        }

        $final_prompt = str_replace('{product_title}', $product_title, $selected_prompt_template);

        if (!empty($variation_desc)) {
            // translators: %s: Product variation attributes
            $final_prompt .= ' ' . sprintf(__('Selected product variation attributes: %s.', 'elfit-ai-virtual-try-on-for-woocommerce'), $variation_desc);
        }

        return $final_prompt;
    }

    public function extract_image_from_choice(array $choice): ?string {
        // 1. Check $choice['images'] array (e.g. x-ai/grok-imagine-image-2.0, google/gemini-2.5-flash-image)
        if (!empty($choice['images']) && is_array($choice['images'])) {
            foreach ($choice['images'] as $img_item) {
                if (is_array($img_item)) {
                    if (!empty($img_item['image_url']['url']) && is_string($img_item['image_url']['url'])) {
                        return $img_item['image_url']['url'];
                    }
                    if (!empty($img_item['url']) && is_string($img_item['url'])) {
                        return $img_item['url'];
                    }
                    if (!empty($img_item['b64_json']) && is_string($img_item['b64_json'])) {
                        return 'data:image/jpeg;base64,' . $img_item['b64_json'];
                    }
                } elseif (is_string($img_item)) {
                    $img = $this->extract_image_from_text($img_item);
                    if (!empty($img)) {
                        return $img;
                    }
                }
            }
        }

        // 2. Check $choice['content'] (can be string or multimodal array)
        $content = $choice['content'] ?? null;
        if (is_array($content)) {
            foreach ($content as $part) {
                if (is_array($part)) {
                    if (($part['type'] ?? '') === 'image_url' && !empty($part['image_url']['url'])) {
                        return $part['image_url']['url'];
                    }
                    if (!empty($part['text']) && is_string($part['text'])) {
                        $img = $this->extract_image_from_text($part['text']);
                        if (!empty($img)) {
                            return $img;
                        }
                    }
                }
            }
        } elseif (is_string($content)) {
            return $this->extract_image_from_text($content);
        }

        return null;
    }

    public function extract_image_from_text(string $text): ?string {
        if (preg_match('/!\[.*?\]\((https?:\/\/[^\s\)]+)\)/i', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/(data:image\/[a-zA-Z0-9+.\-]+;base64,[A-Za-z0-9+\/=\n\r]+)/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/(https?:\/\/[^\s\)\"\']+\.(?:png|jpe?g|webp))/i', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function estimate_cost(string $model, int $prompt_tok, int $comp_tok): float {
        $prompt_rate = 0.00000015;
        $comp_rate = 0.00000060;

        if (str_contains($model, 'gpt-4o-mini')) {
            $prompt_rate = 0.00000015;
            $comp_rate   = 0.00000060;
        } elseif (str_contains($model, 'gpt-4o')) {
            $prompt_rate = 0.00000250;
            $comp_rate   = 0.00001000;
        } elseif (str_contains($model, 'gemini-2.5-flash')) {
            $prompt_rate = 0.000000075;
            $comp_rate   = 0.00000030;
        } elseif (str_contains($model, 'claude-3.5-sonnet')) {
            $prompt_rate = 0.00000300;
            $comp_rate   = 0.00001500;
        }

        return ($prompt_tok * $prompt_rate) + ($comp_tok * $comp_rate);
    }

    private function extract_raw_error_message(?array $body): string {
        if (empty($body['error'])) {
            return '';
        }
        $msg = $body['error']['message'] ?? '';
        if (!empty($body['error']['metadata']['raw'])) {
            $raw = $body['error']['metadata']['raw'];
            $raw_decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (is_array($raw_decoded)) {
                if (!empty($raw_decoded['error']) && is_string($raw_decoded['error'])) {
                    return $raw_decoded['error'];
                }
                if (!empty($raw_decoded['message']) && is_string($raw_decoded['message'])) {
                    return $raw_decoded['message'];
                }
            } elseif (is_string($raw)) {
                return $raw;
            }
        }
        return is_string($msg) ? $msg : '';
    }

    private function parse_error_code(int $code, ?array $body): string {
        $msg = $this->extract_raw_error_message($body);

        // Check if error is related to content moderation/safety
        $lower_msg = strtolower($msg);
        $raw_str = isset($body['error']['metadata']['raw']) ? strtolower(wp_json_encode($body['error']['metadata']['raw'])) : '';
        if (str_contains($lower_msg, 'moderation') || str_contains($lower_msg, 'moderated') || str_contains($raw_str, 'content-moderated') || str_contains($lower_msg, 'safety') || str_contains($lower_msg, 'policy violation')) {
            return __('The AI provider rejected the request due to content safety/moderation policies. Please try a different photo or clothing item.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }

        // Check for invalid, discontinued, or misconfigured model ID
        if (str_contains($lower_msg, 'not a valid model') || str_contains($lower_msg, 'model not found') || str_contains($lower_msg, 'unknown model') || str_contains($lower_msg, 'does not exist')) {
            return __('The virtual try-on service is temporarily unavailable. Please try again in a few moments or contact store support.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }

        if ($code === 401) {
            return __('Invalid OpenRouter API Key. Please verify your key in Settings.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }
        if ($code === 402) {
            return __('OpenRouter account quota exceeded. Please top up your credits.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }
        if ($code === 429) {
            return __('OpenRouter rate limit reached. Please wait a moment and try again.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }
        if ($code === 503 || $code === 504) {
            return __('The AI model provider is temporarily unavailable. Please try again in a few moments.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }
        if (!empty($msg)) {
            // translators: %s: AI service error message
            return sprintf(__('AI Service Error: %s', 'elfit-ai-virtual-try-on-for-woocommerce'), $msg);
        }
        // translators: %d: HTTP status code
        return sprintf(__('AI request returned status %d.', 'elfit-ai-virtual-try-on-for-woocommerce'), $code);
    }
}
