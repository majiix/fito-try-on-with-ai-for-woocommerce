<?php
/**
 * REST API routes for Elfit AI Virtual Try-On for WooCommerce.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Rest {
    public const NAMESPACE = 'fito-tryon/v1';

    private Fito_Tryon $plugin;

    public function __construct(Fito_Tryon $plugin) {
        $this->plugin = $plugin;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $ns = self::NAMESPACE;

        register_rest_route($ns, '/submit', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_submit'],
                'permission_callback' => [$this, 'check_frontend_permission'],
                'args'                => [
                    'product_id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'variation_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]);

            register_rest_route($ns, '/status/(?P<token>[a-zA-Z0-9]+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_status'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);

            register_rest_route($ns, '/test-connection', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_test_connection'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ]);

            register_rest_route($ns, '/models', [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_get_models'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ]);

            register_rest_route($ns, '/logs', [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handle_delete_logs'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ]);

            register_rest_route($ns, '/log-client-error', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_log_client_error'],
                'permission_callback' => [$this, 'check_frontend_permission'],
                'args'                => [
                    'error_message' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);
    }

    public function check_frontend_permission(WP_REST_Request $request): bool {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce');
        }
        return (bool) wp_verify_nonce((string) $nonce, 'wp_rest');
    }

    public function check_admin_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    public function handle_submit(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        // 0. Access level check (Guests vs Logged-in users)
        $access_level = get_option('fito_tryon_access_level', 'everyone');
        if ($access_level === 'logged_in_only' && !is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Please sign up or log in to your account to use the AI Virtual Try-On feature.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 401);
        }

        // 1. Consent verification
        $consent = $request->get_param('consent');
        if (empty($consent) || $consent === 'false' || $consent === '0') {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('You must consent to AI photo processing to use the virtual try-on.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 400);
        }

        // 2. Rate limiting check
        $device_id = $this->plugin->rate_limiter->get_device_id($request);
        $rl_check = $this->plugin->rate_limiter->check_limit($user_id, $device_id);
        if (!$rl_check['allowed']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $rl_check['message'],
            ], 429);
        }

        // 3. Product validation
        $product_id = absint($request->get_param('product_id'));
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid WooCommerce product selected.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 400);
        }

        if (!$this->plugin->public->is_product_allowed($product_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Virtual try-on is not enabled for this product category.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 403);
        }

        // 4. Handle file or base64 image
        $files = $request->get_file_params();
        $user_img_data = null;

        if (!empty($files['photo'])) {
            $user_img_data = $this->plugin->storage->handle_upload($files['photo']);
        } elseif (!empty($request->get_param('image_base64'))) {
            $b64 = (string) $request->get_param('image_base64');
            $user_img_data = $this->plugin->storage->handle_upload_base64($b64);
        }

        if (empty($user_img_data) || empty($user_img_data['success'])) {
            $err = $user_img_data['message'] ?? __('Failed to process uploaded photo.', 'elfit-ai-virtual-try-on-for-woocommerce');
            return new WP_REST_Response(['success' => false, 'message' => $err], 400);
        }

        $variation_id = absint($request->get_param('variation_id'));
        $variation = $variation_id ? wc_get_product($variation_id) : null;

        $custom_img_url = esc_url_raw($request->get_param('product_image_url'));
        $custom_img_id  = absint($request->get_param('product_image_id'));

        // Resolve product image (custom selected image, then variation, then main featured image)
        $prod_image_id = 0;
        $prod_img_url  = '';

        // Validate custom image ID against allowed product/gallery/variation attachments
        $allowed_attachment_ids = array_filter([
            $product->get_image_id(),
            $variation ? $variation->get_image_id() : 0,
        ]);
        if ($product) {
            $allowed_attachment_ids = array_merge($allowed_attachment_ids, (array) $product->get_gallery_image_ids());
        }
        if ($custom_img_id > 0 && in_array($custom_img_id, $allowed_attachment_ids, true)) {
            $prod_image_id = $custom_img_id;
            $prod_img_url  = (string) wp_get_attachment_image_url($prod_image_id, 'large');
        }

        // Validate custom image URL: must reside within local uploads
        if (empty($prod_img_url) && !empty($custom_img_url)) {
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['baseurl']) && str_starts_with($custom_img_url, $upload_dir['baseurl'])) {
                $prod_img_url = $custom_img_url;
            }
        }

        if (empty($prod_img_url) && $variation && $variation->get_image_id()) {
            $prod_image_id = $variation->get_image_id();
            $prod_img_url  = (string) wp_get_attachment_image_url($prod_image_id, 'large');
        }
        if (empty($prod_img_url) && $product->get_image_id()) {
            $prod_image_id = $product->get_image_id();
            $prod_img_url  = (string) wp_get_attachment_image_url($prod_image_id, 'large');
        }

        // Category context
        $cat_ids = $product->get_category_ids();
        $cat_names = [];
        foreach ($cat_ids as $cid) {
            $term = get_term($cid, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $cat_names[] = $term->name;
            }
        }
        $categories_str = implode(', ', $cat_names);

        $variation_desc = '';
        if ($variation && is_a($variation, 'WC_Product_Variation')) {
            $variation_desc = wc_get_formatted_variation($variation, true);
        }

        // 5. Create database job
        $session_id = wp_get_session_token() ?: md5($this->plugin->rate_limiter->get_client_ip());
        $model = $this->plugin->get_active_primary_model();

        $token = $this->plugin->db->create_job([
            'user_id'           => $user_id,
            'session_id'        => $session_id,
            'product_id'        => $product_id,
            'variation_id'      => $variation_id,
            'user_image_path'   => $user_img_data['path'],
            'user_image_url'    => $user_img_data['url'],
            'product_image_url' => $prod_img_url,
            'model_used'        => $model,
        ]);

        // 6. Check Cache via Filter (handled by caching addons)
        $cached_result = apply_filters('fito_tryon_get_cached_result', null, $user_img_data['path'], $product_id, $variation_id, $model);
        if (!empty($cached_result)) {
            $this->plugin->db->update_job_by_token($token, [
                'status'           => 'completed',
                'progress'         => 100,
                'status_message'   => __('Preview loaded from cache.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'result_image_url' => $cached_result,
            ]);
            $this->plugin->rate_limiter->increment_limit($user_id, $device_id);

            return new WP_REST_Response([
                'success'          => true,
                'token'            => $token,
                'status'           => 'completed',
                'progress'         => 100,
                'result_image_url' => $cached_result,
                'user_image_url'   => $user_img_data['url'],
                'message'          => __('Try-on complete!', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 200);
        }

        // 7. Execute AI Generation
        $this->plugin->db->update_job_by_token($token, [
            'status'         => 'processing',
            'progress'       => 45,
            'status_message' => __('Analyzing photo and product geometry...', 'elfit-ai-virtual-try-on-for-woocommerce'),
        ]);

        try {
            $ai_response = $this->plugin->generate_tryon([
                'user_image_path'   => $user_img_data['path'],
                'product_image_url' => $prod_img_url,
                'product_image_id'  => $prod_image_id,
                'product_title'     => $product->get_name(),
                'categories'        => $categories_str,
                'category_ids'      => $cat_ids,
                'variation_desc'    => $variation_desc,
            ]);
        } catch (\Throwable $e) {
            $ai_response = [
                'success'       => false,
                'model'         => $model,
                'prompt'        => '',
                'response_text' => Fito_Tryon_OpenRouter::sanitize_json_response(['exception' => $e->getMessage()]),
                'message'       => $e->getMessage(),
            ];
        }

        $client_ip = $this->plugin->rate_limiter->get_client_ip();
        $job_record = $this->plugin->db->get_job_by_token($token);
        $job_id = $job_record ? (int) $job_record->id : 0;

        if ($ai_response['success'] && !empty($ai_response['result_image_data'])) {
            $saved_result = $this->plugin->storage->save_result_image($ai_response['result_image_data']);
            if (empty($saved_result['success']) || empty($saved_result['url'])) {
                $storage_err = $saved_result['message'] ?? __('Failed to save generated try-on image.', 'elfit-ai-virtual-try-on-for-woocommerce');
                $this->plugin->db->update_job_by_token($token, [
                    'status'         => 'failed',
                    'progress'       => 100,
                    'status_message' => $storage_err,
                ]);

                $fail_log = [
                    'job_id'            => $job_id,
                    'user_id'           => $user_id,
                    'ip_address'        => $client_ip,
                    'product_id'        => $product_id,
                    'variation_id'      => $variation_id,
                    'model_used'        => $ai_response['model'] ?? $model,
                    'status'            => 'failed',
                    'duration_sec'      => $ai_response['duration'] ?? 0,
                    'prompt_tokens'     => $ai_response['prompt_tokens'] ?? 0,
                    'completion_tokens' => $ai_response['completion_tokens'] ?? 0,
                    'cost_estimate'     => $ai_response['cost'] ?? 0,
                    'error_message'     => $storage_err,
                    'prompt_text'       => $ai_response['prompt'] ?? '',
                    'response_text'     => !empty($ai_response['response_text']) ? $ai_response['response_text'] : Fito_Tryon_OpenRouter::sanitize_json_response($ai_response),
                ];
                $fail_log = (array) apply_filters('fito_tryon_log_data', $fail_log, [
                    'user_image_url'   => $user_img_data['url'] ?? '',
                    'result_image_url' => '',
                ]);
                $this->plugin->db->add_log($fail_log);

                return new WP_REST_Response([
                    'success' => false,
                    'token'   => $token,
                    'status'  => 'failed',
                    'message' => $storage_err,
                ], 422);
            }

            $final_url = $saved_result['url'];
            $final_path = $saved_result['path'] ?? '';

            do_action('fito_tryon_set_cached_result', $user_img_data['path'], $product_id, $variation_id, $ai_response['model'], $final_url);

            $this->plugin->db->update_job_by_token($token, [
                'status'            => 'completed',
                'progress'          => 100,
                'status_message'    => __('Fitting successfully generated!', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'result_image_url'  => $final_url,
                'result_image_path' => $final_path,
                'model_used'        => $ai_response['model'],
            ]);

            $success_log = [
                'job_id'            => $job_id,
                'user_id'           => $user_id,
                'ip_address'        => $client_ip,
                'product_id'        => $product_id,
                'variation_id'      => $variation_id,
                'model_used'        => $ai_response['model'],
                'status'            => 'success',
                'duration_sec'      => $ai_response['duration'] ?? 1.5,
                'prompt_tokens'     => $ai_response['prompt_tokens'] ?? 0,
                'completion_tokens' => $ai_response['completion_tokens'] ?? 0,
                'cost_estimate'     => $ai_response['cost'] ?? 0,
                'prompt_text'       => $ai_response['prompt'] ?? '',
                'response_text'     => !empty($ai_response['response_text']) ? $ai_response['response_text'] : Fito_Tryon_OpenRouter::sanitize_json_response($ai_response),
            ];
            $success_log = (array) apply_filters('fito_tryon_log_data', $success_log, [
                'user_image_url'   => $user_img_data['url'] ?? '',
                'result_image_url' => $final_url,
            ]);
            $this->plugin->db->add_log($success_log);

            $this->plugin->rate_limiter->increment_limit($user_id, $device_id);

            return new WP_REST_Response([
                'success'          => true,
                'token'            => $token,
                'status'           => 'completed',
                'progress'         => 100,
                'result_image_url' => $final_url,
                'user_image_url'   => $user_img_data['url'],
                'product_image_url'=> $prod_img_url,
                'message'          => __('Virtual try-on completed successfully!', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 200);
        }

        // Failure handling
        $fail_msg = $ai_response['message'] ?? __('Failed to generate virtual try-on.', 'elfit-ai-virtual-try-on-for-woocommerce');
        $raw_err = !empty($ai_response['raw_error']) ? $ai_response['raw_error'] : $fail_msg;
        $this->plugin->db->update_job_by_token($token, [
            'status'         => 'failed',
            'progress'       => 100,
            'status_message' => $fail_msg,
        ]);

        $err_log = [
            'job_id'            => $job_id,
            'user_id'           => $user_id,
            'ip_address'        => $client_ip,
            'product_id'        => $product_id,
            'variation_id'      => $variation_id,
            'model_used'        => $ai_response['model'] ?? $model,
            'status'            => 'failed',
            'duration_sec'      => $ai_response['duration'] ?? 0,
            'prompt_tokens'     => $ai_response['prompt_tokens'] ?? 0,
            'completion_tokens' => $ai_response['completion_tokens'] ?? 0,
            'cost_estimate'     => $ai_response['cost'] ?? 0,
            'error_message'     => $raw_err,
            'prompt_text'       => $ai_response['prompt'] ?? '',
            'response_text'     => !empty($ai_response['response_text']) ? $ai_response['response_text'] : Fito_Tryon_OpenRouter::sanitize_json_response(!empty($ai_response) ? $ai_response : ['error' => $raw_err]),
        ];
        $err_log = (array) apply_filters('fito_tryon_log_data', $err_log, [
            'user_image_url'   => $user_img_data['url'] ?? '',
            'result_image_url' => '',
        ]);
        $this->plugin->db->add_log($err_log);

        return new WP_REST_Response([
            'success' => false,
            'token'   => $token,
            'status'  => 'failed',
            'message' => $fail_msg,
        ], 422);
    }

    public function handle_status(WP_REST_Request $request): WP_REST_Response {
        $access_level = get_option('fito_tryon_access_level', 'everyone');
        if ($access_level === 'logged_in_only' && !is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Please sign in to view try-on status.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 401);
        }

        $token = sanitize_text_field($request->get_param('token'));
        $job = $this->plugin->db->get_job_by_token($token);

        if (!$job) {
            return new WP_REST_Response(['success' => false, 'message' => __('Try-on job not found.', 'elfit-ai-virtual-try-on-for-woocommerce')], 404);
        }

        // Auto-fail stale jobs stuck in processing for more than 90 seconds
        if ($job->status === 'processing') {
            $created = !empty($job->created_at) ? strtotime($job->created_at) : 0;
            if ($created > 0 && (time() - $created) > 90) {
                $timeout_msg = __('The AI generation request timed out or was interrupted. Please try again.', 'elfit-ai-virtual-try-on-for-woocommerce');
                $this->plugin->db->update_job_by_token($token, [
                    'status'         => 'failed',
                    'progress'       => 100,
                    'status_message' => $timeout_msg,
                ]);
                $job->status = 'failed';
                $job->status_message = $timeout_msg;
                $job->progress = 100;

                // Record failure in audit logs so it appears in the Admin Logs table
                $client_ip = $this->plugin->rate_limiter->get_client_ip();
                $this->plugin->db->add_log([
                    'job_id'            => (int) $job->id,
                    'user_id'           => (int) $job->user_id,
                    'ip_address'        => $client_ip,
                    'product_id'        => (int) $job->product_id,
                    'variation_id'      => (int) $job->variation_id,
                    'model_used'        => $job->model_used ?: $this->plugin->get_active_primary_model(),
                    'status'            => 'failed',
                    'duration_sec'      => round(time() - $created, 2),
                    'prompt_tokens'     => 0,
                    'completion_tokens' => 0,
                    'cost_estimate'     => 0,
                    'user_image_url'    => $job->user_image_url,
                    'result_image_url'  => '',
                    'error_message'     => $timeout_msg,
                    'prompt_text'       => '',
                    'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => 'Generation request timed out or was interrupted before completion.']),
                ]);
            }
        }

        return new WP_REST_Response([
            'success'          => true,
            'token'            => $job->job_token,
            'status'           => $job->status,
            'progress'         => (int) $job->progress,
            'message'          => $job->status_message,
            'user_image_url'   => $job->user_image_url,
            'product_image_url'=> $job->product_image_url,
            'result_image_url' => $job->result_image_url,
        ], 200);
    }

    public function handle_log_client_error(WP_REST_Request $request): WP_REST_Response {
        $product_id     = absint($request->get_param('product_id'));
        $variation_id   = absint($request->get_param('variation_id'));
        $error_message  = sanitize_text_field($request->get_param('error_message'));
        $user_image_url = esc_url_raw($request->get_param('user_image_url'));

        if (empty($error_message)) {
            return new WP_REST_Response(['success' => false], 400);
        }

        $client_ip = $this->plugin->rate_limiter->get_client_ip();
        $user_id   = get_current_user_id();

        // Prevent log flooding: rate limit error reports per IP (max 10 error submissions per minute)
        $flood_key = 'fito_tryon_err_flood_' . md5($client_ip);
        $flood_count = (int) get_transient($flood_key);
        if ($flood_count >= 10) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Rate limit exceeded for client error reporting.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 429);
        }
        set_transient($flood_key, $flood_count + 1, MINUTE_IN_SECONDS);

        // Check if an identical failure was already logged within the last 60 seconds
        if (!$this->plugin->db->has_recent_failed_log($client_ip, $product_id, 60)) {
            $active_model = $this->plugin->get_active_primary_model();
            $client_err_log = [
                'job_id'            => 0,
                'user_id'           => $user_id,
                'ip_address'        => $client_ip,
                'product_id'        => $product_id,
                'variation_id'      => $variation_id,
                'model_used'        => $active_model,
                'status'            => 'failed',
                'duration_sec'      => 0,
                'prompt_tokens'     => 0,
                'completion_tokens' => 0,
                'cost_estimate'     => 0,
                'error_message'     => $error_message,
                'prompt_text'       => '',
                // translators: %s: Error message reported by client browser
                'response_text'     => Fito_Tryon_OpenRouter::sanitize_json_response(['error' => sprintf(__('Client/network error reported: %s', 'elfit-ai-virtual-try-on-for-woocommerce'), $error_message)]),
            ];
            $client_err_log = (array) apply_filters('fito_tryon_log_data', $client_err_log, [
                'user_image_url'   => $user_image_url,
                'result_image_url' => '',
            ]);
            $this->plugin->db->add_log($client_err_log);
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function handle_test_connection(WP_REST_Request $request): WP_REST_Response {
        $provider = sanitize_key((string) ($request->get_param('provider') ?: 'openrouter'));
        $api_key = $request->get_param('api_key');

        $allowed = $this->plugin->get_allowed_providers();
        if (!in_array($provider, $allowed, true)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or unsupported AI provider specified.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 400);
        }

        if ($provider === 'openrouter') {
            $result = $this->plugin->openrouter->test_connection($api_key);
        } else {
            $result = apply_filters('fito_tryon_test_connection', [
                'success' => false,
                'message' => __('Provider connection testing is not available.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], $provider, $api_key);
        }

        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public function handle_get_models(WP_REST_Request $request): WP_REST_Response {
        $provider = sanitize_key((string) ($request->get_param('provider') ?: 'openrouter'));

        $allowed = $this->plugin->get_allowed_providers();
        if (!in_array($provider, $allowed, true)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or unsupported AI provider specified.', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ], 400);
        }

        $force = $request->get_param('refresh') === '1' || $request->get_param('force') === '1';
        $api_key = $request->get_param('api_key');
        if ($api_key !== null) {
            $api_key = sanitize_text_field(trim((string) $api_key));
        }

        if ($provider === 'openrouter') {
            $was_cached = !$force && !empty($this->plugin->openrouter->get_cached_models());
            $models = $this->plugin->openrouter->fetch_live_models($force);
        } else {
            $was_cached = false;
            $models = (array) apply_filters('fito_tryon_fetch_models', [], $provider, $api_key, $force);
        }

        return new WP_REST_Response([
            'success'  => true,
            'provider' => $provider,
            'models'   => $models,
            'count'    => count($models),
            'cached'   => $was_cached,
            'ttl'      => DAY_IN_SECONDS,
        ], 200);
    }

    public function handle_delete_logs(WP_REST_Request $request): WP_REST_Response {
        $log_id = $request->get_param('log_id');
        if ($log_id === 'all') {
            $success = $this->plugin->db->clear_all_logs();
        } else {
            $success = $this->plugin->db->delete_log(absint($log_id));
        }

        return new WP_REST_Response([
            'success' => $success,
            'message' => $success ? __('Logs updated successfully.', 'elfit-ai-virtual-try-on-for-woocommerce') : __('Failed to update logs.', 'elfit-ai-virtual-try-on-for-woocommerce'),
        ], $success ? 200 : 500);
    }
}
