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

        if (!Fito_Tryon::is_pro_active()) {
            add_submenu_page(
                'fito-tryon',
                __('Go Pro', 'fito-try-on-with-ai-for-woocommerce'),
                esc_html__('Go Pro', 'fito-try-on-with-ai-for-woocommerce'),
                $cap,
                'fito-tryon-pro',
                [$this, 'render_go_pro_page']
            );
        }
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
            'isPro'     => Fito_Tryon::is_pro_active(),
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
                            <?php if (Fito_Tryon::is_pro_active()) : ?>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Active', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Discover Pro features', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Go Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                            <?php endif; ?>
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
        $is_pro               = Fito_Tryon::is_pro_active();
        $ai_provider          = $is_pro ? (string) get_option('fito_tryon_ai_provider', 'openrouter') : 'openrouter';
        $api_key              = get_option('fito_tryon_openrouter_api_key', '');
        $primary_model        = get_option('fito_tryon_primary_model', 'google/gemini-2.5-flash-image');
        $fallback_model       = $is_pro ? (string) get_option('fito_tryon_fallback_model', 'black-forest-labs/flux.2-klein-4b') : '';

        $gemini_api_key       = get_option('fito_tryon_gemini_api_key', '');
        $gemini_primary_model = get_option('fito_tryon_gemini_primary_model', 'gemini-2.5-flash-image');
        $gemini_fallback_model= get_option('fito_tryon_gemini_fallback_model', 'imagen-3.0-generate-002');

        $openai_api_key       = get_option('fito_tryon_openai_api_key', '');
        $openai_primary_model = get_option('fito_tryon_openai_primary_model', 'gpt-image-2.5-flare');
        $openai_fallback_model= get_option('fito_tryon_openai_fallback_model', 'dall-e-3');

        $timeout              = get_option('fito_tryon_timeout_seconds', 60);

        $button_text   = get_option('fito_tryon_button_text', __('Try It On with AI', 'fito-try-on-with-ai-for-woocommerce'));
        $button_pos    = get_option('fito_tryon_button_position', 'after_add_to_cart');
        $archive_btn   = get_option('fito_tryon_archive_button', 'no');
        $gallery_btns  = get_option('fito_tryon_gallery_buttons', 'no');
        $variation_btns= get_option('fito_tryon_variation_images', 'no');
        $custom_selectors = get_option('fito_tryon_custom_image_selectors', '');
        $save_to_account = get_option('fito_tryon_save_to_account', 'yes');
        $rl_enabled    = get_option('fito_tryon_rate_limit_enabled', 'yes');
        $rl_count      = (int) get_option('fito_tryon_rate_limit_count', get_option('fito_tryon_rate_limit_hourly', 10000));
        $rl_period     = get_option('fito_tryon_rate_limit_period', 'hour');
        $cache_enabled = get_option('fito_tryon_cache_enabled', 'yes');
        $log_images    = get_option('fito_tryon_log_images', 'no');
        $retention_hrs = get_option('fito_tryon_retention_hours', 24);
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

        $wm_enabled    = get_option('fito_tryon_watermark_enabled', 'no');
        $wm_id         = (int) get_option('fito_tryon_watermark_image_id', 0);
        $wm_url        = $wm_id ? wp_get_attachment_image_url($wm_id, 'thumbnail') : '';
        $wm_text       = get_option('fito_tryon_watermark_text', '');
        $wm_pos        = get_option('fito_tryon_watermark_pos', 'bottom_right');
        $wm_opacity    = (int) get_option('fito_tryon_watermark_opacity', 80);

        $site_host     = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'www.example.com';
        if (!str_starts_with($site_host, 'www.') && !filter_var($site_host, FILTER_VALIDATE_IP) && $site_host !== 'localhost') {
            $suggested_host = 'www.' . $site_host;
        } else {
            $suggested_host = $site_host;
        }

        $categories       = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $allowed_cats     = (array) get_option('fito_tryon_allowed_categories', []);
        ?>
        <div class="wrap fito-admin-wrap">
            <header class="fito-admin-header">
                <div class="fito-brand">
                    <h1>
                        <?php esc_html_e('Virtual Try-On Settings', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        <?php if (Fito_Tryon::is_pro_active()) : ?>
                            <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Active', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Discover Pro features', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Go Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                        <?php endif; ?>
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
                                <p class="fito-muted"><?php esc_html_e('Choose your preferred AI service provider: OpenRouter, direct Google Gemini, or direct OpenAI.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
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
                                    <?php if ($is_pro) : ?>
                                        <select id="fito_ai_provider" name="fito_tryon_ai_provider" class="regular-text" style="max-width: 320px;">
                                            <option value="openrouter" <?php selected($ai_provider, 'openrouter'); ?>><?php esc_html_e('OpenRouter (Multi-Model Aggregator)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>><?php esc_html_e('Google Gemini (Direct API)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="openai" <?php selected($ai_provider, 'openai'); ?>><?php esc_html_e('OpenAI (Direct API)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Select which service powers virtual try-on image generation.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    <?php else : ?>
                                        <select id="fito_ai_provider" name="fito_tryon_ai_provider" class="regular-text" style="max-width: 320px;">
                                            <option value="openrouter" selected><?php esc_html_e('OpenRouter (Multi-Model Aggregator)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Default free provider with access to 100+ AI vision and diffusion models.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>

                                        <div class="fito-pro-feature-card" style="margin-top: 14px; max-width: 580px;">
                                            <div class="fito-pro-feature-card-header">
                                                <span class="dashicons dashicons-cloud"></span>
                                                <strong><?php esc_html_e('Direct Google Gemini & Direct OpenAI APIs', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-direct-apis')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                            </div>
                                            <p><?php esc_html_e('Connect directly to Google AI Studio (Gemini 3.1 Flash Image, Imagen 3) or OpenAI (GPT Image 2.5 Flare, DALL-E 3) without middleman platforms.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <!-- Provider Panel: OpenRouter -->
                        <div class="fito-provider-panel" id="fito-panel-openrouter" style="<?php echo $ai_provider === 'openrouter' ? '' : 'display: none;'; ?>">
                            <table class="form-table fito-provider-table" id="fito-models-wrapper-openrouter" data-provider="openrouter" data-saved-primary="<?php echo esc_attr($primary_model); ?>" data-saved-fallback="<?php echo esc_attr($fallback_model); ?>">
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
                                <tr>
                                    <th scope="row"><label><?php esc_html_e('Fallback Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                    <td>
                                        <?php if ($is_pro) : ?>
                                            <div class="fito-skeleton-box fito-fallback-skeleton">
                                                <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                            </div>
                                            <div class="fito-fallback-select-wrap" style="display: none;">
                                                <select id="fito_fallback_model" name="fito_tryon_fallback_model" class="regular-text" style="max-width: 520px;">
                                                    <option value=""><?php esc_html_e('None (Do not retry)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                                    <?php if (!empty($fallback_model)) : ?>
                                                        <option value="<?php echo esc_attr($fallback_model); ?>"><?php echo esc_html($fallback_model); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <p class="description"><?php esc_html_e('Automatically called if the primary model reaches rate limits or provider downtime.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        <?php else : ?>
                                            <div class="fito-pro-feature-card" style="max-width: 580px;">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-randomize"></span>
                                                    <strong><?php esc_html_e('Automatic Fallback Model Routing', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-fallback-model')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Automatically retries virtual try-on requests with a secondary backup model if the primary model encounters rate limits, capacity saturation, or temporary upstream downtime.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <?php if ($is_pro) : ?>
                            <!-- Provider Panel: Google Gemini -->
                            <div class="fito-provider-panel" id="fito-panel-gemini" style="<?php echo $ai_provider === 'gemini' ? '' : 'display: none;'; ?>">
                                <table class="form-table fito-provider-table" id="fito-models-wrapper-gemini" data-provider="gemini" data-saved-primary="<?php echo esc_attr($gemini_primary_model); ?>" data-saved-fallback="<?php echo esc_attr($gemini_fallback_model); ?>">
                                    <tr>
                                        <th scope="row"><label for="fito_gemini_api_key"><?php esc_html_e('Gemini API Key', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-key-input-row">
                                                <input type="password" id="fito_gemini_api_key" name="fito_tryon_gemini_api_key" value="<?php echo esc_attr($gemini_api_key); ?>" class="regular-text" autocomplete="off">
                                                <button type="button" class="button fito-btn-test-connection" data-provider="gemini"><?php esc_html_e('Test Connection', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                            </div>
                                            <p class="description"><?php esc_html_e('Get your API key at aistudio.google.com/app/apikey.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            <div class="fito-connection-test-result fito-test-result" style="display: none;"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="fito_gemini_primary_model"><?php esc_html_e('Primary Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-skeleton-box fito-primary-skeleton">
                                                <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                            </div>
                                            <div class="fito-primary-select-wrap" style="display: none;">
                                                <select id="fito_gemini_primary_model" name="fito_tryon_gemini_primary_model" class="regular-text" style="max-width: 520px;">
                                                    <option value="<?php echo esc_attr($gemini_primary_model); ?>"><?php echo esc_html($gemini_primary_model); ?></option>
                                                </select>
                                                <p class="description"><?php esc_html_e('Recommended: gemini-3.1-flash-image (Nano Banana 2) or gemini-2.5-flash-image (Nano Banana).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="fito_gemini_fallback_model"><?php esc_html_e('Fallback Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-skeleton-box fito-fallback-skeleton">
                                                <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                            </div>
                                            <div class="fito-fallback-select-wrap" style="display: none;">
                                                <select id="fito_gemini_fallback_model" name="fito_tryon_gemini_fallback_model" class="regular-text" style="max-width: 520px;">
                                                    <option value=""><?php esc_html_e('None (Do not retry)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                                    <?php if (!empty($gemini_fallback_model)) : ?>
                                                        <option value="<?php echo esc_attr($gemini_fallback_model); ?>"><?php echo esc_html($gemini_fallback_model); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <p class="description"><?php esc_html_e('Automatically called if the primary model reaches rate limits or errors.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Provider Panel: OpenAI -->
                            <div class="fito-provider-panel" id="fito-panel-openai" style="<?php echo $ai_provider === 'openai' ? '' : 'display: none;'; ?>">
                                <table class="form-table fito-provider-table" id="fito-models-wrapper-openai" data-provider="openai" data-saved-primary="<?php echo esc_attr($openai_primary_model); ?>" data-saved-fallback="<?php echo esc_attr($openai_fallback_model); ?>">
                                    <tr>
                                        <th scope="row"><label for="fito_openai_api_key"><?php esc_html_e('OpenAI API Key', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-key-input-row">
                                                <input type="password" id="fito_openai_api_key" name="fito_tryon_openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text" autocomplete="off">
                                                <button type="button" class="button fito-btn-test-connection" data-provider="openai"><?php esc_html_e('Test Connection', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                            </div>
                                            <p class="description"><?php esc_html_e('Get your API key at platform.openai.com/api-keys.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            <div class="fito-connection-test-result fito-test-result" style="display: none;"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="fito_openai_primary_model"><?php esc_html_e('Primary Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-skeleton-box fito-primary-skeleton">
                                                <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                            </div>
                                            <div class="fito-primary-select-wrap" style="display: none;">
                                                <select id="fito_openai_primary_model" name="fito_tryon_openai_primary_model" class="regular-text" style="max-width: 520px;">
                                                    <option value="<?php echo esc_attr($openai_primary_model); ?>"><?php echo esc_html($openai_primary_model); ?></option>
                                                </select>
                                                <p class="description"><?php esc_html_e('Recommended: gpt-image-2.5-flare (Fast & High Quality) or gpt-image-2.5-sunburst (Precision Editing).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="fito_openai_fallback_model"><?php esc_html_e('Fallback Model', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                        <td>
                                            <div class="fito-skeleton-box fito-fallback-skeleton">
                                                <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                            </div>
                                            <div class="fito-fallback-select-wrap" style="display: none;">
                                                <select id="fito_openai_fallback_model" name="fito_tryon_openai_fallback_model" class="regular-text" style="max-width: 520px;">
                                                    <option value=""><?php esc_html_e('None (Do not retry)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                                    <?php if (!empty($openai_fallback_model)) : ?>
                                                        <option value="<?php echo esc_attr($openai_fallback_model); ?>"><?php echo esc_html($openai_fallback_model); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <p class="description"><?php esc_html_e('Automatically called if the primary model reaches rate limits or downtime.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        <?php endif; ?>

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
                            <?php if ($is_pro) : ?>
                                <tr>
                                    <th scope="row"><label for="fito_btn_text"><?php esc_html_e('Button Text', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                    <td>
                                        <input type="text" id="fito_btn_text" name="fito_tryon_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e('Custom storefront button label. Leave blank to use default "Try It On with AI".', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                            <?php endif; ?>

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

                            <?php if ($is_pro) : ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Shop Archive Loops', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fito_tryon_archive_button" value="yes" <?php checked($archive_btn, 'yes'); ?>>
                                            <?php esc_html_e('Display "Quick Try-On" button on catalog product cards', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Allows customers to try on products directly from shop catalog loops and archive pages.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Product Gallery Images', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fito_tryon_gallery_buttons" value="yes" <?php checked($gallery_btns, 'yes'); ?>>
                                            <?php esc_html_e('Display try-on button on each product gallery image', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Shows an interactive quick try-on button on each image in the WooCommerce product gallery.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Product Variation Images', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fito_tryon_variation_images" value="yes" <?php checked($variation_btns, 'yes'); ?>>
                                            <?php esc_html_e('Display try-on button on variation images when selected', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Displays a dedicated try-on button specifically matching the active variation image.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="fito_custom_image_selectors"><?php esc_html_e('Custom Image CSS Selectors', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                    <td>
                                        <textarea id="fito_custom_image_selectors" name="fito_tryon_custom_image_selectors" rows="3" class="large-text code" style="max-width: 520px;" placeholder="<?php esc_attr_e('e.g. .elementor-widget-image img, .custom-product-gallery img, .lookbook-photo img', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php echo esc_textarea($custom_selectors); ?></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Enter CSS selectors (comma or newline separated) for images on the product page where a floating Virtual Try-On badge should appear.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Save to Account', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fito_tryon_save_to_account" value="yes" <?php checked($save_to_account, 'yes'); ?>>
                                            <?php esc_html_e('Display "Save to account" button for logged-in customers', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Enables the "Save to account" button in the try-on result modal and adds the "My Try-Ons" tab to WooCommerce My Account.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Pro Appearance Features', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <div class="fito-pro-features-grid">
                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-edit-page"></span>
                                                    <strong><?php esc_html_e('Custom Button Text & CTA', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-button-text')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Customize the try-on button label and product tab title across your storefront with your own brand voice or call-to-action wording (free tier defaults to "Try It On with AI").', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-grid-view"></span>
                                                    <strong><?php esc_html_e('Shop Archive & Catalog Quick Try-On', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-archive-loops')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Allow shoppers to try on products directly from shop loops, category archives, and related product cards without navigating to individual product pages.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-images-alt2"></span>
                                                    <strong><?php esc_html_e('Product Gallery Auxiliary Images', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-gallery-images')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Adds interactive quick try-on overlay buttons directly on each image in the WooCommerce gallery slider, letting shoppers try on different angles.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-image-rotate"></span>
                                                    <strong><?php esc_html_e('Product Variations & Color Swatches', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-variations')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Dynamically binds try-on generation to selected attribute swatches, updating the fitting model to match active variation photos.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-admin-customizer"></span>
                                                    <strong><?php esc_html_e('Custom Image CSS Selectors', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-custom-selectors')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Inject floating try-on badges into Elementor widgets, custom lookbook photo galleries, or bespoke theme sliders using custom CSS selectors.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-pro-feature-card">
                                                <div class="fito-pro-feature-card-header">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    <strong><?php esc_html_e('Customer Account Try-On History', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-save-account')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                                </div>
                                                <p><?php esc_html_e('Allow logged-in shoppers to save virtual try-on results directly to their personal WooCommerce account history with a dedicated "My Try-Ons" dashboard gallery.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- 3. Watermark / Branding -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('3. Watermark & Branding Overlay', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    </div>
                    <div class="fito-card-body">
                        <?php if ($is_pro) : ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Watermark', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="fito_tryon_watermark_enabled" value="yes" <?php checked($wm_enabled, 'yes'); ?>>
                                            <?php esc_html_e('Stamp store logo/watermark onto generated try-on preview images', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Watermark Image', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <input type="hidden" id="fito_watermark_id" name="fito_tryon_watermark_image_id" value="<?php echo esc_attr($wm_id); ?>">
                                        <div id="fito-watermark-preview" style="<?php echo empty($wm_url) ? 'display:none;' : ''; ?>margin-bottom: 10px;">
                                            <img src="<?php echo esc_url($wm_url); ?>" style="max-height: 80px; border: 1px solid #ddd; padding: 4px; border-radius: 4px;" alt="Watermark preview">
                                        </div>
                                        <button type="button" class="button" id="fito-btn-upload-watermark"><?php esc_html_e('Select / Upload Watermark', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                        <button type="button" class="button" id="fito-btn-remove-watermark" style="<?php echo empty($wm_url) ? 'display:none;' : ''; ?>"><?php esc_html_e('Remove', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Watermark Text / Site Address', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; max-width: 480px;">
                                            <input type="text" id="fito_watermark_text" name="fito_tryon_watermark_text" value="<?php echo esc_attr($wm_text); ?>" placeholder="<?php echo esc_attr($suggested_host); ?>" class="regular-text" style="flex: 1;">
                                            <button type="button" class="button" id="fito-btn-use-site-url" data-host="<?php echo esc_attr($suggested_host); ?>"><?php esc_html_e('Use Site Address', 'fito-try-on-with-ai-for-woocommerce'); ?></button>
                                        </div>
                                        <p class="description"><?php esc_html_e('Optional text or website address (e.g. www.yourstore.com) stamped as a clean watermark overlay on generated try-on images.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Watermark Position', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <select name="fito_tryon_watermark_pos" id="fito_watermark_pos" style="min-width: 200px;">
                                            <option value="bottom_right" <?php selected($wm_pos, 'bottom_right'); ?>><?php esc_html_e('Bottom Right (Default)', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="bottom_left" <?php selected($wm_pos, 'bottom_left'); ?>><?php esc_html_e('Bottom Left', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="bottom_center" <?php selected($wm_pos, 'bottom_center'); ?>><?php esc_html_e('Bottom Center', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="top_right" <?php selected($wm_pos, 'top_right'); ?>><?php esc_html_e('Top Right', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="top_left" <?php selected($wm_pos, 'top_left'); ?>><?php esc_html_e('Top Left', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="top_center" <?php selected($wm_pos, 'top_center'); ?>><?php esc_html_e('Top Center', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                            <option value="center" <?php selected($wm_pos, 'center'); ?>><?php esc_html_e('Center', 'fito-try-on-with-ai-for-woocommerce'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Corner or position where the watermark logo and/or text will appear.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Watermark Opacity', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px; max-width: 320px;">
                                            <input type="range" id="fito_watermark_opacity_range" min="10" max="100" step="5" value="<?php echo esc_attr($wm_opacity); ?>" style="flex: 1;">
                                            <input type="number" id="fito_watermark_opacity" name="fito_tryon_watermark_opacity" min="10" max="100" step="1" value="<?php echo esc_attr($wm_opacity); ?>" style="width: 70px; text-align: center;">
                                            <span style="font-weight: 500;">%</span>
                                        </div>
                                        <p class="description"><?php esc_html_e('Transparency of the logo image and text watermark (10% is subtle, 100% is fully solid).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Test & Preview Overlay', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                    <td>
                                        <button type="button" class="button button-secondary" id="fito-btn-preview-watermark">
                                            <span class="dashicons dashicons-visibility"></span>
                                            <?php esc_html_e('Preview Watermark on Sample Image', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </button>
                                        <span class="spinner" id="fito-wm-preview-spinner" style="float: none; margin-top: 4px;"></span>
                                        <div id="fito-wm-preview-container" style="display: none; margin-top: 14px; max-width: 480px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #1e293b;"><?php esc_html_e('Live Composite Preview:', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                                            <img id="fito-wm-preview-img" src="" alt="Watermark Preview" style="width: 100%; height: auto; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: block;">
                                            <div style="margin-top: 8px; font-size: 12px; color: #64748b;"><?php esc_html_e('Renders with your currently selected logo, text, position, and opacity.', 'fito-try-on-with-ai-for-woocommerce'); ?></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        <?php else : ?>
                            <div class="fito-pro-features-grid" style="grid-template-columns: 1fr;">
                                <div class="fito-pro-feature-card" style="border-left: 4px solid #6366f1;">
                                    <div class="fito-pro-feature-card-header">
                                        <span class="dashicons dashicons-format-image" style="color: #6366f1;"></span>
                                        <strong><?php esc_html_e('Watermark & Store Branding Overlay', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-watermark')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                    </div>
                                    <p style="margin-top: 8px; line-height: 1.6; color: #334155;">
                                        <?php esc_html_e('In the Free tier, all generated virtual try-on photos include a discreet "Made with Fito Try-On with AI for WooCommerce" branding badge at the bottom of each image.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </p>
                                    <p style="margin-top: 6px; line-height: 1.6; color: #334155;">
                                        <?php esc_html_e('Upgrade to Pro to disable watermarks completely for 100% clean high-definition output, or fully customize your store branding: upload your own store logo, enter your website address or brand slogan, position anywhere on canvas, adjust transparency (10% to 100%), and test in real-time with the live preview tool.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </p>
                                    <div style="margin-top: 14px; display: flex; align-items: center; gap: 10px;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-watermark')); ?>" class="button button-primary">
                                            <?php esc_html_e('Explore Pro Watermark & Branding Features', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                                        </a>
                                        <a href="https://violo.ir/?p=721" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                            <?php esc_html_e('Get Pro', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4. Rate Limiting, Caching & Privacy -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('4. Abuse Prevention & Data Privacy', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
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
                                    <?php if ($is_pro) : ?>
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
                                    <?php else : ?>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-sample-image')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                <?php esc_html_e('Unlock with Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                                            </a>
                                        </div>
                                        <p class="description">
                                            <?php esc_html_e('Upload a custom branded sample Before/After transformation photo to display inside the guest login prompt modal. The Free tier displays the built-in high-resolution sample showcase graphic.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                        </p>
                                    <?php endif; ?>
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
                        <?php if ($is_pro) : ?>
                            <tr>
                                <th scope="row"><?php esc_html_e('Result Caching', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fito_tryon_cache_enabled" value="yes" <?php checked($cache_enabled, 'yes'); ?>>
                                        <?php esc_html_e('Cache identical photo and product combinations to save AI API usage', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Save Images in Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fito_tryon_log_images" value="yes" <?php checked($log_images, 'yes'); ?>>
                                        <?php esc_html_e('Save customer Before (user photo) and After (try-on result) images in audit logs', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, admin can view Before and After images directly on the Try-On Audit Logs page and inside the log inspect popup. Photos follow the retention window and are auto-deleted when expired.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fito_retention"><?php esc_html_e('Photo Retention Window', 'fito-try-on-with-ai-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="number" id="fito_retention" name="fito_tryon_retention_hours" value="<?php echo esc_attr($retention_hrs); ?>" min="1" max="720" class="small-text">
                                    <span><?php esc_html_e('hours (Temporary photos auto-deleted after this window)', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                            </tr>
                        <?php else : ?>
                            <tr>
                                <th scope="row"><?php esc_html_e('Pro Privacy & Caching', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <td>
                                    <div class="fito-pro-features-grid">
                                        <div class="fito-pro-feature-card">
                                            <div class="fito-pro-feature-card-header">
                                                <span class="dashicons dashicons-database"></span>
                                                <strong><?php esc_html_e('Smart Result Caching', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-caching')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                            </div>
                                            <p><?php esc_html_e('Cache identical photo and product combinations to eliminate duplicate AI calls and save API costs (free tier generates fresh try-ons every time).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        </div>

                                        <div class="fito-pro-feature-card">
                                            <div class="fito-pro-feature-card-header">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <strong><?php esc_html_e('Visual Before & After Photo Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-log-images')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                            </div>
                                            <p><?php esc_html_e('Archive customer user photos and try-on results directly in audit log records for visual comparison in the log modal (free tier logs all metadata and tokens without storing photos).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        </div>

                                        <div class="fito-pro-feature-card">
                                            <div class="fito-pro-feature-card-header">
                                                <span class="dashicons dashicons-clock"></span>
                                                <strong><?php esc_html_e('Flexible Photo Retention Window', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-retention')); ?>" class="fito-badge fito-badge--pro fito-badge-link" title="<?php esc_attr_e('Learn how this Pro feature works', 'fito-try-on-with-ai-for-woocommerce'); ?>"><?php esc_html_e('Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;</a>
                                            </div>
                                            <p><?php esc_html_e('Customize how long unassigned customer photos remain on disk from 1 hour up to 30 days (free tier strictly auto-purges photos within 30 minutes for privacy).', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                        </table>
                    </div>
                </div>

                <!-- 5. Product Categories -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('5. Enabled Product Categories', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
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

    /**
     * Render the Go Pro showcase and comparison page.
     */
    public function render_go_pro_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return;
        }

        $is_pro  = Fito_Tryon::is_pro_active();
        $buy_url = 'https://violo.ir/?p=721';
        ?>
        <div class="wrap fito-admin-wrap fito-pro-wrap">
            <!-- Hero Header -->
            <div class="fito-pro-hero">
                <div class="fito-pro-hero-content">
                    <span class="fito-pro-pill"><?php esc_html_e('Fito AI Try-On Pro', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                    <h1 class="fito-pro-hero-title"><?php esc_html_e('Take Your Virtual Try-On Experience to the Next Level', 'fito-try-on-with-ai-for-woocommerce'); ?></h1>
                    <p class="fito-pro-hero-subtitle">
                        <?php esc_html_e('Empower your shoppers with multi-angle gallery try-on, shop catalog loops, real-time variation switching, custom CSS theme targeting, and customer account fitting history.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </p>
                    <div class="fito-pro-hero-actions">
                        <?php if ($is_pro) : ?>
                            <div class="fito-pro-status-badge fito-pro-status-badge--active">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Fito Pro Addon is Active and Licensed', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </div>
                        <?php else : ?>
                            <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener noreferrer" class="fito-pro-btn fito-pro-btn--primary">
                                <?php esc_html_e('Get Fito Pro Now', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                            </a>
                            <a href="#fito-pro-comparison" class="fito-pro-btn fito-pro-btn--ghost">
                                <?php esc_html_e('Compare Free vs Pro', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Value Highlights Grid -->
            <div class="fito-pro-highlights-grid">
                <div class="fito-pro-highlight-card">
                    <div class="fito-pro-highlight-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <h3><?php esc_html_e('Boost Catalog Conversions', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Shoppers can try on outfits directly from shop loops, category archives, and related products without loading individual product pages.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
                <div class="fito-pro-highlight-card">
                    <div class="fito-pro-highlight-icon">
                        <span class="dashicons dashicons-image-rotate"></span>
                    </div>
                    <h3><?php esc_html_e('Multi-Angle & Variations', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Automatically synchronize try-on with active color swatches and let shoppers preview garments from auxiliary gallery image angles.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
                <div class="fito-pro-highlight-card">
                    <div class="fito-pro-highlight-icon">
                        <span class="dashicons dashicons-admin-customizer"></span>
                    </div>
                    <h3><?php esc_html_e('Works with Any Theme', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Inject try-on buttons anywhere using custom CSS selectors for full compatibility with Elementor, Divi, Gutenberg, or lookbook galleries.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>
            </div>

            <!-- Free vs Pro Comparison Table -->
            <div class="fito-card fito-pro-section" id="fito-pro-comparison">
                <div class="fito-pro-section-header">
                    <h2><?php esc_html_e('Free vs Pro Feature Comparison', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    <p><?php esc_html_e('Compare the core features of the free plugin against the extended capabilities of the Pro Addon.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <div class="fito-table-responsive">
                    <table class="fito-pro-comparison-table">
                        <thead>
                            <tr>
                                <th class="fito-col-feature"><?php esc_html_e('Feature / Capability', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <th class="fito-col-free"><?php esc_html_e('Free Tier', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                                <th class="fito-col-pro"><?php esc_html_e('Pro Addon', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Category 1: AI Engine & Performance -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('AI Engine & Performance', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Single Product Page Virtual Try-On', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Full interactive modal and canvas fitting room on single product pages.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Shopper Upload Canvas & Pose Controls', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Zoom, rotate, scale, and align user photos on the virtual canvas.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <?php esc_html_e('Included', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('AI Model Providers & Direct APIs', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('OpenRouter aggregator vs direct Google Gemini & OpenAI connections.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('OpenRouter Aggregator', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('OpenRouter + Direct Gemini & OpenAI', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Automatic Fallback Model Routing', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Automatic failover retry to secondary backup model on rate limits or outages.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('No (Single Model Only)', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Automated Secondary Model Failover', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Smart Result Caching & Token Conservation', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Cache identical photo and product fittings to eliminate duplicate API requests.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('No (Fresh AI Call Every Time)', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Hash-Based Result Caching', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 2: Storefront & Catalog Placements -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('Storefront, Widget & Placement Controls', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Custom Button Text & CTA Labeling', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Tailor button labels and tab titles to your brand marketing voice.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Fixed Default Text', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Fully Customizable', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Shop Archive & Catalog Quick Try-On', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Try-on triggers on shop grids, category archives, and related products.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Single Product Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Catalog Loop Modal', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Product Gallery Auxiliary Images', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Multi-angle fitting room triggers on all secondary gallery thumbnails.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Primary Image Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('All Gallery Photos & Angles', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Product Variations & Color Swatches', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Dynamic synchronization with active variation attributes and swatches.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Primary Image Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Dynamic Swatch Auto-Sync', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Custom Image CSS Selectors', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Target custom page builders (Elementor, Divi) and lookbook sliders.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Standard Selectors Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Full Custom CSS Targeting', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 3: Customer Account & Guest Experience -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('Customer Account & Guest Experience', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Customer Account Try-On History', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Save try-on results to WooCommerce account history with dedicated "My Try-Ons" tab.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--no">&#10005;</span> <?php esc_html_e('Not Available', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('"Save to account" Button & My Account Gallery', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Custom Guest Sample Try-On Image', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Upload custom Before/After transformation model photos in the guest login modal.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('Built-in Sample Graphic', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Custom Media Library Photo', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 4: Watermark & Store Branding -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('Watermark & Store Branding', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Watermark Removal & Custom Branding', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Option to remove watermarks completely or stamp custom store logo and website address.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('Includes "Made with Fito Try-On with AI for WooCommerce" Watermark', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Clean HD (No Watermark) or Custom Store Logo & Text', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 5: Abuse Prevention, Privacy & Logs -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('Abuse Prevention, Privacy & Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Visual Before & After Photo Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Store customer user photos and try-on results for visual inspection in audit logs.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('Text & Metadata Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Before & After Photo Archives', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Photo Retention Window Customization', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Control how long temporary customer photos remain on disk before auto-cleanup.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('Strict 30 Minutes Max', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Customizable (1h to 30 Days)', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 6: AI Prompts & Category Profiles -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('AI Prompts & Category Profiles', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Custom Category Fitting Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Add and duplicate specialized AI prompts for niche product categories and lines.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><span class="fito-check fito-check--partial">&#9679;</span> <?php esc_html_e('7 Built-in Presets Only', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Unlimited Custom Prompts & Duplication', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>

                            <!-- Category 7: Updates & Priority Support -->
                            <tr class="fito-table-section-heading">
                                <th colspan="3"><?php esc_html_e('Updates & Priority Support', 'fito-try-on-with-ai-for-woocommerce'); ?></th>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e('Technical Support & Updates', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    <span class="fito-table-desc"><?php esc_html_e('Assistance and updates for new WordPress and WooCommerce versions.', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td><?php esc_html_e('Community Forums', 'fito-try-on-with-ai-for-woocommerce'); ?></td>
                                <td><span class="fito-check fito-check--yes">&#10003;</span> <strong><?php esc_html_e('Dedicated Priority Support', 'fito-try-on-with-ai-for-woocommerce'); ?></strong></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td>
                                    <span class="fito-current-tier-text"><?php esc_html_e('Currently Installed', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                </td>
                                <td>
                                    <?php if ($is_pro) : ?>
                                        <span class="fito-pro-active-badge"><?php esc_html_e('Pro Active', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary fito-table-buy-btn">
                                            <?php esc_html_e('Upgrade to Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Detailed Pro Features Breakdown -->
            <div class="fito-card fito-pro-section" id="fito-pro-features">
                <div class="fito-pro-section-header">
                    <h2><?php esc_html_e('Explore All Pro Features in Detail', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                    <p><?php esc_html_e('Learn how each Pro capability functions and how it enhances your customers shopping experience.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                </div>

                <div class="fito-pro-details-list">
                    <!-- Feature 1: Direct APIs -->
                    <div class="fito-pro-detail-card" id="pro-feature-direct-apis">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-cloud"></span>
                            <div>
                                <h3><?php esc_html_e('Direct Google Gemini & Direct OpenAI APIs', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Direct API connectivity to Google AI Studio and OpenAI image generation services without third-party aggregator routing.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Pro unlocks direct API integration panels for Google Gemini and OpenAI. Simply enter your private API keys to utilize Gemini 3.1 Flash Image, Gemini 2.5 Flash Image, Imagen 3, GPT Image 2.5 Flare, and DALL-E 3 directly from their official servers.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Provides the lowest latency, highest availability, and independent billing without relying on aggregator proxies or shared rate pools.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 2: Fallback Model -->
                    <div class="fito-pro-detail-card" id="pro-feature-fallback-model">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-randomize"></span>
                            <div>
                                <h3><?php esc_html_e('Automatic Fallback Model Failover Routing', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('An automated failover system that transparently retries try-on generation with a secondary backup model if the primary model fails.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('In each provider panel, configure a primary model and an alternate fallback model. If the primary model experiences sudden rate limits, capacity errors, or temporary downtime, Pro automatically redirects the request to your fallback model in milliseconds before the shopper encounters any disruption.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Maintains continuous uptime for your virtual dressing room during viral social campaigns and high-traffic shopping rushes.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 3: Result Caching -->
                    <div class="fito-pro-detail-card" id="pro-feature-caching">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-database"></span>
                            <div>
                                <h3><?php esc_html_e('Smart Result Caching & Token Conservation', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('An intelligent hash-based caching system that detects identical user photo, product, variation, and model combinations.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('When a shopper tries on an item with the same photo, Pro serves the previously generated result in milliseconds directly from local transient cache without repeating paid API calls to OpenRouter, Google Gemini, or OpenAI.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Drastically reduces your monthly AI inference bills and ensures instantaneous fitting responses for recurring shoppers.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 4: Button Text -->
                    <div class="fito-pro-detail-card" id="pro-feature-button-text">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-edit-page"></span>
                            <div>
                                <h3><?php esc_html_e('Custom Button Text & CTA Labeling', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Full freedom to customize all frontend call-to-action strings, button labels, and tab headings.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('In the Settings panel under Frontend Widget & Button Appearance, enter your custom text for the single product button (for example: "Virtually Try On", "Enter Dressing Room", or "See How It Looks on You") and custom product tab title. When active, Fito replaces default system strings across single product pages and catalog widgets automatically.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Matches your store branding voice and language nuances, leading to higher click-through rates and stronger shopper engagement.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 5: Shop Archive Loops -->
                    <div class="fito-pro-detail-card" id="pro-feature-archive-loops">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-grid-view"></span>
                            <div>
                                <h3><?php esc_html_e('Shop Archive & Catalog Quick Try-On', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Enables one-click virtual try-on directly inside shop catalogs, category archives, search results, and related product loops.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Pro hooks into the WooCommerce loop item display and appends a floating try-on trigger badge to product thumbnail cards. When clicked, it launches the interactive fitting canvas pre-populated with that product image and metadata, without requiring a page redirect.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Reduces navigation steps, accelerates apparel discovery, and empowers customers to compare multiple styles in seconds.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 6: Product Gallery Images -->
                    <div class="fito-pro-detail-card" id="pro-feature-gallery-images">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <div>
                                <h3><?php esc_html_e('Product Gallery Auxiliary Images', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Allows customers to virtually try on clothing using secondary gallery images (back views, side angles, close-ups), not just the featured photo.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Pro retrieves all attachment IDs from the WooCommerce product gallery and overlays interactive quick-try icons onto thumbnails and sliders. Shoppers can switch the active garment angle in the fitting room with a single tap.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Customers want to see how clothes look from every angle before buying, minimizing return rates and increasing buyer confidence.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 7: Product Variations -->
                    <div class="fito-pro-detail-card" id="pro-feature-variations">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-image-rotate"></span>
                            <div>
                                <h3><?php esc_html_e('Product Variations & Color Swatches', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Dynamic synchronization with WooCommerce variable products, dropdown selectors, and third-party visual color swatches.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Pro listens to variation change events. When a customer selects a specific color or style variation, Pro updates the garment image used by the AI model in real time to match the chosen variation.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Eliminates buyer hesitation by letting customers see the exact colorway and style they plan to purchase.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 8: Custom Selectors -->
                    <div class="fito-pro-detail-card" id="pro-feature-custom-selectors">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-admin-customizer"></span>
                            <div>
                                <h3><?php esc_html_e('Custom Image CSS Selectors', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Ability to define custom CSS selectors to attach try-on buttons to any custom theme layout or page builder component.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Enter comma-separated CSS selectors (e.g. .elementor-widget-image, .lookbook-photo, .custom-product-slider) in settings. The frontend script finds matching elements in the DOM and automatically binds try-on functionality without modifying template files.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Works out-of-the-box with customized Elementor templates, Divi builder, Gutenberg blocks, and custom child themes.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 9: Save to Account -->
                    <div class="fito-pro-detail-card" id="pro-feature-save-account">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-admin-users"></span>
                            <div>
                                <h3><?php esc_html_e('Customer Account Try-On History ("Save to account")', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Allows registered customers to save generated virtual try-ons into their personal WooCommerce account history for later review.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('When enabled in Pro, logged-in customers see a "Save to account" action on try-on result modals. Clicking it connects the generated fitting session to their user ID and adds a dedicated "My Try-Ons" tab under WooCommerce My Account.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Incentivizes user registrations, increases customer return rates, and provides shoppers with a personal virtual wardrobe to revisit outfits and complete purchases.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 10: Custom Guest Sample Image -->
                    <div class="fito-pro-detail-card" id="pro-feature-sample-image">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-format-image"></span>
                            <div>
                                <h3><?php esc_html_e('Custom Guest Sample Try-On Image', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('The ability to upload your own custom Before/After transformation image to greet guest shoppers when virtual try-on is restricted to logged-in customers.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('While the Free tier displays the built-in high-resolution sample graphic, Pro allows store owners to upload bespoke lookbook photos or real customer fitting transformations from their WordPress Media Library to match their specific product line.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Greatly increases visitor-to-member registrations by showing relevant, high-converting apparel transformations tailored to your specific audience.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 11: Watermark & Branding Overlay -->
                    <div class="fito-pro-detail-card" id="pro-feature-watermark">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-art"></span>
                            <div>
                                <h3><?php esc_html_e('Watermark Removal & Custom Store Branding', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('The ability to either produce 100% clean, watermark-free high-definition try-on imagery, or overlay your own custom store logo and website address watermark.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('While the Free tier automatically stamps a discreet "Made with Fito Try-On with AI for WooCommerce" branding badge at the bottom of generated photos, Pro lets you disable watermarks entirely for a white-label storefront, or customize your own brand overlay: upload a store logo, specify a website address or slogan, choose placement across 7 positions (corners and center), configure opacity from 10% to 100%, and verify using the live preview test tool.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Gives you complete brand control, protects your product catalog imagery, and presents an ultra-premium, professional fitting room experience to your customers.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 12: Save Images in Logs -->
                    <div class="fito-pro-detail-card" id="pro-feature-log-images">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-visibility"></span>
                            <div>
                                <h3><?php esc_html_e('Visual Before & After Photo Logs', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Visual storage of customer Before photos and AI-generated After try-on results directly in audit log records.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('While the Free tier logs all metadata, execution tokens, and prompt text without saving photos, Pro enables full visual logging. Store admins can inspect Before and After photo previews directly in the Try-On Audit Logs table and modal popup.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Gives store owners complete visual oversight to evaluate fitting quality, diagnose customer issues, and monitor AI output aesthetics.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 13: Photo Retention Window -->
                    <div class="fito-pro-detail-card" id="pro-feature-retention">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-clock"></span>
                            <div>
                                <h3><?php esc_html_e('Flexible Photo Retention Window', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Customizable lifecycle duration for unassigned temporary customer photos on your server disk.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Free installations enforce an aggressive 30-minute maximum retention window for strict shopper privacy. Pro unlocks full administrative control, allowing you to configure retention from 1 hour up to 30 days (720 hours) to balance privacy with shopper return sessions.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Allows returning shoppers to browse related products and variations without re-uploading their photo repeatedly throughout the day.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 14: Custom Fitting Prompts -->
                    <div class="fito-pro-detail-card" id="pro-feature-custom-prompts">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-welcome-write-blog"></span>
                            <div>
                                <h3><?php esc_html_e('Unlimited Custom Category Fitting Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('The ability to create unlimited custom fitting prompts and duplicate presets for specialized store categories.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('While the Free tier includes complete instruction customization for the 7 standard fashion presets (Clothing, Shoes, Eyewear, Watches, Hats, Bags, and Jewelry), Pro lets you click "Add New Prompt" or duplicate any preset to build tailored fitting profiles for swimwear, scarfs, belts, hijab, costume apparel, and custom artisan catalog niches.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Provides pinpoint prompt engineering and flawless visual placement tailored precisely to your specific catalog categories.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Feature 15: Priority Support -->
                    <div class="fito-pro-detail-card" id="pro-feature-support">
                        <div class="fito-pro-detail-header">
                            <span class="dashicons dashicons-sos"></span>
                            <div>
                                <h3><?php esc_html_e('Priority Updates & Dedicated VIP Support', 'fito-try-on-with-ai-for-woocommerce'); ?></h3>
                                <span class="fito-badge fito-badge--pro"><?php esc_html_e('Pro Feature', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            </div>
                        </div>
                        <div class="fito-pro-detail-body">
                            <p>
                                <strong><?php esc_html_e('What it is:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Direct access to priority technical support and continuous compatibility updates.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('How it works:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Pro customers receive direct email and ticket support with guaranteed response times, theme integration guidance, and seamless updates as WooCommerce evolves.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e('Why it matters:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                <?php esc_html_e('Ensures uninterrupted store operation and total confidence during mission-critical selling seasons.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom CTA Banner -->
            <div class="fito-pro-bottom-cta">
                <h2><?php esc_html_e('Ready to Upgrade Your Virtual Dressing Room?', 'fito-try-on-with-ai-for-woocommerce'); ?></h2>
                <p><?php esc_html_e('Join leading WooCommerce merchants delivering next-generation AI shopping experiences.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                <div class="fito-pro-bottom-cta-actions">
                    <?php if ($is_pro) : ?>
                        <div class="fito-pro-status-badge fito-pro-status-badge--active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('You have Pro features enabled!', 'fito-try-on-with-ai-for-woocommerce'); ?>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url($buy_url); ?>" target="_blank" rel="noopener noreferrer" class="fito-pro-btn fito-pro-btn--primary fito-pro-btn--lg">
                            <?php esc_html_e('Get Fito Pro Now', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
                <p class="fito-pro-guarantee-note">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e('Instant download, seamless installation, and guaranteed WooCommerce compatibility.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    public function handle_save_settings(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_save_settings_nonce');

        if (isset($_POST['fito_tryon_ai_provider'])) {
            $allowed_providers = Fito_Tryon::is_pro_active() ? ['openrouter', 'gemini', 'openai'] : ['openrouter'];
            $provider          = sanitize_key($_POST['fito_tryon_ai_provider']);
            update_option('fito_tryon_ai_provider', in_array($provider, $allowed_providers, true) ? $provider : 'openrouter', false);
        }

        if (isset($_POST['fito_tryon_openrouter_api_key'])) {
            update_option('fito_tryon_openrouter_api_key', sanitize_text_field(wp_unslash($_POST['fito_tryon_openrouter_api_key'])), false);
        }
        if (isset($_POST['fito_tryon_primary_model'])) {
            update_option('fito_tryon_primary_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_primary_model'])), false);
        }

        // Pro Provider Features Guard
        if (Fito_Tryon::is_pro_active()) {
            if (isset($_POST['fito_tryon_fallback_model'])) {
                update_option('fito_tryon_fallback_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_fallback_model'])), false);
            }
            if (isset($_POST['fito_tryon_gemini_api_key'])) {
                update_option('fito_tryon_gemini_api_key', sanitize_text_field(wp_unslash($_POST['fito_tryon_gemini_api_key'])), false);
            }
            if (isset($_POST['fito_tryon_gemini_primary_model'])) {
                update_option('fito_tryon_gemini_primary_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_gemini_primary_model'])), false);
            }
            if (isset($_POST['fito_tryon_gemini_fallback_model'])) {
                update_option('fito_tryon_gemini_fallback_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_gemini_fallback_model'])), false);
            }
            if (isset($_POST['fito_tryon_openai_api_key'])) {
                update_option('fito_tryon_openai_api_key', sanitize_text_field(wp_unslash($_POST['fito_tryon_openai_api_key'])), false);
            }
            if (isset($_POST['fito_tryon_openai_primary_model'])) {
                update_option('fito_tryon_openai_primary_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_openai_primary_model'])), false);
            }
            if (isset($_POST['fito_tryon_openai_fallback_model'])) {
                update_option('fito_tryon_openai_fallback_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_openai_fallback_model'])), false);
            }
        }

        if (isset($_POST['fito_tryon_timeout_seconds'])) {
            update_option('fito_tryon_timeout_seconds', max(15, absint($_POST['fito_tryon_timeout_seconds'])), false);
        }

        if (isset($_POST['fito_tryon_button_position'])) {
            update_option('fito_tryon_button_position', sanitize_text_field(wp_unslash($_POST['fito_tryon_button_position'])));
        }

        // Pro Appearance Features Guard
        if (Fito_Tryon::is_pro_active()) {
            if (isset($_POST['fito_tryon_button_text'])) {
                update_option('fito_tryon_button_text', sanitize_text_field(wp_unslash($_POST['fito_tryon_button_text'])));
            }
            update_option('fito_tryon_archive_button', !empty($_POST['fito_tryon_archive_button']) ? 'yes' : 'no');
            update_option('fito_tryon_gallery_buttons', !empty($_POST['fito_tryon_gallery_buttons']) ? 'yes' : 'no');
            update_option('fito_tryon_variation_images', !empty($_POST['fito_tryon_variation_images']) ? 'yes' : 'no');
            if (isset($_POST['fito_tryon_custom_image_selectors'])) {
                update_option('fito_tryon_custom_image_selectors', sanitize_textarea_field(wp_unslash($_POST['fito_tryon_custom_image_selectors'])), false);
            }
            update_option('fito_tryon_save_to_account', !empty($_POST['fito_tryon_save_to_account']) ? 'yes' : 'no');
        }

        if (Fito_Tryon::is_pro_active()) {
            update_option('fito_tryon_watermark_enabled', !empty($_POST['fito_tryon_watermark_enabled']) ? 'yes' : 'no');
            if (isset($_POST['fito_tryon_watermark_image_id'])) {
                update_option('fito_tryon_watermark_image_id', absint($_POST['fito_tryon_watermark_image_id']));
            }
            if (isset($_POST['fito_tryon_watermark_text'])) {
                update_option('fito_tryon_watermark_text', sanitize_text_field(wp_unslash($_POST['fito_tryon_watermark_text'])));
            }
            if (isset($_POST['fito_tryon_watermark_pos'])) {
                $allowed_positions = ['bottom_right', 'bottom_left', 'bottom_center', 'top_right', 'top_left', 'top_center', 'center'];
                $pos = sanitize_key($_POST['fito_tryon_watermark_pos']);
                update_option('fito_tryon_watermark_pos', in_array($pos, $allowed_positions, true) ? $pos : 'bottom_right', false);
            }
            if (isset($_POST['fito_tryon_watermark_opacity'])) {
                $opacity = max(10, min(100, absint($_POST['fito_tryon_watermark_opacity'])));
                update_option('fito_tryon_watermark_opacity', $opacity, false);
            }
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
        if (Fito_Tryon::is_pro_active()) {
            update_option('fito_tryon_cache_enabled', !empty($_POST['fito_tryon_cache_enabled']) ? 'yes' : 'no', false);
            update_option('fito_tryon_log_images', !empty($_POST['fito_tryon_log_images']) ? 'yes' : 'no', false);

            if (isset($_POST['fito_tryon_retention_hours'])) {
                update_option('fito_tryon_retention_hours', max(1, absint($_POST['fito_tryon_retention_hours'])), false);
            }
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
        if (Fito_Tryon::is_pro_active() && isset($_POST['fito_tryon_sample_image_id'])) {
            update_option('fito_tryon_sample_image_id', absint(wp_unslash($_POST['fito_tryon_sample_image_id'])));
        }

        $cats = isset($_POST['fito_tryon_allowed_categories']) && is_array($_POST['fito_tryon_allowed_categories'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['fito_tryon_allowed_categories']))
            : [];
        update_option('fito_tryon_allowed_categories', $cats, false);

        $this->plugin->cache->flush();

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-settings', 'settings_updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function handle_purge_photos(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'fito-try-on-with-ai-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_purge_nonce');

        $count = $this->plugin->privacy->purge_all_temporary_photos();
        $this->plugin->cache->flush();

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
        if (!Fito_Tryon::is_pro_active()) {
            $prompts = array_values(array_filter($prompts, function ($item) use ($default_ids) {
                return !empty($item['is_system']) || in_array($item['id'] ?? '', $default_ids, true);
            }));
        }

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
        $is_pro = Fito_Tryon::is_pro_active();
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
                        <?php if ($is_pro) : ?>
                            <button type="button" class="button button-primary fito-btn-add-prompt-action" id="fito-btn-add-prompt">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('Add New Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="fito-card-body">
                        <?php if (!$is_pro) : ?>
                            <div class="fito-pro-feature-card" style="margin-bottom: 18px; border-left: 4px solid var(--fito-admin-primary);">
                                <div class="fito-pro-feature-card-header" style="justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="dashicons dashicons-edit-page"></span>
                                        <strong><?php esc_html_e('Unlimited Custom Category Fitting Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                    </div>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-pro#pro-feature-custom-prompts')); ?>" class="fito-badge fito-badge--pro fito-badge-link">
                                        <?php esc_html_e('Unlock with Pro', 'fito-try-on-with-ai-for-woocommerce'); ?> &rarr;
                                    </a>
                                </div>
                                <p>
                                    <?php esc_html_e('The Free tier includes full customization of all 7 built-in fitting presets (Clothing, Shoes, Eyewear, Watches, Hats, Bags, and Jewelry). Adding new custom prompts and duplicating presets requires the Pro Addon.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
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
                                            <?php if ($is_pro) : ?>
                                                <button type="button" class="button button-small fito-prompt-duplicate" title="<?php esc_attr_e('Duplicate Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                                    <span class="dashicons dashicons-admin-page"></span>
                                                </button>
                                            <?php endif; ?>
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

                        <?php if ($is_pro) : ?>
                            <div style="margin-top: 16px; display: flex; justify-content: flex-start;">
                                <button type="button" class="button button-secondary fito-btn-add-prompt-action" id="fito-btn-add-prompt-bottom">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    <?php esc_html_e('Add New Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fito-settings-submit-bar">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Prompts', 'fito-try-on-with-ai-for-woocommerce'); ?>
                    </button>
                </div>
            </form>

            <?php if ($is_pro) : ?>
            <!-- Dynamic Template for Adding New Custom Prompt Row -->
            <template id="fitoPromptRowTemplate">
                <div class="fito-prompt-row is-custom-prompt" data-index="__INDEX__">
                    <div class="fito-prompt-row-header">
                        <div class="fito-prompt-header-left">
                            <span class="dashicons dashicons-menu fito-prompt-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'fito-try-on-with-ai-for-woocommerce'); ?>"></span>
                            <input type="text" name="prompts[__INDEX__][title]" value="<?php esc_attr_e('New Custom Fitting', 'fito-try-on-with-ai-for-woocommerce'); ?>" class="regular-text fito-prompt-title-input" required>
                            <span class="fito-prompt-badge fito-badge-custom"><?php esc_html_e('Custom Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                        </div>
                        <div class="fito-prompt-header-right">
                            <span class="fito-prompt-cat-count" data-count="0"><?php esc_html_e('No categories connected', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                            <button type="button" class="button button-small fito-prompt-duplicate" title="<?php esc_attr_e('Duplicate Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="button button-small fito-prompt-toggle" title="<?php esc_attr_e('Toggle Prompt Details', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <button type="button" class="button button-small fito-prompt-delete" title="<?php esc_attr_e('Delete Custom Prompt', 'fito-try-on-with-ai-for-woocommerce'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>

                    <div class="fito-prompt-row-body">
                        <input type="hidden" name="prompts[__INDEX__][id]" value="custom___TIMESTAMP__">
                        <input type="hidden" name="prompts[__INDEX__][is_system]" value="0">

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
                                        <?php foreach ($categories as $cat) : ?>
                                            <label class="fito-prompt-cat-item">
                                                <input type="checkbox" name="prompts[__INDEX__][categories][]" value="<?php echo esc_attr($cat->term_id); ?>">
                                                <span class="fito-prompt-cat-name"><?php echo esc_html($cat->name); ?></span>
                                                <span class="fito-prompt-cat-id">ID: <?php echo esc_html($cat->term_id); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <p class="fito-cat-empty-notice" style="display: none;"><?php esc_html_e('No available categories to select.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    <?php else : ?>
                                        <p class="fito-muted"><?php esc_html_e('No WooCommerce product categories found.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Check which store categories trigger this prompt.', 'fito-try-on-with-ai-for-woocommerce'); ?></p>
                            </div>

                            <div class="fito-prompt-field-text">
                                <label class="fito-field-label">
                                    <strong><?php esc_html_e('Fitting Prompt Instruction:', 'fito-try-on-with-ai-for-woocommerce'); ?></strong>
                                </label>
                                <div class="fito-placeholder-helpers">
                                    <span class="fito-helper-label"><?php esc_html_e('Available tag:', 'fito-try-on-with-ai-for-woocommerce'); ?></span>
                                    <button type="button" class="button button-small fito-insert-placeholder" data-tag="{product_title}">+ {product_title}</button>
                                </div>
                                <textarea name="prompts[__INDEX__][prompt]" rows="5" class="large-text fito-prompt-textarea" required><?php echo esc_textarea("Virtually fit the item '{product_title}' naturally onto the person in Image 1. Match the scene lighting, body posture, and physical contours realistically."); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Image 1 is the customer photo, Image 2 is the product image. The tag {product_title} is dynamically replaced with the product name.', 'fito-try-on-with-ai-for-woocommerce'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <?php endif; ?>
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

        $is_pro = Fito_Tryon::is_pro_active();
        $existing_prompts = get_option('fito_tryon_prompts_config', []);
        $existing_custom_ids = [];
        if (is_array($existing_prompts)) {
            foreach ($existing_prompts as $ep) {
                if (!empty($ep['id']) && empty($ep['is_system']) && !in_array($ep['id'], $default_ids, true)) {
                    $existing_custom_ids[] = (string) $ep['id'];
                }
            }
        }

        $sanitized_prompts = [];
        $processed_ids = [];
        $claimed_category_ids = [];

        foreach ($raw_prompts as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = !empty($item['id']) ? sanitize_key($item['id']) : ('custom_' . wp_generate_password(6, false, false));
            $is_system = !empty($item['is_system']) || in_array($id, $default_ids, true);

            // Gating: Only Pro users can add or keep custom prompts
            if (!$is_pro && !$is_system) {
                continue;
            }

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
        if (isset($this->plugin->cache) && method_exists($this->plugin->cache, 'flush')) {
            $this->plugin->cache->flush();
        }

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-prompts', 'prompts_updated' => '1'], admin_url('admin.php')));
        exit;
    }
}
