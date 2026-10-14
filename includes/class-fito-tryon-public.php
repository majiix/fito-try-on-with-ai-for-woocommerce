<?php
/**
 * Public frontend display, button injection, modal markup, and customer account tab.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Public {
    private Fito_Tryon $plugin;
    private bool $modal_printed = false;

    public function __construct(Fito_Tryon $plugin) {
        $this->plugin = $plugin;
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('elfit_tryon', [$this, 'render_shortcode']);

        // Button placement
        $position = get_option('fito_tryon_button_position', 'after_add_to_cart');
        if ($position === 'before_add_to_cart') {
            add_action('woocommerce_before_add_to_cart_button', [$this, 'render_product_button'], 25);
        } elseif ($position === 'after_add_to_cart') {
            add_action('woocommerce_after_add_to_cart_button', [$this, 'render_product_button'], 25);
        } elseif ($position === 'product_tab') {
            add_filter('woocommerce_product_tabs', [$this, 'add_product_tab']);
        }

        // Modal output in footer
        add_action('wp_footer', [$this, 'render_modal_markup']);

        // Filter contract for external plugins to query product eligibility cleanly.
        add_filter('fito_tryon_is_product_allowed', [$this, 'filter_is_product_allowed'], 10, 2);

        // Allow extensions to attach public hooks.
        do_action('fito_tryon_public_init', $this);
    }

    public function enqueue_assets(): void {
        global $post;
        $has_tryon_tag = is_a($post, 'WP_Post') && (
            has_shortcode((string) $post->post_content, 'elfit_tryon') ||
            has_block('fito/tryon-button', $post) ||
            has_block('fito-tryon/button', $post)
        );
        if (!is_product() && !is_shop() && !is_product_category() && !is_account_page() && !$has_tryon_tag) {
            return;
        }

        wp_enqueue_style(
            'fito-tryon-public',
            FITO_TRYON_URL . 'assets/css/public.css',
            [],
            FITO_TRYON_VERSION
        );

        wp_enqueue_script(
            'fito-tryon-public',
            FITO_TRYON_URL . 'assets/js/public.js',
            ['jquery'],
            FITO_TRYON_VERSION,
            true
        );

        $login_cfg = $this->get_login_config();
        $access_level = get_option('fito_tryon_access_level', 'everyone');
        $require_login = ($access_level === 'logged_in_only');

        $is_prod_allowed = true;
        if (is_product()) {
            $current_prod_id = get_the_ID();
            if ($current_prod_id) {
                $is_prod_allowed = $this->is_product_allowed($current_prod_id);
            }
        }

        $script_data = apply_filters('fito_tryon_public_script_data', [
            'restUrl'              => esc_url_raw(rest_url('fito-tryon/v1/')),
            'nonce'                => wp_create_nonce('wp_rest'),
            'isUserLoggedIn'       => is_user_logged_in(),
            'currentUserId'        => get_current_user_id(),
            'requireLogin'         => $require_login,
            'loginUrl'             => esc_url_raw($login_cfg['login_url']),
            'loginNotice'          => $login_cfg['login_notice'],
            'sampleImageUrl'       => esc_url_raw($login_cfg['sample_image']),
            'isProductAllowed'     => $is_prod_allowed,
            'consentText'          => Fito_Tryon_Privacy::get_consent_text(),
            'statusMessages'       => [
                __('Connecting to fitting engine...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                __('Analyzing pose and camera angle...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                __('Fitting garment drape and contours...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                __('Matching lighting and surface reflections...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                __('Finalizing your try-on preview...', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
            'i18n'                 => [
                'invalidImage'   => __('Choose a JPG, PNG, or WebP image.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'fileTooLarge'   => __('Choose an image smaller than 10 MB.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'cameraError'    => __('Unable to access camera. Check browser permissions or upload a photo.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'needConsent'    => __('Agree to the privacy notice to continue.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'needPhoto'      => __('Select or take a photo to continue.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'genericError'   => __('Unable to generate try-on. Check your connection and try again.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'timeoutError'   => __('Try-on request timed out. Check your connection and try again.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'shareCopied'    => __('Link copied to clipboard', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'fullscreen'     => __('Full screen', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'exitFullscreen' => __('Exit full screen', 'elfit-ai-virtual-try-on-for-woocommerce'),
            ],
        ]);

        wp_localize_script('fito-tryon-public', 'fitoTryonData', $script_data);
    }

    private function get_login_config(): array {
        $raw_login_url = get_option('fito_tryon_login_url', '');
        if (empty($raw_login_url)) {
            $raw_login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        }
        $redirect_dest = is_singular() ? get_permalink() : home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $final_login_url = $redirect_dest ? add_query_arg('redirect_to', urlencode($redirect_dest), $raw_login_url) : $raw_login_url;

        $login_notice = get_option('fito_tryon_login_notice', '');
        if (empty($login_notice)) {
            $login_notice = __('Please sign up or log in to your account to use our AI Virtual Try-On feature and preview how this item looks on you.', 'elfit-ai-virtual-try-on-for-woocommerce');
        }

        $sample_img_id = absint(get_option('fito_tryon_sample_image_id', 0));
        $sample_image_url = $sample_img_id ? wp_get_attachment_image_url($sample_img_id, 'large') : '';
        if (empty($sample_image_url)) {
            $sample_image_url = FITO_TRYON_URL . 'assets/images/sample-tryon.jfif';
        }

        return [
            'login_url'    => $final_login_url,
            'login_notice' => $login_notice,
            'sample_image' => $sample_image_url,
        ];
    }

    /**
     * Check if a product is allowed for try-on based on category rules.
     * Statically memoized to avoid redundant option reads and taxonomy traversals in loops.
     *
     * @param int|\WC_Product $product_or_id
     * @return bool
     */
    public function is_product_allowed($product_or_id): bool {
        static $allowed_cat_ints = null;
        static $memo = [];

        if ($allowed_cat_ints === null) {
            $allowed_cats = get_option('fito_tryon_allowed_categories', []);
            $allowed_cat_ints = (!empty($allowed_cats) && is_array($allowed_cats)) ? array_map('intval', $allowed_cats) : [];
        }

        if (empty($allowed_cat_ints)) {
            return true; // All allowed by default when none specified
        }

        $product = ($product_or_id instanceof \WC_Product) ? $product_or_id : wc_get_product($product_or_id);
        if (!$product) {
            return false;
        }

        $product_id = $product->get_id();
        if (isset($memo[$product_id])) {
            return $memo[$product_id];
        }

        // If product is a variation, check the parent product categories
        $target_product = $product;
        if ($target_product->is_type('variation')) {
            $parent_id = $target_product->get_parent_id();
            $target_product = wc_get_product($parent_id);
            if (!$target_product) {
                $memo[$product_id] = false;
                return false;
            }
        }

        $product_cats = $target_product->get_category_ids();
        if (empty($product_cats)) {
            $memo[$product_id] = false;
            return false;
        }

        // 1. Direct category match
        $intersect = array_intersect($product_cats, $allowed_cat_ints);
        if (!empty($intersect)) {
            $memo[$product_id] = true;
            return true;
        }

        // 2. Ancestor categories check (parent category enabled covers its subcategories)
        foreach ($product_cats as $cat_id) {
            $ancestors = get_ancestors($cat_id, 'product_cat');
            if (!empty($ancestors) && !empty(array_intersect($ancestors, $allowed_cat_ints))) {
                $memo[$product_id] = true;
                return true;
            }
        }

        $memo[$product_id] = false;
        return false;
    }

    /**
     * Filter callback to check whether a product is allowed for virtual try-on.
     *
     * @param bool $default
     * @param mixed $product_or_id
     * @return bool
     */
    public function filter_is_product_allowed(bool $default, mixed $product_or_id): bool {
        return $this->is_product_allowed($product_or_id);
    }

    public function render_product_button(): void {
        global $product;
        $current_product = $product instanceof WC_Product ? $product : wc_get_product(get_the_ID());
        if (!$current_product || !$this->is_product_allowed($current_product)) {
            return;
        }

        $this->print_button_html($current_product);
    }

    public function add_product_tab(array $tabs): array {
        global $product;
        $current_product = $product instanceof WC_Product ? $product : wc_get_product(get_the_ID());
        if (!$current_product || !$this->is_product_allowed($current_product)) {
            return $tabs;
        }

        $default_title = $this->get_button_text();
        $tab_title = apply_filters('fito_tryon_button_text', $default_title, $current_product);

        $tabs['fito_tryon_tab'] = [
            'title'    => $tab_title,
            'priority' => 30,
            'callback' => [$this, 'render_product_tab_content'],
        ];

        return $tabs;
    }

    public function render_product_tab_content(): void {
        global $product;
        if ($product && $this->is_product_allowed($product)) {
            echo '<div class="fito-tab-wrapper">';
            echo '<p>' . esc_html__('See how this product looks on you using our virtual AI fitting room.', 'elfit-ai-virtual-try-on-for-woocommerce') . '</p>';
            $this->print_button_html($product);
            echo '</div>';
        }
    }

    /**
     * Render the [fito_tryon] shortcode.
     *
     * @param array<string, mixed>|string $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode($atts = []): string {
        $atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'product_id' => 0,
            'text'       => '',
        ], $atts);

        $product_id = absint($atts['product_id']);
        if (!$product_id && is_product()) {
            $product_id = get_the_ID();
        }

        $product = wc_get_product($product_id);
        if (!$product || !$this->is_product_allowed($product)) {
            return '';
        }

        ob_start();
        $this->print_button_html($product, $atts['text']);
        return (string) ob_get_clean();
    }

    public function render_block(array $attributes = []): string {
        $btn_text = !empty($attributes['buttonText']) ? sanitize_text_field($attributes['buttonText']) : '';
        return $this->render_shortcode(['text' => $btn_text]);
    }

    /**
     * Get configured try-on button text with fallback to default.
     *
     * @return string
     */
    public function get_button_text(): string {
        $configured = trim((string) get_option('fito_tryon_button_text', ''));
        if ($configured !== '') {
            return $configured;
        }
        return __('Try It On with AI', 'elfit-ai-virtual-try-on-for-woocommerce');
    }

    private function print_button_html(WC_Product $product, string $custom_text = ''): void {
        $default_text  = $this->get_button_text();
        $resolved_text = apply_filters('fito_tryon_button_text', $default_text, $product);
        $btn_text      = !empty($custom_text) ? $custom_text : $resolved_text;
        $prod_image    = wp_get_attachment_image_url($product->get_image_id(), 'large') ?: '';
        ?>
        <div class="fito-tryon-button-wrapper">
            <button type="button"
                    class="fito-tryon-btn fito-tryon-btn--single"
                    data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                    data-product-title="<?php echo esc_attr($product->get_name()); ?>"
                    data-product-image="<?php echo esc_url($prod_image); ?>"
                    aria-haspopup="dialog">
                <span class="fito-btn-icon-spark">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
                    </svg>
                </span>
                <span class="fito-btn-label"><?php echo esc_html($btn_text); ?></span>
            </button>
        </div>
        <?php
    }

    public function render_modal_markup(): void {
        if ($this->modal_printed) {
            return;
        }
        $this->modal_printed = true;

        $login_cfg = $this->get_login_config();
        $final_login_url = $login_cfg['login_url'];
        $login_notice = $login_cfg['login_notice'];
        $sample_image_url = $login_cfg['sample_image'];
        ?>
        <div id="fito-tryon-modal" class="fito-modal" role="dialog" aria-modal="true" aria-hidden="true" style="display: none;">
            <div class="fito-modal-backdrop"></div>
            <div class="fito-modal-dialog">

                <!-- Modal Header -->
                <div class="fito-modal-header">
                    <div class="fito-modal-header-info">
                        <div class="fito-header-badge">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
                            </svg>
                            <span><?php esc_html_e('Virtual fitting', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </div>
                    </div>
                    <div class="fito-modal-header-actions">
                        <button type="button" class="fito-modal-close" aria-label="<?php esc_attr_e('Close virtual try-on', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body: Step Login Required (Guests when access is restricted) -->
                <div class="fito-modal-body fito-step-login-required" data-step="login-required" style="display: none;">
                    <div class="fito-login-prompt-wrapper">
                        <div class="fito-login-sample-card">
                            <div class="fito-login-sample-badge">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor">
                                    <path d="M12 2l2.4 7.2h7.6l-6 4.8 2.4 7.2-6.4-4.8-6.4 4.8 2.4-7.2-6-4.8h7.6z"/>
                                </svg>
                                <span><?php esc_html_e('Virtual Try-On Preview', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </div>
                            <div class="fito-login-sample-media">
                                <img src="<?php echo esc_url($sample_image_url); ?>" alt="<?php esc_attr_e('Virtual Try-On Preview', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>" id="fito-login-sample-img" loading="lazy">
                            </div>
                        </div>
                        <h4 class="fito-login-prompt-title"><?php esc_html_e('Account required', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h4>
                        <p class="fito-login-prompt-text"><?php echo esc_html($login_notice); ?></p>
                        <div class="fito-login-prompt-actions">
                            <a href="<?php echo esc_url($final_login_url); ?>" class="fito-btn fito-btn--primary fito-btn--login" id="fito-btn-login-redirect">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                    <polyline points="10 17 15 12 10 7"/>
                                    <line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                                <span><?php esc_html_e('Log in or sign up', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </a>
                            <button type="button" class="fito-btn fito-btn--outline" id="fito-btn-login-close">
                                <?php esc_html_e('Close', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Body: Step 1 Upload / Camera -->
                <div class="fito-modal-body fito-step-upload" data-step="upload">
                    <div class="fito-guidance-card">
                        <div class="fito-guidance-icon">💡</div>
                        <div class="fito-guidance-text">
                            <strong><?php esc_html_e('Photo tip:', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></strong>
                            <?php esc_html_e('Use a clear, well-lit photo facing the camera. Avoid busy backgrounds or strong filters.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                        </div>
                    </div>

                    <!-- Dropzone -->
                    <div class="fito-dropzone" id="fito-dropzone">
                        <input type="file" id="fito-file-input" accept="image/jpeg,image/png,image/webp" capture="user" style="display: none;">
                        <div class="fito-dropzone-content">
                            <div class="fito-upload-cloud-icon">
                                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/>
                                </svg>
                            </div>
                            <h4 class="fito-dropzone-title"><?php esc_html_e('Add a photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h4>
                            <p class="fito-dropzone-subtitle"><?php esc_html_e('Drag and drop an image, or choose an option below', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>

                            <div class="fito-upload-buttons">
                                <button type="button" class="fito-btn fito-btn--secondary" id="fito-btn-browse">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span><?php esc_html_e('Upload photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                                </button>
                                <button type="button" class="fito-btn fito-btn--secondary" id="fito-btn-camera">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                                    <span><?php esc_html_e('Take photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                                </button>
                            </div>
                            <span class="fito-dropzone-formats"><?php esc_html_e('JPG, PNG, or WebP up to 10 MB', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </div>
                    </div>

                    <!-- Live Camera Feed Container -->
                    <div class="fito-camera-container" id="fito-camera-container" style="display: none;">
                        <video id="fito-camera-stream" autoplay playsinline muted></video>
                        <div class="fito-camera-controls">
                            <button type="button" class="fito-btn fito-btn--primary" id="fito-btn-capture">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                                <span><?php esc_html_e('Take photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </button>
                            <button type="button" class="fito-btn fito-btn--outline" id="fito-btn-camera-close">
                                <?php esc_html_e('Cancel', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Photo Preview Container -->
                    <div class="fito-crop-container" id="fito-crop-container" style="display: none;">
                        <div class="fito-crop-preview-wrapper" id="fito-crop-preview-wrapper">
                            <img id="fito-crop-image" src="" alt="<?php esc_attr_e('Uploaded user photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                        </div>
                        <div class="fito-crop-actions">
                            <button type="button" class="fito-btn fito-btn--ghost" id="fito-crop-change">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                                <span><?php esc_html_e('Choose another photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Consent Gate -->
                    <div class="fito-consent-wrapper">
                        <label class="fito-consent-label">
                            <input type="checkbox" id="fito-consent-checkbox" required>
                            <span class="fito-consent-text">
                                <?php echo esc_html(Fito_Tryon_Privacy::get_consent_text()); ?>
                            </span>
                        </label>
                        <p class="fito-privacy-notice">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span><?php esc_html_e('Photos are deleted within 24 hours and never used to train public models.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <button type="button" class="fito-btn fito-btn--primary fito-btn--full" id="fito-btn-submit" disabled>
                        <span class="fito-btn-label"><?php esc_html_e('Generate try-on', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </div>

                <!-- Modal Body: Step 2 Generating Progress -->
                <div class="fito-modal-body fito-step-loading" data-step="loading" style="display: none;">
                    <div class="fito-loading-wrapper">
                        <div class="fito-loading-avatar-duo">
                            <div class="fito-avatar fito-avatar-user"><img id="fito-loading-user-thumb" src="" alt="User thumbnail"></div>
                            <div class="fito-avatar-spark">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
                            </div>
                            <div class="fito-avatar fito-avatar-product"><img id="fito-loading-prod-thumb" src="" alt="Product thumbnail"></div>
                        </div>

                        <h4 class="fito-loading-title"><?php esc_html_e('Creating your try-on', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h4>
                        <div class="fito-progress-track">
                            <div class="fito-progress-fill" id="fito-progress-bar" style="width: 15%;"></div>
                        </div>
                        <p class="fito-loading-message" id="fito-rotating-message"><?php esc_html_e('Connecting to fitting engine...', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                    </div>
                </div>

                <!-- Modal Body: Step 3 Result View -->
                <div class="fito-modal-body fito-step-result" data-step="result" style="display: none;">
                    <!-- Before/After Comparison Slider Container -->
                    <div class="fito-slider-stage" id="fito-slider-stage">
                        <div class="fito-slider-container" id="fito-slider-container">
                            <div class="fito-slider-before">
                                <img id="fito-slider-img-before" src="" alt="<?php esc_attr_e('Original user photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                                <span class="fito-tag fito-tag--before"><?php esc_html_e('Before', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </div>
                            <div class="fito-slider-after" id="fito-slider-after">
                                <img id="fito-slider-img-after" src="" alt="<?php esc_attr_e('AI try-on result', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                                <span class="fito-tag fito-tag--after"><?php esc_html_e('After', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                            </div>
                            <div class="fito-slider-handle" id="fito-slider-handle">
                                <div class="fito-handle-line"></div>
                                <div class="fito-handle-circle">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 8 4 12 8 16"/><polyline points="16 16 20 12 16 8"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Stage Fullscreen Controls -->
                        <button type="button" class="fito-stage-fullscreen-btn" id="fito-stage-fullscreen-btn" title="<?php esc_attr_e('View full screen', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                        </button>
                        <button type="button" class="fito-exit-fullscreen-btn" id="fito-exit-fullscreen-btn" title="<?php esc_attr_e('Exit full screen', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            <span><?php esc_html_e('Exit full screen', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </button>
                    </div>

                    <!-- Result Action Toolbar -->
                    <div class="fito-result-toolbar">
                        <button type="button" class="fito-tool-btn" id="fito-btn-download" title="<?php esc_attr_e('Download image', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span><?php esc_html_e('Download', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </button>
                        <button type="button" class="fito-tool-btn" id="fito-btn-share" title="<?php esc_attr_e('Share try-on', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            <span><?php esc_html_e('Share', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </button>
                        <?php do_action('fito_tryon_modal_result_actions'); ?>
                    </div>

                    <!-- Bottom navigation -->
                    <div class="fito-result-footer">
                        <button type="button" class="fito-btn fito-btn--outline" id="fito-btn-retry">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                            <span><?php esc_html_e('Try another photo', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </button>
                        <button type="button" class="fito-btn fito-btn--primary" id="fito-btn-done">
                            <span><?php esc_html_e('Back to product', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
