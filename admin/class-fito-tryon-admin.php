<?php
/**
 * Admin dashboard, settings, analytics, and audit logs manager.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Admin {
    private Fito_Tryon $plugin;

    public function __construct(Fito_Tryon $plugin) {
        $this->plugin = $plugin;
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_fito_tryon_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_fito_tryon_save_prompts', [$this, 'handle_save_prompts']);
        add_action('admin_post_fito_tryon_purge_photos', [$this, 'handle_purge_photos']);
    }

    public function register_admin_menus(): void {
        $cap = 'manage_woocommerce';

        add_menu_page(
            __('AI Try-On', 'fito-try-on-with-ai-for-woocommerce'),
            __('AI Try-On', 'fito-try-on-with-ai-for-woocommerce'),
            $cap,
            'fito-tryon',
            [$this, 'render_dashboard_page'],
            'dashicons-camera',
            56
        );

        add_submenu_page(
            'fito-tryon',
            __('Dashboard', 'fito-try-on-with-ai-for-woocommerce'),
            __('Dashboard', 'fito-try-on-with-ai-for-woocommerce'),
            $cap,
            'fito-tryon',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Prompts', 'fito-try-on-with-ai-for-woocommerce'),
            __('Prompts', 'fito-try-on-with-ai-for-woocommerce'),
            $cap,
            'fito-tryon-prompts',
            [$this, 'render_prompts_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Try-On Logs', 'fito-try-on-with-ai-for-woocommerce'),
            __('Audit Logs', 'fito-try-on-with-ai-for-woocommerce'),
            $cap,
            'fito-tryon-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Settings', 'fito-try-on-with-ai-for-woocommerce'),
            __('Settings', 'fito-try-on-with-ai-for-woocommerce'),
            $cap,
            'fito-tryon-settings',
            [$this, 'render_settings_page']
        );

        if (apply_filters('fito_tryon_show_pro_card', true)) {
            add_submenu_page(
                'fito-tryon',
                __('Go Pro', 'fito-try-on-with-ai-for-woocommerce'),
                __('Go Pro ⭐', 'fito-try-on-with-ai-for-woocommerce'),
                $cap,
                'fito-tryon-go-pro',
                [$this, 'render_go_pro_page']
            );
        }

        do_action('fito_tryon_admin_submenus', 'fito-tryon', $cap);
    }

    public function enqueue_admin_assets(string $hook): void {
        if (!str_contains($hook, 'fito-tryon')) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'fito-tryon-admin',
            FITO_TRYON_URL . 'assets/css/admin.css',
            [],
            FITO_TRYON_VERSION
        );

        $is_dashboard = ($hook === 'toplevel_page_fito-tryon');

        $admin_js_deps = ['jquery'];
        $chart_data = [];

        if ($is_dashboard) {
            // Chart.js for analytics dashboard only (bundled locally for WordPress.org compliance)
            wp_enqueue_script(
                'chartjs',
                FITO_TRYON_URL . 'assets/js/chart.umd.min.js',
                [],
                '4.4.1',
                true
            );
            $admin_js_deps[] = 'chartjs';
            $chart_data = $this->plugin->db->get_chart_data(14);
        }

        wp_enqueue_script(
            'fito-tryon-admin',
            FITO_TRYON_URL . 'assets/js/admin.js',
            $admin_js_deps,
            FITO_TRYON_VERSION,
            true
        );

        wp_localize_script('fito-tryon-admin', 'fitoAdminData', [
            'restUrl'   => esc_url_raw(rest_url('fito-tryon/v1/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'chartData' => $chart_data,
            'i18n'      => [
                'testing'        => __('Testing connection...', 'fito-try-on-with-ai-for-woocommerce'),
                'testSuccess'    => __('Connection verified successfully!', 'fito-try-on-with-ai-for-woocommerce'),
                'fetchingModels' => __('Fetching live models...', 'fito-try-on-with-ai-for-woocommerce'),
                'confirmPurge'     => __('Are you sure you want to delete all temporary customer try-on photos? This cannot be undone.', 'fito-try-on-with-ai-for-woocommerce'),
                'confirmClear'     => __('Are you sure you want to clear all audit logs?', 'fito-try-on-with-ai-for-woocommerce'),
                'sentPrompt'       => __('Sent AI Prompt', 'fito-try-on-with-ai-for-woocommerce'),
                'receivedResponse' => __('Received AI Response', 'fito-try-on-with-ai-for-woocommerce'),
                'noPrompt'         => __('No sent prompt recorded for this log entry.', 'fito-try-on-with-ai-for-woocommerce'),
                'noResponse'       => __('No AI response recorded for this log entry.', 'fito-try-on-with-ai-for-woocommerce'),
                'copy'             => __('Copy', 'fito-try-on-with-ai-for-woocommerce'),
                'copied'           => __('Copied!', 'fito-try-on-with-ai-for-woocommerce'),
            ],
        ]);
    }

    public function render_dashboard_page(): void {
        $summary = $this->plugin->db->get_analytics_summary();
        $top_products = $this->plugin->db->get_top_products(5);
        ?>
        <div class="wrap fito-admin-wrap">
            <header class="fito-admin-header">
                <div class="fito-brand">
                    <div class="fito-brand-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
                        </svg>
                    </div>
                    <div>
                        <h1>
                            <?php esc_html_e('AI Try-On Analytics & Overview', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        </h1>
                        <p class="fito-subtitle"><?php esc_html_e('Monitor customer engagement, AI model performance, and conversion signals.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="fito-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-settings')); ?>" class="button button-primary">
                        <?php esc_html_e('Plugin Settings', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </a>
                </div>
            </header>

            <!-- KPI Cards Grid -->
            <div class="fito-kpi-grid">
                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Today / This Week', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['today']); ?> <span class="fito-kpi-sub">/ <?php echo esc_html($summary['week']); ?></span></div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Try-on generations completed', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Monthly Total', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['month']); ?></div>
                    <div class="fito-kpi-desc">
                        <?php
                        // translators: %d: Total all-time try-on count
                        printf(esc_html__('Lifetime all-time: %d', 'fito-try-on-with-ai-for-woocommerce'), absint($summary['total']));
                        ?>
                    </div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Success Rate', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val fito-val--accent"><?php echo esc_html($summary['success_rate']); ?>%</div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Healthy AI provider completion', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Avg Generation Time', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['avg_duration']); ?>s</div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Customer wait time before preview', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Estimated API Spend', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val">$<?php echo esc_html(number_format($summary['total_spend'], 4)); ?></div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Direct AI provider usage', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                </div>
            </div>

            <!-- Chart & Top Products Row -->
            <div class="fito-dashboard-grid">
                <!-- Volume Chart -->
                <div class="fito-card fito-chart-card">
                    <div class="fito-card-header">
                        <h3><?php esc_html_e('Try-On Volume (Past 14 Days)', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    </div>
                    <div class="fito-card-body">
                        <canvas id="fitoAnalyticsChart" height="260"></canvas>
                    </div>
                </div>

                <!-- Top Tried-On Products -->
                <div class="fito-card fito-top-products-card">
                    <div class="fito-card-header">
                        <h3><?php esc_html_e('Top Tried-On Products', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    </div>
                    <div class="fito-card-body">
                        <?php if (empty($top_products)) : ?>
                            <p class="fito-muted"><?php esc_html_e('No product try-on records recorded yet.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                        <?php else : ?>
                            <ul class="fito-top-list">
                                <?php foreach ($top_products as $item) : ?>
                                    <li class="fito-top-item">
                                        <div class="fito-top-thumb">
                                            <?php if (!empty($item['image'])) : ?>
                                                <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
                                            <?php else : ?>
                                                <div class="fito-placeholder-thumb"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fito-top-info">
                                            <a href="<?php echo esc_url($item['url']); ?>" target="_blank" class="fito-top-title">
                                                <?php echo esc_html($item['name']); ?>
                                            </a>
                                            <span class="fito-top-sub">
                                                <?php
                                                // translators: 1: Total try-on count, 2: Success rate percentage
                                                printf(esc_html__('%1$d try-ons (%2$d%% success)', 'fito-try-on-with-ai-for-woocommerce'), absint($item['tryon_count']), absint($item['tryon_count'] > 0 ? round(($item['success_count'] / $item['tryon_count']) * 100) : 0));
                                                ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_logs_page(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search query for logs display table.
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter for logs display table.
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $allowed_limits = [10, 20, 50, 100];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination limit parameter for logs display table.
        $limit  = isset($_GET['limit']) ? absint($_GET['limit']) : 10;
        if (!in_array($limit, $allowed_limits, true)) {
            $limit = 10;
        }

        $total_items = $this->plugin->db->get_logs_count([
            'search' => $search,
            'status' => $status,
        ]);
        $total_pages = max(1, (int) ceil($total_items / $limit));

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination page parameter for logs display table.
        $page   = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $limit;

        $logs = $this->plugin->db->get_logs([
            'search' => $search,
            'status' => $status,
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        if (!empty($logs)) {
            $log_product_ids = array_filter(array_unique(array_map('intval', array_column($logs, 'product_id'))));
            if (!empty($log_product_ids) && function_exists('_prime_post_caches')) {
                _prime_post_caches($log_product_ids, true, true);
            }

            $log_user_ids = array_filter(array_unique(array_map('intval', array_column($logs, 'user_id'))));
            if (!empty($log_user_ids) && function_exists('cache_users')) {
                cache_users($log_user_ids);
            }
        }

        $start_item = $total_items > 0 ? $offset + 1 : 0;
        $end_item   = min($offset + count($logs), $total_items);
        ?>
        <div class="wrap fito-admin-wrap">
            <header class="fito-admin-header">
                <div class="fito-brand">
                    <h1><?php esc_html_e('Try-On Audit Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></h1>
                </div>
                <div class="fito-header-actions">
                    <button type="button" class="button button-link-delete" id="fito-btn-clear-logs">
                        <?php esc_html_e('Clear All Logs', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </button>
                </div>
            </header>

            <!-- Filters -->
            <div class="fito-logs-filter-bar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="fito-tryon-logs">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search model, IP, error...', 'fito-try-on-with-ai-for-woocommerce'); ?>" class="regular-text">
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                        <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Success', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                        <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                    </select>
                    <select name="limit">
                        <option value="10" <?php selected($limit, 10); ?>><?php esc_html_e('10 per page', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                        <option value="20" <?php selected($limit, 20); ?>><?php esc_html_e('20 per page', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                        <option value="50" <?php selected($limit, 50); ?>><?php esc_html_e('50 per page', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                        <option value="100" <?php selected($limit, 100); ?>><?php esc_html_e('100 per page', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                    <?php if (!empty($search) || !empty($status) || $limit !== 10) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-logs')); ?>" class="button button-secondary"><?php esc_html_e('Reset', 'fito-try-on-with-ai-for-woocommerce'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="fito-card">
                <table class="wp-list-table widefat fixed striped fito-table">
                    <thead>
                        <tr>
                            <th width="65"><?php esc_html_e('ID', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="130"><?php esc_html_e('Date / Time', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="115"><?php esc_html_e('User', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Product', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="115"><?php esc_html_e('Previews', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Model Used', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="95"><?php esc_html_e('Status', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Duration', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Cost Est.', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Actions', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                            <tr>
                                <td colspan="10" class="fito-text-center"><?php esc_html_e('No log entries match your criteria.', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : 
                                $user_obj = !empty($log->user_id) ? get_userdata((int) $log->user_id) : null;
                                // translators: %d: WordPress user ID number
                                $user_name = $user_obj ? $user_obj->display_name : ($log->user_id > 0 ? sprintf(__('User #%d', 'fito-try-on-with-ai-for-woocommerce'), $log->user_id) : __('Guest', 'fito-try-on-with-ai-for-woocommerce'));
                                $user_edit_link = ($user_obj && current_user_can('edit_users')) ? get_edit_user_link($log->user_id) : '';

                                $product = !empty($log->product_id) ? wc_get_product($log->product_id) : null;
                                if ($product) {
                                    $prod_name = $product->get_name();
                                    $view_url  = get_permalink($log->product_id);
                                    $edit_url  = get_edit_post_link($log->product_id);
                                } elseif (!empty($log->product_id)) {
                                    // translators: %d: Product ID
                                    $prod_name = sprintf(__('Deleted Product (ID %d)', 'fito-try-on-with-ai-for-woocommerce'), $log->product_id);
                                    $view_url  = '';
                                    $edit_url  = '';
                                } else {
                                    $prod_name = __('General / No Product', 'fito-try-on-with-ai-for-woocommerce');
                                    $view_url  = '';
                                    $edit_url  = '';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo esc_html($log->id); ?></td>
                                    <td><?php echo esc_html(wp_date('M j, H:i', strtotime($log->created_at))); ?></td>
                                    <td>
                                        <?php if (!empty($user_edit_link)) : ?>
                                            <a href="<?php echo esc_url($user_edit_link); ?>" target="_blank" title="<?php echo esc_attr($user_obj ? $user_obj->user_email : ''); ?>">
                                                <strong><?php echo esc_html($user_name); ?></strong>
                                            </a>
                                            <?php if ($user_obj) : ?>
                                                <div class="fito-row-meta fito-muted"><?php echo esc_html($user_obj->user_login); ?></div>
                                            <?php endif; ?>
                                        <?php elseif (!empty($log->user_id)) : ?>
                                            <span><?php echo esc_html($user_name); ?></span>
                                        <?php else : ?>
                                            <span class="fito-muted"><?php esc_html_e('Guest', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fito-log-product-cell">
                                            <span class="fito-log-product-title">
                                                <?php if (!empty($edit_url)) : ?>
                                                    <a href="<?php echo esc_url($edit_url); ?>" target="_blank" class="fito-log-product-name">
                                                        <?php echo esc_html($prod_name); ?>
                                                    </a>
                                                <?php elseif (!empty($view_url)) : ?>
                                                    <a href="<?php echo esc_url($view_url); ?>" target="_blank" class="fito-log-product-name">
                                                        <?php echo esc_html($prod_name); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="fito-muted"><?php echo esc_html($prod_name); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!empty($edit_url) || !empty($view_url)) : ?>
                                                <div class="fito-row-actions fito-log-product-links">
                                                    <?php if (!empty($edit_url)) : ?>
                                                        <a href="<?php echo esc_url($edit_url); ?>" target="_blank" class="fito-action-link" title="<?php esc_attr_e('Edit product in WooCommerce admin', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Edit', 'fito-try-on-with-ai-for-woocommerce'); ?></a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($edit_url) && !empty($view_url)) : ?>
                                                        <span class="fito-sep">|</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($view_url)) : ?>
                                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" class="fito-action-link fito-action-view" title="<?php esc_attr_e('View product on store frontend', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('View', 'fito-try-on-with-ai-for-woocommerce'); ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($log->user_image_url) || !empty($log->result_image_url)) : ?>
                                            <div class="fito-log-thumb-group">
                                                <?php if (!empty($log->user_image_url)) : ?>
                                                    <a href="<?php echo esc_url($log->user_image_url); ?>" target="_blank" rel="noopener noreferrer" class="fito-log-thumb" data-preview-url="<?php echo esc_url($log->user_image_url); ?>" data-preview-title="<?php esc_attr_e('Before (User Photo)', 'fito-try-on-with-ai-for-woocommerce'); ?>" title="<?php esc_attr_e('Click to open image in new tab', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                        <img src="<?php echo esc_url($log->user_image_url); ?>" alt="<?php esc_attr_e('Before', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                        <span class="fito-thumb-tag"><?php esc_html_e('Before', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($log->result_image_url)) : ?>
                                                    <a href="<?php echo esc_url($log->result_image_url); ?>" target="_blank" rel="noopener noreferrer" class="fito-log-thumb" data-preview-url="<?php echo esc_url($log->result_image_url); ?>" data-preview-title="<?php esc_attr_e('After (Try-On Result)', 'fito-try-on-with-ai-for-woocommerce'); ?>" title="<?php esc_attr_e('Click to open image in new tab', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                        <img src="<?php echo esc_url($log->result_image_url); ?>" alt="<?php esc_attr_e('After', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                        <span class="fito-thumb-tag"><?php esc_html_e('After', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="fito-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo esc_html($log->model_used ?: '-'); ?></code></td>
                                    <td>
                                        <span class="fito-badge fito-badge--<?php echo esc_attr($log->status); ?>">
                                            <?php echo esc_html(ucfirst($log->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log->duration_sec); ?>s</td>
                                    <td>$<?php echo esc_html(number_format((float) $log->cost_estimate, 4)); ?></td>
                                    <td>
                                        <button type="button" class="button button-small fito-inspect-btn" 
                                                data-id="<?php echo esc_attr($log->id); ?>"
                                                data-ip="<?php echo esc_attr($log->ip_address); ?>"
                                                data-user-name="<?php echo esc_attr($user_name); ?>"
                                                data-user-url="<?php echo esc_url($user_edit_link); ?>"
                                                data-prod-name="<?php echo esc_attr($prod_name); ?>"
                                                data-prod-edit="<?php echo esc_url($edit_url ?: ''); ?>"
                                                data-prod-view="<?php echo esc_url($view_url ?: ''); ?>"
                                                data-error="<?php echo esc_attr($log->error_message ?: ''); ?>"
                                                data-prompt-tok="<?php echo esc_attr($log->prompt_tokens); ?>"
                                                data-comp-tok="<?php echo esc_attr($log->completion_tokens); ?>"
                                                data-user-img="<?php echo esc_url(!empty($log->user_image_url) ? $log->user_image_url : ''); ?>"
                                                data-result-img="<?php echo esc_url(!empty($log->result_image_url) ? $log->result_image_url : ''); ?>">
                                            <?php esc_html_e('Inspect', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </button>
                                        <div class="fito-log-hidden-data" style="display: none;">
                                            <div class="fito-log-raw-prompt"><?php echo esc_html($log->prompt_text ?? ''); ?></div>
                                            <div class="fito-log-raw-response"><?php echo esc_html($log->response_text ?? ''); ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="fito-pagination-bar">
                <div class="fito-pagination-info">
                    <?php if ($total_items > 0) : ?>
                        <?php
                        // translators: 1: Start item number, 2: End item number, 3: Total logs count
                        printf(esc_html__('Showing %1$d to %2$d of %3$d logs', 'fito-try-on-with-ai-for-woocommerce'), absint($start_item), absint($end_item), absint($total_items));
                        ?>
                    <?php else : ?>
                        <?php esc_html_e('No logs found', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    <?php endif; ?>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav class="fito-pagination-nav" aria-label="<?php esc_attr_e('Logs pagination', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base'      => add_query_arg('paged', '%#%', remove_query_arg('paged')),
                            'format'    => '',
                            'prev_text' => '&laquo; ' . esc_html__('Prev', 'fito-try-on-with-ai-for-woocommerce'),
                            'next_text' => esc_html__('Next', 'fito-try-on-with-ai-for-woocommerce') . ' &raquo;',
                            'total'     => $total_pages,
                            'current'   => $page,
                        ]));
                        ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Inspect Modal -->
        <div id="fito-log-modal" class="fito-admin-modal" style="display: none;">
            <div class="fito-admin-modal-dialog">
                <h3><?php esc_html_e('Log Details', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                <div id="fito-log-modal-content"></div>
                <button type="button" class="button" id="fito-close-log-modal"><?php esc_html_e('Close', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
            </div>
        </div>
        <?php
    }

    public function render_settings_page(): void {
        $allowed_providers    = $this->plugin->get_allowed_providers();
        $ai_provider          = (string) get_option('fito_tryon_ai_provider', 'openrouter');
        if (!in_array($ai_provider, $allowed_providers, true)) {
            $ai_provider = 'openrouter';
        }
        $api_key              = get_option('fito_tryon_openrouter_api_key', '');
        $primary_model        = get_option('fito_tryon_primary_model', 'google/gemini-2.5-flash-image');
        $timeout              = get_option('fito_tryon_timeout_seconds', 60);

        $button_text   = get_option('fito_tryon_button_text', __('Try It On with AI', 'fito-try-on-with-ai-for-woocommerce'));
        $button_pos    = get_option('fito_tryon_button_position', 'after_add_to_cart');

        $rl_enabled    = get_option('fito_tryon_rate_limit_enabled', 'yes');
        $rl_count      = (int) get_option('fito_tryon_rate_limit_count', get_option('fito_tryon_rate_limit_hourly', 10000));
        $rl_period     = get_option('fito_tryon_rate_limit_period', 'hour');
        $log_retention = get_option('fito_tryon_log_retention', '30');
        $consent_text  = Fito_Tryon_Privacy::get_consent_text();

        $access_level  = get_option('fito_tryon_access_level', 'everyone');
        $my_acct_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        $login_url     = get_option('fito_tryon_login_url', '');
        if (empty($login_url)) {
            $login_url = $my_acct_url;
        }
        $login_notice  = get_option('fito_tryon_login_notice', '');
        if (empty($login_notice)) {
            $login_notice = __('Please sign up or log in to your account to use our AI Virtual Try-On feature and preview how this item looks on you.', 'fito-try-on-with-ai-for-woocommerce');
        }
        $sample_img_id = absint(get_option('fito_tryon_sample_image_id', 0));
        $sample_img_url= $sample_img_id ? wp_get_attachment_image_url($sample_img_id, 'medium') : '';

        $categories       = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $allowed_cats     = (array) get_option('fito_tryon_allowed_categories', []);
        ?>
        <div class="wrap fito-admin-wrap">
            <header class="fito-admin-header">
                <div class="fito-brand">
                    <h1>
                        <?php esc_html_e('Virtual Try-On Settings', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </h1>
                </div>
            </header>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification flag.
            if (isset($_GET['settings_updated'])) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
            <?php endif; ?>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification flag and count.
            $photos_purged = isset($_GET['photos_purged']) ? absint(wp_unslash($_GET['photos_purged'])) : null;
            if ($photos_purged !== null) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        // translators: %d: Number of deleted temporary photo files
                        printf(esc_html__('Stored photos purged successfully (%d files deleted).', 'fito-try-on-with-ai-for-woocommerce'), absint($photos_purged));
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fito_tryon_save_settings">
                <?php wp_nonce_field('fito_tryon_save_settings_nonce'); ?>

                <!-- 1. AI Provider Connection & Models -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h2><?php esc_html_e('1. AI Provider Connection & Models', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                                <p class="fito-muted"><?php esc_html_e('Choose your preferred AI service provider for virtual try-on image generation.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                            </div>
                            <div>
                                <button type="button" class="button button-secondary" id="fito-btn-sync-models">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                    <span id="fito-sync-models-text"><?php esc_html_e('Refresh Models from Provider', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </button>
                                <span id="fito-sync-models-status" style="margin-left: 8px; font-size: 12px; color: #10b981; display: none;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fito_ai_provider"><?php esc_html_e('AI Provider', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_ai_provider" name="fito_tryon_ai_provider" class="regular-text" style="max-width: 320px;">
                                        <option value="openrouter" <?php selected($ai_provider, 'openrouter'); ?>><?php esc_html_e('OpenRouter (Multi-Model Aggregator)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <?php if (in_array('gemini', $allowed_providers, true)) : ?>
                                            <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>><?php esc_html_e('Google Gemini (Direct API)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <?php endif; ?>
                                        <?php if (in_array('openai', $allowed_providers, true)) : ?>
                                            <option value="openai" <?php selected($ai_provider, 'openai'); ?>><?php esc_html_e('OpenAI (Direct API)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Select which service powers virtual try-on image generation.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <!-- Provider Panel: OpenRouter -->
                        <div class="fito-provider-panel" id="fito-panel-openrouter" style="<?php echo $ai_provider === 'openrouter' ? '' : 'display: none;'; ?>">
                            <table class="form-table fito-provider-table" id="fito-models-wrapper-openrouter" data-provider="openrouter" data-saved-primary="<?php echo esc_attr($primary_model); ?>">
                                <tr>
                                    <th scope="row"><label for="fito_openrouter_api_key"><?php esc_html_e('OpenRouter API Key', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                    <td>
                                        <div class="fito-key-input-row">
                                            <input type="password" id="fito_openrouter_api_key" name="fito_tryon_openrouter_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                                            <button type="button" class="button fito-btn-test-connection" data-provider="openrouter"><?php esc_html_e('Test Connection', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                        </div>
                                        <p class="description"><?php esc_html_e('Get your API key at openrouter.ai/keys.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        <div class="fito-connection-test-result fito-test-result" style="display: none;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="fito_primary_model"><?php esc_html_e('Primary Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                    <td>
                                        <div class="fito-skeleton-box fito-primary-skeleton">
                                            <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                        </div>
                                        <div class="fito-primary-select-wrap" style="display: none;">
                                            <select id="fito_primary_model" name="fito_tryon_primary_model" class="regular-text" style="max-width: 520px;">
                                                <option value="<?php echo esc_attr($primary_model); ?>"><?php echo esc_html($primary_model); ?></option>
                                            </select>
                                            <p class="description"><?php esc_html_e('Only image generation models suitable for virtual try-on are displayed (cached for 24 hours).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <?php do_action('fito_tryon_admin_provider_extra_fields', 'openrouter'); ?>
                            </table>
                        </div>

                        <?php
                        /**
                         * Action to render additional AI provider configuration panels.
                         *
                         * @param string $ai_provider Currently active AI provider.
                         * @param array<int, string> $allowed_providers Permitted AI providers.
                         */
                        do_action('fito_tryon_admin_provider_panels', $ai_provider, $allowed_providers);
                        ?>

                        <!-- Common Request Timeout -->
                        <table class="form-table" style="margin-top: 10px;">
                            <tr>
                                <th scope="row"><label for="fito_timeout"><?php esc_html_e('Request Timeout (seconds)', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="number" id="fito_timeout" name="fito_tryon_timeout_seconds" value="<?php echo esc_attr($timeout); ?>" min="15" max="180" class="small-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 2. Frontend Appearance -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('2. Frontend Widget & Button Appearance', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fito_btn_text"><?php esc_html_e('Button Text', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="text" id="fito_btn_text" name="fito_tryon_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Custom storefront button label. Leave blank to use default "Try It On with AI".', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="fito_btn_pos"><?php esc_html_e('Button Placement', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_btn_pos" name="fito_tryon_button_position" class="regular-text">
                                        <option value="after_add_to_cart" <?php selected($button_pos, 'after_add_to_cart'); ?>><?php esc_html_e('Below Add to Cart button (Recommended)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="before_add_to_cart" <?php selected($button_pos, 'before_add_to_cart'); ?>><?php esc_html_e('Above Add to Cart button', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="product_tab" <?php selected($button_pos, 'product_tab'); ?>><?php esc_html_e('In a custom Product Tab', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="shortcode_only" <?php selected($button_pos, 'shortcode_only'); ?>><?php esc_html_e('Shortcode only [fito_tryon]', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose where the AI Virtual Try-On button appears on single product pages.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>

                            <?php do_action('fito_tryon_admin_appearance_settings'); ?>
                        </table>
                    </div>
                </div>

                <!-- 3. Rate Limiting & Privacy -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('3. Abuse Prevention & Data Privacy', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fito_access_level"><?php esc_html_e('Access Control', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_access_level" name="fito_tryon_access_level" class="regular-text">
                                        <option value="everyone" <?php selected($access_level, 'everyone'); ?>><?php esc_html_e('Allow everyone (Guests and registered users)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="logged_in_only" <?php selected($access_level, 'logged_in_only'); ?>><?php esc_html_e('Only logged-in users', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose whether guests can try on items immediately or must sign up / log in to access the virtual fitting room.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_login_url" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="fito_login_url"><?php esc_html_e('Login / Signup Page URL', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; max-width: 580px;">
                                        <input type="url" id="fito_login_url" name="fito_tryon_login_url" value="<?php echo esc_attr($login_url); ?>" placeholder="<?php echo esc_attr($my_acct_url); ?>" class="regular-text" style="flex: 1;">
                                        <button type="button" class="button" id="fito-btn-use-myaccount" data-url="<?php echo esc_attr($my_acct_url); ?>"><?php esc_html_e('Use My Account URL', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                    </div>
                                    <p class="description"><?php esc_html_e('Destination URL where guests are directed to sign up or log in. Visitors will be seamlessly returned back to their product page after logging in.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_login_notice" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="fito_login_notice"><?php esc_html_e('Signup / Login Prompt Message', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <textarea id="fito_login_notice" name="fito_tryon_login_notice" rows="3" class="large-text"><?php echo esc_textarea($login_notice); ?></textarea>
                                    <p class="description"><?php esc_html_e('Message displayed inside the modal when an unregistered visitor clicks the try-on button.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_sample_image" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label><?php esc_html_e('Guest Sample Try-On Image', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="hidden" id="fito_sample_image_id" name="fito_tryon_sample_image_id" value="<?php echo esc_attr($sample_img_id ?: ''); ?>">
                                    <div id="fito-sample-image-preview" style="<?php echo $sample_img_url ? '' : 'display:none;'; ?> margin-bottom: 10px;">
                                        <img src="<?php echo esc_url($sample_img_url); ?>" alt="<?php esc_attr_e('Sample try-on preview', 'fito-try-on-with-ai-for-woocommerce'); ?>" style="max-height: 120px; max-width: 240px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: block; object-fit: cover;">
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <button type="button" class="button" id="fito-btn-upload-sample-image">
                                            <span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
                                            <?php esc_html_e('Upload Sample Image', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete" id="fito-btn-remove-sample-image" style="<?php echo $sample_img_id ? '' : 'display:none;'; ?>">
                                            <?php esc_html_e('Remove', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Upload a sample virtual try-on or Before/After transformation image to display to guest visitors inside the modal. If empty, the built-in high resolution sample showcase is displayed.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Rate Limiting', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fito_tryon_rate_limit_enabled" value="yes" <?php checked($rl_enabled, 'yes'); ?>>
                                        <span id="fito-rl-label-user" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                            <?php esc_html_e('Enforce try-on limits per user account', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </span>
                                        <span id="fito-rl-label-guest" style="<?php echo $access_level === 'logged_in_only' ? 'display:none;' : ''; ?>">
                                            <?php esc_html_e('Enforce try-on limits per device cookie & IP', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </span>
                                    </label>
                                    <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
                                        <label for="fito_rl_count"><?php esc_html_e('Max try-ons:', 'fito-try-on-with-ai-for-woocommerce'); ?></label>
                                        <input type="number" id="fito_rl_count" name="fito_tryon_rate_limit_count" value="<?php echo esc_attr($rl_count); ?>" min="1" max="100000" class="regular-text" style="max-width: 120px;">
                                        <select id="fito_rl_period" name="fito_tryon_rate_limit_period" style="min-width: 130px;">
                                            <option value="minute" <?php selected($rl_period, 'minute'); ?>><?php esc_html_e('per minute', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="hour" <?php selected($rl_period, 'hour'); ?>><?php esc_html_e('per hour', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="day" <?php selected($rl_period, 'day'); ?>><?php esc_html_e('per day', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        </select>
                                    </div>
                                    <p class="description" id="fito-rl-desc-user" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Throttle repeated virtual try-ons per registered customer account to protect your API balance.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </p>
                                    <p class="description" id="fito-rl-desc-guest" style="<?php echo $access_level === 'logged_in_only' ? 'display:none;' : ''; ?>">
                                        <?php esc_html_e('Tracks each guest device via a persistent browser cookie so family members sharing the same home WiFi/IP keep independent try-on allowances, backed by an IP safety ceiling against abuse.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fito_log_retention"><?php esc_html_e('Log Retention in Database', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_log_retention" name="fito_tryon_log_retention">
                                        <option value="none" <?php selected($log_retention, 'none'); ?>><?php esc_html_e('Do not log', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="1" <?php selected($log_retention, '1'); ?>><?php esc_html_e('1 day', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="3" <?php selected($log_retention, '3'); ?>><?php esc_html_e('3 days', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="7" <?php selected($log_retention, '7'); ?>><?php esc_html_e('1 week', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="30" <?php selected($log_retention, '30'); ?>><?php esc_html_e('30 days', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        <option value="never" <?php selected($log_retention, 'never'); ?>><?php esc_html_e('Never clear logs', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose how long try-on audit log records are preserved in the database before being automatically purged, or disable logging entirely.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fito_consent_text"><?php esc_html_e('Consent Notice Text', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <textarea id="fito_consent_text" name="fito_tryon_consent_text" rows="3" class="large-text"><?php echo esc_textarea($consent_text); ?></textarea>
                                </td>
                            </tr>
                            <?php do_action('fito_tryon_admin_privacy_settings'); ?>
                        </table>
                    </div>
                </div>

                <!-- 4. Product Categories -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('4. Enabled Product Categories', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                        <p class="fito-muted"><?php esc_html_e('Leave all unchecked to enable virtual try-on across your entire store.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                    </div>
                    <div class="fito-card-body">
                        <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                            <div class="fito-category-checklist">
                                <?php foreach ($categories as $cat) : ?>
                                    <label class="fito-cat-item">
                                        <input type="checkbox" name="fito_tryon_allowed_categories[]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked(in_array((string) $cat->term_id, $allowed_cats, true)); ?>>
                                        <span><?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="fito-muted"><?php esc_html_e('No product categories found.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php do_action('fito_tryon_settings_form_bottom'); ?>

                <div class="fito-settings-submit-bar">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Changes', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </button>
                </div>
            </form>

            <!-- Manual Purge Form -->
            <div class="fito-card fito-purge-card" style="margin-top: 32px;">
                <div class="fito-card-header">
                    <h3><?php esc_html_e('Manual Photo Purge', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                </div>
                <div class="fito-card-body">
                    <p><?php esc_html_e('Immediately delete all temporary customer photos and generated preview files from the server.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to purge all stored photos?', 'fito-try-on-with-ai-for-woocommerce')); ?>');">
                        <input type="hidden" name="action" value="fito_tryon_purge_photos">
                        <?php wp_nonce_field('fito_tryon_purge_nonce'); ?>
                        <button type="submit" class="button button-secondary">
                            <?php esc_html_e('Purge All Stored Photos', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save_settings(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_save_settings_nonce');

        $allowed_providers = $this->plugin->get_allowed_providers();
        $ai_provider       = (string) get_option('fito_tryon_ai_provider', 'openrouter');
        if (isset($_POST['fito_tryon_ai_provider'])) {
            $provider    = sanitize_key($_POST['fito_tryon_ai_provider']);
            $ai_provider = in_array($provider, $allowed_providers, true) ? $provider : 'openrouter';
            update_option('fito_tryon_ai_provider', $ai_provider, false);
        }

        if (isset($_POST['fito_tryon_openrouter_api_key'])) {
            update_option('fito_tryon_openrouter_api_key', sanitize_text_field(wp_unslash($_POST['fito_tryon_openrouter_api_key'])), false);
        }
        if (isset($_POST['fito_tryon_primary_model'])) {
            update_option('fito_tryon_primary_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_primary_model'])), false);
        }

        /**
         * Action to handle saving settings for additional AI providers.
         *
         * @param string $ai_provider Currently selected AI provider.
         * @param array<string, mixed> $post_data Submitted POST data.
         */
        do_action('fito_tryon_admin_save_provider_settings', $ai_provider, $_POST);

        if (isset($_POST['fito_tryon_timeout_seconds'])) {
            update_option('fito_tryon_timeout_seconds', max(15, absint($_POST['fito_tryon_timeout_seconds'])), false);
        }

        if (isset($_POST['fito_tryon_button_position'])) {
            update_option('fito_tryon_button_position', sanitize_text_field(wp_unslash($_POST['fito_tryon_button_position'])));
        }

        if (isset($_POST['fito_tryon_button_text'])) {
            update_option('fito_tryon_button_text', sanitize_text_field(wp_unslash($_POST['fito_tryon_button_text'])));
        }

        update_option('fito_tryon_rate_limit_enabled', !empty($_POST['fito_tryon_rate_limit_enabled']) ? 'yes' : 'no');
        if (isset($_POST['fito_tryon_rate_limit_count'])) {
            $count = max(1, absint($_POST['fito_tryon_rate_limit_count']));
            update_option('fito_tryon_rate_limit_count', $count, false);
            update_option('fito_tryon_rate_limit_hourly', $count, false);
        } elseif (isset($_POST['fito_tryon_rate_limit_hourly'])) {
            $count = max(1, absint($_POST['fito_tryon_rate_limit_hourly']));
            update_option('fito_tryon_rate_limit_count', $count, false);
            update_option('fito_tryon_rate_limit_hourly', $count, false);
        }
        if (isset($_POST['fito_tryon_rate_limit_period'])) {
            $period = sanitize_key($_POST['fito_tryon_rate_limit_period']);
            update_option('fito_tryon_rate_limit_period', in_array($period, ['minute', 'hour', 'day'], true) ? $period : 'hour', false);
        }
        if (isset($_POST['fito_tryon_log_retention'])) {
            $allowed_retention = ['none', '1', '3', '7', '30', 'never'];
            $retention_val = sanitize_key($_POST['fito_tryon_log_retention']);
            update_option('fito_tryon_log_retention', in_array($retention_val, $allowed_retention, true) ? $retention_val : '30', false);
        }
        if (isset($_POST['fito_tryon_consent_text'])) {
            update_option('fito_tryon_consent_text', sanitize_textarea_field(wp_unslash($_POST['fito_tryon_consent_text'])), false);
        }

        if (isset($_POST['fito_tryon_access_level'])) {
            $level = sanitize_key(wp_unslash($_POST['fito_tryon_access_level']));
            update_option('fito_tryon_access_level', in_array($level, ['everyone', 'logged_in_only'], true) ? $level : 'everyone');
        }
        if (isset($_POST['fito_tryon_login_url'])) {
            $url = esc_url_raw(trim(sanitize_text_field(wp_unslash($_POST['fito_tryon_login_url']))));
            update_option('fito_tryon_login_url', $url);
        }
        if (isset($_POST['fito_tryon_login_notice'])) {
            update_option('fito_tryon_login_notice', sanitize_textarea_field(wp_unslash($_POST['fito_tryon_login_notice'])));
        }
        if (isset($_POST['fito_tryon_sample_image_id'])) {
            update_option('fito_tryon_sample_image_id', absint(wp_unslash($_POST['fito_tryon_sample_image_id'])));
        }

        $cats = isset($_POST['fito_tryon_allowed_categories']) && is_array($_POST['fito_tryon_allowed_categories'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['fito_tryon_allowed_categories']))
            : [];
        update_option('fito_tryon_allowed_categories', $cats, false);

        do_action('fito_tryon_save_settings_post');

        do_action('fito_tryon_flush_cache');

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-settings', 'settings_updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function handle_purge_photos(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_purge_nonce');

        $count = $this->plugin->privacy->purge_all_temporary_photos();
        do_action('fito_tryon_flush_cache');

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-settings', 'photos_purged' => $count], admin_url('admin.php')));
        exit;
    }

    public function render_prompts_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        $system_prompt = get_option('fito_tryon_system_prompt');
        if (empty($system_prompt)) {
            $system_prompt = Fito_Tryon_OpenRouter::get_default_system_prompt();
        }

        $prompts = get_option('fito_tryon_prompts_config');
        if (!is_array($prompts) || empty($prompts)) {
            $prompts = Fito_Tryon_OpenRouter::get_default_prompts();
        }

        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        if (is_wp_error($categories)) {
            $categories = [];
        }

        $default_system_prompt = Fito_Tryon_OpenRouter::get_default_system_prompt();
        $builtin_defaults = Fito_Tryon_OpenRouter::get_default_prompts();
        $default_map = [];
        foreach ($builtin_defaults as $def_item) {
            $default_map[$def_item['id']] = $def_item['prompt'] ?? '';
        }
        $default_ids = array_column($builtin_defaults, 'id');
        $system_prompts = array_values(array_filter($prompts, static function ($p) use ($default_ids) {
            $p_id = $p['id'] ?? '';
            return !empty($p['is_system']) || in_array($p_id, $default_ids, true);
        }));
        $prompts = (array) apply_filters('fito_tryon_admin_displayed_prompts', $system_prompts, $prompts);

        // Map category ID to the prompt index that owns it (first prompt wins to ensure mutual exclusivity)
        $cat_to_prompt_owner = [];
        foreach ($prompts as $p_idx => $p_data) {
            $assigned_cats = array_map('intval', (array) ($p_data['categories'] ?? []));
            foreach ($assigned_cats as $cid) {
                if ($cid > 0 && !isset($cat_to_prompt_owner[$cid])) {
                    $cat_to_prompt_owner[$cid] = $p_idx;
                }
            }
        }
        ?>
        <div class="wrap fito-admin-wrap">
            <header class="fito-admin-header">
                <div class="fito-brand">
                    <h1><?php esc_html_e('AI Try-On Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></h1>
                    <p class="fito-subtitle"><?php esc_html_e('Configure AI system guardrails and customize fitting prompts mapped to WooCommerce categories.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
            </header>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification flag.
            if (isset($_GET['prompts_updated'])) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Prompts and category mappings saved successfully.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="fitoPromptsForm">
                <input type="hidden" name="action" value="fito_tryon_save_prompts">
                <?php wp_nonce_field('fito_tryon_save_prompts_nonce'); ?>

                <!-- 1. System Prompt Card -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2><?php esc_html_e('1. Global AI System Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                            <p class="fito-muted"><?php esc_html_e('Sets the role, photographic realism standards, identity preservation, and lighting consistency for vision models.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                        </div>
                        <button type="button" class="button button-secondary" id="fito-btn-reset-system-prompt" data-default="<?php echo esc_attr($default_system_prompt); ?>">
                            <?php esc_html_e('Reset to Default', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        </button>
                    </div>
                    <div class="fito-card-body">
                        <textarea name="fito_tryon_system_prompt" id="fito_tryon_system_prompt" rows="4" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('This instruction is prepended as the system message to guide photorealism, body proportions, and skin/fabric fidelity.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        </p>
                    </div>
                </div>

                <!-- 2. Category Fitting Prompts Repeater -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2><?php esc_html_e('2. Category Try-On Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                            <p class="fito-muted"><?php esc_html_e('Map WooCommerce product categories to specialized fitting prompts. Works across any language because mapping uses category IDs. If a product has no mapped prompt, the default Clothing & Apparel prompt is used.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                        </div>
                        <?php do_action('fito_tryon_admin_prompts_header_actions'); ?>
                    </div>
                    <div class="fito-card-body">
                        <div class="fito-prompts-repeater" id="fitoPromptsRepeater">
                            <?php foreach ($prompts as $index => $prompt_item) :
                                $p_id = $prompt_item['id'] ?? ('prompt_' . $index);
                                $p_title = $prompt_item['title'] ?? __('Prompt', 'fito-try-on-with-ai-for-woocommerce');
                                $p_is_system = !empty($prompt_item['is_system']);
                                $raw_categories = array_map('intval', (array) ($prompt_item['categories'] ?? []));
                                $p_categories = [];
                                foreach ($raw_categories as $cid) {
                                    if (isset($cat_to_prompt_owner[$cid]) && $cat_to_prompt_owner[$cid] === $index) {
                                        $p_categories[] = $cid;
                                    }
                                }
                                $p_text = $prompt_item['prompt'] ?? '';
                                $cat_count = count($p_categories);
                                $default_text = $p_is_system ? ($default_map[$p_id] ?? '') : '';
                            ?>
                                <div class="fito-prompt-row is-collapsed <?php echo $p_is_system ? 'is-system-preset' : 'is-custom-prompt'; ?>" data-index="<?php echo esc_attr($index); ?>">
                                    <div class="fito-prompt-row-header">
                                        <div class="fito-prompt-header-left">
                                            <span class="dashicons dashicons-menu fito-prompt-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'fito-try-on-with-ai-for-woocommerce'); ?>"></span>
                                            <input type="text" name="prompts[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($p_title); ?>" class="regular-text fito-prompt-title-input" placeholder="<?php esc_attr_e('Prompt Title', 'fito-try-on-with-ai-for-woocommerce'); ?>" required>
                                            <span class="fito-prompt-badge <?php echo $p_is_system ? 'fito-badge-system' : 'fito-badge-custom'; ?>">
                                                <?php echo $p_is_system ? esc_html__('Built-in Preset', 'fito-try-on-with-ai-for-woocommerce') : esc_html__('Custom Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                            </span>
                                        </div>
                                        <div class="fito-prompt-header-right">
                                            <span class="fito-prompt-cat-count" data-count="<?php echo esc_attr($cat_count); ?>">
                                                <?php
                                                if ($cat_count > 0) {
                                                    // translators: %d: Number of connected categories
                                                    printf(esc_html__('%d categories connected', 'fito-try-on-with-ai-for-woocommerce'), absint($cat_count));
                                                } elseif ($p_id === 'clothing') {
                                                    esc_html_e('Default Fallback (All other categories)', 'fito-try-on-with-ai-for-woocommerce');
                                                } else {
                                                    esc_html_e('No categories connected', 'fito-try-on-with-ai-for-woocommerce');
                                                }
                                                ?>
                                            </span>
                                            <?php do_action('fito_tryon_admin_prompt_row_actions', $prompt_item, $index); ?>
                                            <button type="button" class="button button-small fito-prompt-toggle" title="<?php esc_attr_e('Toggle Prompt Details', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            </button>
                                            <?php if (!$p_is_system) : ?>
                                                <button type="button" class="button button-small fito-prompt-delete" title="<?php esc_attr_e('Delete Custom Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="fito-prompt-row-body">
                                        <input type="hidden" name="prompts[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($p_id); ?>">
                                        <input type="hidden" name="prompts[<?php echo esc_attr($index); ?>][is_system]" value="<?php echo $p_is_system ? '1' : '0'; ?>">

                                        <div class="fito-prompt-fields-grid">
                                            <div class="fito-prompt-field-cats">
                                                <label class="fito-field-label">
                                                    <strong><?php esc_html_e('Connected WooCommerce Categories:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                </label>
                                                <div class="fito-cat-filter-wrap">
                                                    <input type="text" class="fito-prompt-cat-search" placeholder="<?php esc_attr_e('Filter categories...', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                </div>
                                                <div class="fito-prompt-cat-checklist">
                                                    <?php if (!empty($categories)) : ?>
                                                        <?php foreach ($categories as $cat) :
                                                            $cid = (int) $cat->term_id;
                                                            $owner = $cat_to_prompt_owner[$cid] ?? null;
                                                            $is_claimed_elsewhere = ($owner !== null && $owner !== $index);
                                                            $is_checked = ($owner === $index);
                                                            $item_class = 'fito-prompt-cat-item' . ($is_claimed_elsewhere ? ' is-claimed-elsewhere' : '');
                                                        ?>
                                                            <label class="<?php echo esc_attr($item_class); ?>">
                                                                <input type="checkbox" name="prompts[<?php echo esc_attr($index); ?>][categories][]" value="<?php echo esc_attr($cid); ?>" <?php checked($is_checked); ?>>
                                                                <span class="fito-prompt-cat-name"><?php echo esc_html($cat->name); ?></span>
                                                                <span class="fito-prompt-cat-id">ID: <?php echo esc_html($cid); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                        <p class="fito-cat-empty-notice" style="display: none;"><?php esc_html_e('No available categories to select.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                                    <?php else : ?>
                                                        <p class="fito-muted"><?php esc_html_e('No WooCommerce product categories found.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="description"><?php esc_html_e('Check which store categories trigger this prompt. Categories can be named in any language.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-prompt-field-text">
                                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
                                                    <label class="fito-field-label" style="margin-bottom: 0;">
                                                        <strong><?php esc_html_e('Fitting Prompt Instruction:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    </label>
                                                    <div class="fito-placeholder-helpers" style="margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
                                                        <span class="fito-helper-label"><?php esc_html_e('Available tag:', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                                        <button type="button" class="button button-small fito-insert-placeholder" data-tag="{product_title}">+ {product_title}</button>
                                                        <?php if ($p_is_system && !empty($default_text)) : ?>
                                                            <button type="button" class="button button-small fito-revert-default-prompt" data-default="<?php echo esc_attr($default_text); ?>" title="<?php esc_attr_e('Revert instruction back to built-in default', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                                <span class="dashicons dashicons-undo"></span>
                                                                <?php esc_html_e('Revert to Default', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <textarea name="prompts[<?php echo esc_attr($index); ?>][prompt]" rows="5" class="large-text fito-prompt-textarea" required><?php echo esc_textarea($p_text); ?></textarea>
                                                <?php if ($p_is_system && !empty($default_text)) : ?>
                                                    <details class="fito-default-prompt-box" style="margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px;">
                                                        <summary style="cursor: pointer; font-size: 12px; font-weight: 600; color: #475569; user-select: none;">
                                                            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; margin-right: 4px;"></span>
                                                            <?php esc_html_e('Original Built-in Default Prompt (Click to view):', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                                        </summary>
                                                        <div style="margin-top: 6px; font-size: 12px; color: #64748b; font-style: italic; line-height: 1.5; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px;">
                                                            <?php echo esc_html($default_text); ?>
                                                        </div>
                                                    </details>
                                                <?php endif; ?>
                                                <p class="description">
                                                    <?php esc_html_e('Image 1 is the customer photo, Image 2 is the product image. The tag {product_title} is dynamically replaced with the product name. Variation attributes are automatically appended.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php do_action('fito_tryon_admin_prompts_repeater_bottom'); ?>
                    </div>
                </div>

                <div class="fito-settings-submit-bar">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </button>
                </div>
            </form>

            <?php do_action('fito_tryon_admin_prompts_page_bottom'); ?>
        </div>
        <?php
    }

    public function handle_save_prompts(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_save_prompts_nonce');

        if (isset($_POST['fito_tryon_system_prompt'])) {
            $system_prompt = sanitize_textarea_field(wp_unslash($_POST['fito_tryon_system_prompt']));
            update_option('fito_tryon_system_prompt', $system_prompt, false);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array of prompt structures sanitized individually in the loop below.
        $raw_prompts = isset($_POST['prompts']) && is_array($_POST['prompts']) ? (array) wp_unslash($_POST['prompts']) : [];
        $default_prompts = Fito_Tryon_OpenRouter::get_default_prompts();
        $default_ids = array_column($default_prompts, 'id');

        $sanitized_prompts = [];
        $processed_ids = [];
        $claimed_category_ids = [];

        foreach ($raw_prompts as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = !empty($item['id']) ? sanitize_key($item['id']) : ('custom_' . wp_generate_password(6, false, false));
            $is_system = !empty($item['is_system']) || in_array($id, $default_ids, true);

            $title = !empty($item['title']) ? sanitize_text_field($item['title']) : __('Custom Prompt', 'fito-try-on-with-ai-for-woocommerce');
            $prompt = !empty($item['prompt']) ? sanitize_textarea_field($item['prompt']) : '';

            $cats = [];
            if (!empty($item['categories']) && is_array($item['categories'])) {
                $raw_cats = array_map('absint', $item['categories']);
                $raw_cats = array_filter($raw_cats);
                foreach ($raw_cats as $cid) {
                    if (!in_array($cid, $claimed_category_ids, true)) {
                        $cats[] = $cid;
                        $claimed_category_ids[] = $cid;
                    }
                }
            }

            $sanitized_prompts[] = [
                'id'         => $id,
                'title'      => $title,
                'is_system'  => $is_system,
                'categories' => array_values(array_unique($cats)),
                'prompt'     => $prompt,
            ];

            $processed_ids[] = $id;
        }

        // Ensure all built-in system presets are retained even if somehow missing
        foreach ($default_prompts as $default_item) {
            if (!in_array($default_item['id'], $processed_ids, true)) {
                $sanitized_prompts[] = $default_item;
            }
        }

        update_option('fito_tryon_prompts_config', $sanitized_prompts, false);

        // Invalidate try-on cache so updated category mappings take effect immediately
        do_action('fito_tryon_flush_cache');

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-prompts', 'prompts_updated' => '1'], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the Go Pro showcase and comparison page.
     */
    public function render_go_pro_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        $buy_url = 'https://violo.ir/?p=721';
        ?>
        <div class="wrap fito-admin-wrap fito-go-pro-wrap">
            <!-- Hero Header Banner -->
            <div class="fito-go-pro-hero">
                <div class="fito-go-pro-hero-content">
                    <span class="fito-go-pro-badge">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('FITO TRY-ON PRO ADDON', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </span>
                    <h1><?php esc_html_e('Supercharge Your Virtual Fitting Room', 'fito-try-on-with-ai-for-woocommerce'); ?></h1>
                    <p class="fito-go-pro-lead">
                        <?php esc_html_e('Maximize WooCommerce conversions, eliminate AI downtime with automatic fallback routing, and customize every visual aspect of your fitting experience.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </p>
                    <div class="fito-go-pro-hero-actions">
                        <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero fito-btn-pro-cta">
                            <span><?php esc_html_e('Get Fito Pro Addon Now', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                        <span class="fito-hero-trust-tag"><?php esc_html_e('Instant Download & Lifetime Updates', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Key Features Grid: Only Actual Real Pro Addon Features -->
            <div class="fito-go-pro-section-title">
                <h2><?php esc_html_e('Why Upgrade to Fito Pro?', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                <p><?php esc_html_e('Unlock advanced AI providers, automated failover, branding protection, and expanded shop placements.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
            </div>

            <div class="fito-go-pro-grid">
                <!-- 1. Direct AI Providers -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-purple">
                        <span class="dashicons dashicons-cloud"></span>
                    </div>
                    <h3><?php esc_html_e('Direct Google Gemini & OpenAI APIs', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Connect directly to Google AI Studio (Gemini 2.5 & 3.1 Flash) and OpenAI APIs with independent keys and primary model selectors, bypassing aggregator markup.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 2. Direct OpenAI API -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-blue">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                    <h3><?php esc_html_e('Direct OpenAI API Integration', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Connect directly to OpenAI API for GPT Image and DALL-E models with custom key authorization and dedicated billing.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 3. Watermark & Branding -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-indigo">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <h3><?php esc_html_e('Brand Watermark & Logo Overlay', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Stamp store logos or domain watermarks onto try-on images with 7-point positioning, opacity controls (10% to 100%), alpha blending, and instant preview rendering.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 4. Shop Placements & Variation Swatches -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-green">
                        <span class="dashicons dashicons-products"></span>
                    </div>
                    <h3><?php esc_html_e('Archive Loops & Product Gallery Overlays', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Display Quick Try-On buttons across catalog archive loops and product gallery thumbnails for effortless 1-click customer access.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 5. Custom CSS Selectors -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-amber">
                        <span class="dashicons dashicons-admin-customizer"></span>
                    </div>
                    <h3><?php esc_html_e('Custom Selectors & Placement Studio', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Attach virtual try-on floating badges to Elementor widgets, lookbooks, custom carousels, and image sliders by simply declaring custom CSS target selectors.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 6. Variation Swatches Sync -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-purple">
                        <span class="dashicons dashicons-forms"></span>
                    </div>
                    <h3><?php esc_html_e('Product Variation Swatches & Image Sync', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Dynamically binds virtual try-on to active product variation photos and swatches as customers select different colors and styles.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 7. Result Caching & Extended Retention -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-blue">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <h3><?php esc_html_e('Smart Result Caching & Retention', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Cache identical photo and product combinations to eliminate duplicate AI generation fees, and configure photo retention auto-cleanup from 1 to 720 hours.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <!-- 8. Customer Account History & Visual Logs -->
                <div class="fito-go-pro-card">
                    <div class="fito-card-icon fito-icon-red">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <h3><?php esc_html_e('Customer Account History & Visual Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Add a dedicated "My Try-Ons" tab in WooCommerce My Account, enable modal save actions, and store Before/After photos in admin audit logs with 240px hover zoom previews.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
            </div>

            <!-- Comparison Matrix -->
            <div class="fito-go-pro-section-title" style="margin-top: 48px;">
                <h2><?php esc_html_e('Compare Plans', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                <p><?php esc_html_e('Everything you need to deliver a world-class virtual fitting room experience.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
            </div>

            <div class="fito-comparison-card">
                <table class="fito-comparison-table">
                    <thead>
                        <tr>
                            <th class="fito-th-feature"><?php esc_html_e('Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th class="fito-th-free"><?php esc_html_e('Free Version', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            <th class="fito-th-pro">
                                <span class="fito-table-pro-heading">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e('Pro Addon', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Direct Google Gemini & OpenAI Providers', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Connect directly to Google Gemini and OpenAI APIs without intermediary gateway fees', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><?php esc_html_e('OpenRouter only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Multi-Provider Fallback Routing', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Seamlessly retries on backup model when primary hits rate limits or errors across providers', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('All Providers (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Brand Watermark & Transparency Logo Overlay', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Custom logo stamps, site URL watermark, and 7-point position controls', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Custom CSS Element Selectors', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Attach try-on badges to Elementor widgets, lookbooks, and custom sliders', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Catalog Archive Loops, Gallery & Variation Images', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Overlay buttons directly on shop catalog loops, product gallery thumbnails, and variation swatches', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Customer Account Try-On History (Save to Account)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Allows logged-in customers to save their try-on results and view fitting history in WooCommerce My Account', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Audit Log Before/After Photo Storage', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Store customer images in audit logs with 240px hover zoom previews', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><?php esc_html_e('Text logs only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Smart Result Caching & Generation Reuse', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Cache identical photo and product combinations to save AI API usage and minimize latency', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-cross">✕</span></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Custom Photo Retention Window', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Configurable photo expiration and auto-purge window from 1 to 720 hours', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><?php esc_html_e('Fixed 24 hours', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('1 - 720 Hours (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr class="fito-highlight-row">
                            <td>
                                <strong><?php esc_html_e('Custom & Duplicated Fitting Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Create unlimited custom prompt profiles and clone existing prompts with 1-click duplication', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><?php esc_html_e('7 Built-in presets only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Unlocked (Pro)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Interactive Try-On Modal & Camera Capture', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Mobile camera, file dropzone, and responsive aspect ratios', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Split Comparison Slider & Fullscreen', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Interactive Before/After comparison handle and full-screen view', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Product Category Hierarchy Gating', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Restricts fitting room access to enabled categories & subcategories', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Guest Access Control & Sample Preview Showcase', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Restrict try-on to logged-in users with guest sample showcase cards and custom preview imagery', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Sliding-Window Rate Limiting & Proxy IP Resolution', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Configure per-minute, per-hour, or per-day limits with Cloudflare header detection', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included (Minute / Hour / Day)', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included (Minute / Hour / Day)', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Audit Logging & Conversion Analytics', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Sent prompt text, response payload logs, duration, error capture, and volume charts', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                        </tr>
                        <tr>
                            <td>
                                <strong><?php esc_html_e('Software Updates', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <span class="fito-row-sub"><?php esc_html_e('Maintenance and compatibility updates', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </td>
                            <td><?php esc_html_e('WordPress.org', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            <td class="fito-td-pro"><span class="fito-check">✓</span> <strong><?php esc_html_e('Pro Addon Updates', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom CTA Banner -->
            <div class="fito-go-pro-cta-card">
                <div class="fito-cta-content">
                    <h2><?php esc_html_e('Ready to Elevate Your Store Experience?', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    <p><?php esc_html_e('Get instant access to the Fito Try-On Pro Addon and unlock all premium capabilities today.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
                <div class="fito-cta-actions">
                    <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero fito-btn-pro-cta">
                        <span><?php esc_html_e('Get Fito Pro Addon Now', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
