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
            __('AI Try-On', 'elfit-ai-virtual-try-on-for-woocommerce'),
            __('AI Try-On', 'elfit-ai-virtual-try-on-for-woocommerce'),
            $cap,
            'fito-tryon',
            [$this, 'render_dashboard_page'],
            'dashicons-camera',
            56
        );

        add_submenu_page(
            'fito-tryon',
            __('Dashboard', 'elfit-ai-virtual-try-on-for-woocommerce'),
            __('Dashboard', 'elfit-ai-virtual-try-on-for-woocommerce'),
            $cap,
            'fito-tryon',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Prompts', 'elfit-ai-virtual-try-on-for-woocommerce'),
            __('Prompts', 'elfit-ai-virtual-try-on-for-woocommerce'),
            $cap,
            'fito-tryon-prompts',
            [$this, 'render_prompts_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Try-On Logs', 'elfit-ai-virtual-try-on-for-woocommerce'),
            __('Audit Logs', 'elfit-ai-virtual-try-on-for-woocommerce'),
            $cap,
            'fito-tryon-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'fito-tryon',
            __('Settings', 'elfit-ai-virtual-try-on-for-woocommerce'),
            __('Settings', 'elfit-ai-virtual-try-on-for-woocommerce'),
            $cap,
            'fito-tryon-settings',
            [$this, 'render_settings_page']
        );

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
                '4.5.1',
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
                'testing'        => __('Testing connection...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'testSuccess'    => __('Connection verified successfully!', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'fetchingModels' => __('Fetching live models...', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'confirmPurge'     => __('Are you sure you want to delete all temporary customer try-on photos? This cannot be undone.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'confirmClear'     => __('Are you sure you want to clear all audit logs?', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'sentPrompt'       => __('Sent AI Prompt', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'receivedResponse' => __('Received AI Response', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'noPrompt'         => __('No sent prompt recorded for this log entry.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'noResponse'       => __('No AI response recorded for this log entry.', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'copy'             => __('Copy', 'elfit-ai-virtual-try-on-for-woocommerce'),
                'copied'           => __('Copied!', 'elfit-ai-virtual-try-on-for-woocommerce'),
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
                            <?php esc_html_e('AI Try-On Analytics & Overview', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                        </h1>
                        <p class="fito-subtitle"><?php esc_html_e('Monitor customer engagement, AI model performance, and conversion signals.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="fito-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-settings')); ?>" class="button button-primary">
                        <?php esc_html_e('Plugin Settings', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    </a>
                </div>
            </header>

            <!-- KPI Cards Grid -->
            <div class="fito-kpi-grid">
                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Today / This Week', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['today']); ?> <span class="fito-kpi-sub">/ <?php echo esc_html($summary['week']); ?></span></div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Try-on generations completed', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Monthly Total', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['month']); ?></div>
                    <div class="fito-kpi-desc">
                        <?php
                        // translators: %d: Total all-time try-on count
                        printf(esc_html__('Lifetime all-time: %d', 'elfit-ai-virtual-try-on-for-woocommerce'), absint($summary['total']));
                        ?>
                    </div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Success Rate', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val fito-val--accent"><?php echo esc_html($summary['success_rate']); ?>%</div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Healthy AI provider completion', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Avg Generation Time', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val"><?php echo esc_html($summary['avg_duration']); ?>s</div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Customer wait time before preview', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                </div>

                <div class="fito-kpi-card">
                    <div class="fito-kpi-title"><?php esc_html_e('Estimated API Spend', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                    <div class="fito-kpi-val">$<?php echo esc_html(number_format($summary['total_spend'], 4)); ?></div>
                    <div class="fito-kpi-desc"><?php esc_html_e('Direct AI provider usage', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></div>
                </div>
            </div>

            <!-- Chart & Top Products Row -->
            <div class="fito-dashboard-grid">
                <!-- Volume Chart -->
                <div class="fito-card fito-chart-card">
                    <div class="fito-card-header">
                        <h3><?php esc_html_e('Try-On Volume (Past 14 Days)', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h3>
                    </div>
                    <div class="fito-card-body">
                        <canvas id="fitoAnalyticsChart" height="260"></canvas>
                    </div>
                </div>

                <!-- Top Tried-On Products -->
                <div class="fito-card fito-top-products-card">
                    <div class="fito-card-header">
                        <h3><?php esc_html_e('Top Tried-On Products', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h3>
                    </div>
                    <div class="fito-card-body">
                        <?php if (empty($top_products)) : ?>
                            <p class="fito-muted"><?php esc_html_e('No product try-on records recorded yet.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
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
                                                printf(esc_html__('%1$d try-ons (%2$d%% success)', 'elfit-ai-virtual-try-on-for-woocommerce'), absint($item['tryon_count']), absint($item['tryon_count'] > 0 ? round(($item['success_count'] / $item['tryon_count']) * 100) : 0));
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
        $limit  = isset($_GET['limit']) ? absint(wp_unslash($_GET['limit'])) : 10;
        if (!in_array($limit, $allowed_limits, true)) {
            $limit = 10;
        }

        $total_items = $this->plugin->db->get_logs_count([
            'search' => $search,
            'status' => $status,
        ]);
        $total_pages = max(1, (int) ceil($total_items / $limit));

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination page parameter for logs display table.
        $page   = max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1);
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
                    <h1><?php esc_html_e('Try-On Audit Logs', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h1>
                </div>
                <div class="fito-header-actions">
                    <button type="button" class="button button-link-delete" id="fito-btn-clear-logs">
                        <?php esc_html_e('Clear All Logs', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    </button>
                </div>
            </header>

            <!-- Filters -->
            <div class="fito-logs-filter-bar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="fito-tryon-logs">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search model, IP, error...', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>" class="regular-text">
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                        <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Success', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                        <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                    </select>
                    <select name="limit">
                        <option value="10" <?php selected($limit, 10); ?>><?php esc_html_e('10 per page', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                        <option value="20" <?php selected($limit, 20); ?>><?php esc_html_e('20 per page', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                        <option value="50" <?php selected($limit, 50); ?>><?php esc_html_e('50 per page', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                        <option value="100" <?php selected($limit, 100); ?>><?php esc_html_e('100 per page', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></button>
                    <?php if (!empty($search) || !empty($status) || $limit !== 10) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fito-tryon-logs')); ?>" class="button button-secondary"><?php esc_html_e('Reset', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="fito-card">
                <table class="wp-list-table widefat fixed striped fito-table">
                    <thead>
                        <tr>
                            <th width="65"><?php esc_html_e('ID', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="130"><?php esc_html_e('Date / Time', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="115"><?php esc_html_e('User', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Product', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Model Used', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="95"><?php esc_html_e('Status', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Duration', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Cost Est.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                            <th width="85"><?php esc_html_e('Actions', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                            <tr>
                                <td colspan="9" class="fito-text-center"><?php esc_html_e('No log entries match your criteria.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : 
                                $user_obj = !empty($log->user_id) ? get_userdata((int) $log->user_id) : null;
                                // translators: %d: WordPress user ID number
                                $user_name = $user_obj ? $user_obj->display_name : ($log->user_id > 0 ? sprintf(__('User #%d', 'elfit-ai-virtual-try-on-for-woocommerce'), $log->user_id) : __('Guest', 'elfit-ai-virtual-try-on-for-woocommerce'));
                                $user_edit_link = ($user_obj && current_user_can('edit_users')) ? get_edit_user_link($log->user_id) : '';

                                $product = !empty($log->product_id) ? wc_get_product($log->product_id) : null;
                                if ($product) {
                                    $prod_name = $product->get_name();
                                    $view_url  = get_permalink($log->product_id);
                                    $edit_url  = get_edit_post_link($log->product_id);
                                } elseif (!empty($log->product_id)) {
                                    // translators: %d: Product ID
                                    $prod_name = sprintf(__('Deleted Product (ID %d)', 'elfit-ai-virtual-try-on-for-woocommerce'), $log->product_id);
                                    $view_url  = '';
                                    $edit_url  = '';
                                } else {
                                    $prod_name = __('General / No Product', 'elfit-ai-virtual-try-on-for-woocommerce');
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
                                            <span class="fito-muted"><?php esc_html_e('Guest', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
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
                                                        <a href="<?php echo esc_url($edit_url); ?>" target="_blank" class="fito-action-link" title="<?php esc_attr_e('Edit product in WooCommerce admin', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>"><?php esc_html_e('Edit', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($edit_url) && !empty($view_url)) : ?>
                                                        <span class="fito-sep">|</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($view_url)) : ?>
                                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" class="fito-action-link fito-action-view" title="<?php esc_attr_e('View product on store frontend', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>"><?php esc_html_e('View', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
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
                                                data-comp-tok="<?php echo esc_attr($log->completion_tokens); ?>">
                                            <?php esc_html_e('Inspect', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
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
                        printf(esc_html__('Showing %1$d to %2$d of %3$d logs', 'elfit-ai-virtual-try-on-for-woocommerce'), absint($start_item), absint($end_item), absint($total_items));
                        ?>
                    <?php else : ?>
                        <?php esc_html_e('No logs found', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    <?php endif; ?>
                </div>
                <?php if ($total_pages > 1) : ?>
                    <nav class="fito-pagination-nav" aria-label="<?php esc_attr_e('Logs pagination', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base'      => add_query_arg('paged', '%#%', remove_query_arg('paged')),
                            'format'    => '',
                            'prev_text' => '&laquo; ' . esc_html__('Prev', 'elfit-ai-virtual-try-on-for-woocommerce'),
                            'next_text' => esc_html__('Next', 'elfit-ai-virtual-try-on-for-woocommerce') . ' &raquo;',
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
                <h3><?php esc_html_e('Log Details', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h3>
                <div id="fito-log-modal-content"></div>
                <button type="button" class="button" id="fito-close-log-modal"><?php esc_html_e('Close', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></button>
            </div>
        </div>
        <?php
    }

    public function render_settings_page(): void {
        $api_key              = get_option('fito_tryon_openrouter_api_key', '');
        $primary_model        = get_option('fito_tryon_primary_model', 'google/gemini-2.5-flash-image');
        $timeout              = get_option('fito_tryon_timeout_seconds', 60);

        $button_text   = get_option('fito_tryon_button_text', __('Try It On with AI', 'elfit-ai-virtual-try-on-for-woocommerce'));
        $button_pos    = get_option('fito_tryon_button_position', 'after_add_to_cart');

        $rl_enabled       = get_option('fito_tryon_rate_limit_enabled', 'yes');
        $rl_count         = (int) get_option('fito_tryon_rate_limit_count', get_option('fito_tryon_rate_limit_hourly', 10000));
        $rl_period        = get_option('fito_tryon_rate_limit_period', 'hour');
        $log_retention    = get_option('fito_tryon_log_retention', '30');
        $consent_text     = Fito_Tryon_Privacy::get_consent_text();

        $access_level  = get_option('fito_tryon_access_level', 'everyone');
        $my_acct_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        $login_url     = get_option('fito_tryon_login_url', '');
        if (empty($login_url)) {
            $login_url = $my_acct_url;
        }
        $login_notice  = get_option('fito_tryon_login_notice', '');
        if (empty($login_notice)) {
            $login_notice = __('Please sign up or log in to your account to use our AI Virtual Try-On feature and preview how this item looks on you.', 'elfit-ai-virtual-try-on-for-woocommerce');
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
                        <?php esc_html_e('Virtual Try-On Settings', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    </h1>
                </div>
            </header>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification flag.
            if (isset($_GET['settings_updated'])) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
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
                        printf(esc_html__('Stored photos purged successfully (%d files deleted).', 'elfit-ai-virtual-try-on-for-woocommerce'), absint($photos_purged));
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
                                <h2><?php esc_html_e('1. AI Provider Connection & Models', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                                <p class="fito-muted"><?php esc_html_e('Choose your preferred AI service provider for virtual try-on image generation.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                            </div>
                            <div>
                                <button type="button" class="button button-secondary" id="fito-btn-sync-models">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                    <span id="fito-sync-models-text"><?php esc_html_e('Refresh Models from Provider', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                                </button>
                                <span id="fito-sync-models-status" style="margin-left: 8px; font-size: 12px; color: #10b981; display: none;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label><?php esc_html_e('AI Engine', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="fito-provider-badge" style="display: inline-flex; align-items: center; padding: 4px 10px; background: #e0f2fe; color: #0369a1; font-weight: 600; border-radius: 4px; font-size: 13px;">
                                            <?php esc_html_e('OpenRouter (Multi-Model Aggregator)', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                        </span>
                                    </div>
                                    <p class="description"><?php esc_html_e('OpenRouter powers AI virtual try-on image generation with access to top vision models.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <!-- Provider Panel: OpenRouter -->
                        <div class="fito-provider-panel" id="fito-panel-openrouter">
                            <table class="form-table fito-provider-table" id="fito-models-wrapper-openrouter" data-provider="openrouter" data-saved-primary="<?php echo esc_attr($primary_model); ?>">
                                <tr>
                                    <th scope="row"><label for="fito_openrouter_api_key"><?php esc_html_e('OpenRouter API Key', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                    <td>
                                        <div class="fito-key-input-row">
                                            <input type="password" id="fito_openrouter_api_key" name="fito_tryon_openrouter_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off">
                                            <button type="button" class="button fito-btn-test-connection" data-provider="openrouter"><?php esc_html_e('Test Connection', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></button>
                                        </div>
                                        <p class="description"><?php esc_html_e('Get your API key at openrouter.ai/keys.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                        <div class="fito-connection-test-result fito-test-result" style="display: none;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="fito_primary_model"><?php esc_html_e('Primary Model', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                    <td>
                                        <div class="fito-skeleton-box fito-primary-skeleton">
                                            <div class="fito-skeleton-bar fito-skeleton-select"></div>
                                        </div>
                                        <div class="fito-primary-select-wrap" style="display: none;">
                                            <select id="fito_primary_model" name="fito_tryon_primary_model" class="regular-text" style="max-width: 520px;">
                                                <option value="<?php echo esc_attr($primary_model); ?>"><?php echo esc_html($primary_model); ?></option>
                                            </select>
                                            <p class="description"><?php esc_html_e('Only image generation models suitable for virtual try-on are displayed (cached for 24 hours).', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Common Request Timeout -->
                        <table class="form-table" style="margin-top: 10px;">
                            <tr>
                                <th scope="row"><label for="fito_timeout"><?php esc_html_e('Request Timeout (seconds)', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
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
                        <h2><?php esc_html_e('2. Frontend Widget & Button Appearance', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fito_btn_text"><?php esc_html_e('Button Text', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="text" id="fito_btn_text" name="fito_tryon_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Custom storefront button label. Leave blank to use default "Try It On with AI".', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="fito_btn_pos"><?php esc_html_e('Button Placement', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_btn_pos" name="fito_tryon_button_position" class="regular-text">
                                        <option value="after_add_to_cart" <?php selected($button_pos, 'after_add_to_cart'); ?>><?php esc_html_e('Below Add to Cart button (Recommended)', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="before_add_to_cart" <?php selected($button_pos, 'before_add_to_cart'); ?>><?php esc_html_e('Above Add to Cart button', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="product_tab" <?php selected($button_pos, 'product_tab'); ?>><?php esc_html_e('In a custom Product Tab', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="shortcode_only" <?php selected($button_pos, 'shortcode_only'); ?>><?php esc_html_e('Shortcode only [elfit_tryon]', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose where the AI Virtual Try-On button appears on single product pages.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 3. Rate Limiting & Privacy -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('3. Abuse Prevention & Data Privacy', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                    </div>
                    <div class="fito-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fito_access_level"><?php esc_html_e('Access Control', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_access_level" name="fito_tryon_access_level" class="regular-text">
                                        <option value="everyone" <?php selected($access_level, 'everyone'); ?>><?php esc_html_e('Allow everyone (Guests and registered users)', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="logged_in_only" <?php selected($access_level, 'logged_in_only'); ?>><?php esc_html_e('Only logged-in users', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose whether guests can try on items immediately or must sign up / log in to access the virtual fitting room.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_login_url" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="fito_login_url"><?php esc_html_e('Login / Signup Page URL', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; max-width: 580px;">
                                        <input type="url" id="fito_login_url" name="fito_tryon_login_url" value="<?php echo esc_attr($login_url); ?>" placeholder="<?php echo esc_attr($my_acct_url); ?>" class="regular-text" style="flex: 1;">
                                        <button type="button" class="button" id="fito-btn-use-myaccount" data-url="<?php echo esc_attr($my_acct_url); ?>"><?php esc_html_e('Use My Account URL', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></button>
                                    </div>
                                    <p class="description"><?php esc_html_e('Destination URL where guests are directed to sign up or log in. Visitors will be seamlessly returned back to their product page after logging in.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_login_notice" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label for="fito_login_notice"><?php esc_html_e('Signup / Login Prompt Message', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <textarea id="fito_login_notice" name="fito_tryon_login_notice" rows="3" class="large-text"><?php echo esc_textarea($login_notice); ?></textarea>
                                    <p class="description"><?php esc_html_e('Message displayed inside the modal when an unregistered visitor clicks the try-on button.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr id="fito_row_sample_image" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                <th scope="row"><label><?php esc_html_e('Guest Sample Try-On Image', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <input type="hidden" id="fito_sample_image_id" name="fito_tryon_sample_image_id" value="<?php echo esc_attr($sample_img_id ?: ''); ?>">
                                    <div id="fito-sample-image-preview" style="<?php echo $sample_img_url ? '' : 'display:none;'; ?> margin-bottom: 10px;">
                                        <img src="<?php echo esc_url($sample_img_url); ?>" alt="<?php esc_attr_e('Sample try-on preview', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>" style="max-height: 120px; max-width: 240px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: block; object-fit: cover;">
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <button type="button" class="button" id="fito-btn-upload-sample-image">
                                            <span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-right: 4px;"></span>
                                            <?php esc_html_e('Upload Sample Image', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete" id="fito-btn-remove-sample-image" style="<?php echo $sample_img_id ? '' : 'display:none;'; ?>">
                                            <?php esc_html_e('Remove', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Upload a sample virtual try-on or Before/After transformation image to display to guest visitors inside the modal. If empty, the built-in high resolution sample showcase is displayed.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Rate Limiting', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fito_tryon_rate_limit_enabled" value="yes" <?php checked($rl_enabled, 'yes'); ?>>
                                        <span id="fito-rl-label-user" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                            <?php esc_html_e('Enforce try-on limits per user account', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                        </span>
                                        <span id="fito-rl-label-guest" style="<?php echo $access_level === 'logged_in_only' ? 'display:none;' : ''; ?>">
                                            <?php esc_html_e('Enforce try-on limits per device cookie & IP', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                        </span>
                                    </label>
                                    <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
                                        <label for="fito_rl_count"><?php esc_html_e('Max try-ons:', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label>
                                        <input type="number" id="fito_rl_count" name="fito_tryon_rate_limit_count" value="<?php echo esc_attr($rl_count); ?>" min="1" max="100000" class="regular-text" style="max-width: 120px;">
                                        <select id="fito_rl_period" name="fito_tryon_rate_limit_period" style="min-width: 130px;">
                                            <option value="minute" <?php selected($rl_period, 'minute'); ?>><?php esc_html_e('per minute', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                            <option value="hour" <?php selected($rl_period, 'hour'); ?>><?php esc_html_e('per hour', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                            <option value="day" <?php selected($rl_period, 'day'); ?>><?php esc_html_e('per day', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        </select>
                                    </div>
                                    <p class="description" id="fito-rl-desc-user" style="<?php echo $access_level === 'logged_in_only' ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Throttle repeated virtual try-ons per registered customer account to protect your API balance.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                    </p>
                                    <p class="description" id="fito-rl-desc-guest" style="<?php echo $access_level === 'logged_in_only' ? 'display:none;' : ''; ?>">
                                        <?php esc_html_e('Tracks each guest device via a persistent browser cookie so family members sharing the same home WiFi/IP keep independent try-on allowances, backed by an IP safety ceiling against abuse.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fito_log_retention"><?php esc_html_e('Log Retention in Database', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <select id="fito_log_retention" name="fito_tryon_log_retention">
                                        <option value="none" <?php selected($log_retention, 'none'); ?>><?php esc_html_e('Do not log', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="1" <?php selected($log_retention, '1'); ?>><?php esc_html_e('1 day', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="3" <?php selected($log_retention, '3'); ?>><?php esc_html_e('3 days', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="7" <?php selected($log_retention, '7'); ?>><?php esc_html_e('1 week', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="30" <?php selected($log_retention, '30'); ?>><?php esc_html_e('30 days', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                        <option value="never" <?php selected($log_retention, 'never'); ?>><?php esc_html_e('Never clear logs', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Choose how long try-on audit log records are preserved in the database before being automatically purged, or disable logging entirely.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fito_consent_text"><?php esc_html_e('Consent Notice Text', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></label></th>
                                <td>
                                    <textarea id="fito_consent_text" name="fito_tryon_consent_text" rows="3" class="large-text"><?php echo esc_textarea($consent_text); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 4. Product Categories -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('4. Enabled Product Categories', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                        <p class="fito-muted"><?php esc_html_e('Leave all unchecked to enable virtual try-on across your entire store.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
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
                            <p class="fito-muted"><?php esc_html_e('No product categories found.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fito-settings-submit-bar">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Changes', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    </button>
                </div>
            </form>

            <!-- Maintenance Actions -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 32px;">
                <div class="fito-card fito-purge-card">
                    <div class="fito-card-header">
                        <h3><?php esc_html_e('Manual Photo Purge', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h3>
                    </div>
                    <div class="fito-card-body">
                        <p><?php esc_html_e('Immediately delete all temporary customer photos and generated preview files from the server disk.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to purge all stored photos?', 'elfit-ai-virtual-try-on-for-woocommerce')); ?>');">
                            <input type="hidden" name="action" value="fito_tryon_purge_photos">
                            <?php wp_nonce_field('fito_tryon_purge_nonce'); ?>
                            <button type="submit" class="button button-secondary">
                                <?php esc_html_e('Purge All Stored Photos', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save_settings(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'elfit-ai-virtual-try-on-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_save_settings_nonce');

        if (isset($_POST['fito_tryon_openrouter_api_key'])) {
            update_option('fito_tryon_openrouter_api_key', sanitize_text_field(wp_unslash($_POST['fito_tryon_openrouter_api_key'])), false);
        }
        if (isset($_POST['fito_tryon_primary_model'])) {
            update_option('fito_tryon_primary_model', sanitize_text_field(wp_unslash($_POST['fito_tryon_primary_model'])), false);
        }

        if (isset($_POST['fito_tryon_timeout_seconds'])) {
            update_option('fito_tryon_timeout_seconds', max(15, absint(wp_unslash($_POST['fito_tryon_timeout_seconds']))), false);
        }

        if (isset($_POST['fito_tryon_button_position'])) {
            $allowed_positions = ['after_add_to_cart', 'before_add_to_cart', 'product_tab', 'shortcode_only'];
            $btn_pos           = sanitize_key(wp_unslash($_POST['fito_tryon_button_position']));
            update_option('fito_tryon_button_position', in_array($btn_pos, $allowed_positions, true) ? $btn_pos : 'after_add_to_cart');
        }

        if (isset($_POST['fito_tryon_button_text'])) {
            update_option('fito_tryon_button_text', sanitize_text_field(wp_unslash($_POST['fito_tryon_button_text'])));
        }

        update_option('fito_tryon_rate_limit_enabled', !empty($_POST['fito_tryon_rate_limit_enabled']) ? 'yes' : 'no');
        if (isset($_POST['fito_tryon_rate_limit_count'])) {
            $count = max(1, absint(wp_unslash($_POST['fito_tryon_rate_limit_count'])));
            update_option('fito_tryon_rate_limit_count', $count, false);
            update_option('fito_tryon_rate_limit_hourly', $count, false);
        } elseif (isset($_POST['fito_tryon_rate_limit_hourly'])) {
            $count = max(1, absint(wp_unslash($_POST['fito_tryon_rate_limit_hourly'])));
            update_option('fito_tryon_rate_limit_count', $count, false);
            update_option('fito_tryon_rate_limit_hourly', $count, false);
        }
        if (isset($_POST['fito_tryon_rate_limit_period'])) {
            $period = sanitize_key(wp_unslash($_POST['fito_tryon_rate_limit_period']));
            update_option('fito_tryon_rate_limit_period', in_array($period, ['minute', 'hour', 'day'], true) ? $period : 'hour', false);
        }
        if (isset($_POST['fito_tryon_log_retention'])) {
            $allowed_retention = ['none', '1', '3', '7', '30', 'never'];
            $retention_val     = sanitize_key(wp_unslash($_POST['fito_tryon_log_retention']));
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
            ? array_values(array_filter(array_map('absint', wp_unslash($_POST['fito_tryon_allowed_categories']))))
            : [];
        update_option('fito_tryon_allowed_categories', $cats, false);

        do_action('fito_tryon_save_settings_post');

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-settings', 'settings_updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function handle_purge_photos(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'elfit-ai-virtual-try-on-for-woocommerce'));
        }

        check_admin_referer('fito_tryon_purge_nonce');

        $count = $this->plugin->privacy->purge_all_temporary_photos();

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-settings', 'photos_purged' => $count], admin_url('admin.php')));
        exit;
    }

    public function render_prompts_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'elfit-ai-virtual-try-on-for-woocommerce'));
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
        $builtin_ids = array_column($builtin_defaults, 'id');
        $prompts = array_values(array_filter($prompts, function ($p) use ($builtin_ids) {
            return !empty($p['id']) && in_array($p['id'], $builtin_ids, true);
        }));

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
                    <h1><?php esc_html_e('AI Try-On Prompts', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h1>
                    <p class="fito-subtitle"><?php esc_html_e('Configure AI system guardrails and customize fitting prompts mapped to WooCommerce categories.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                </div>
            </header>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notification flag.
            if (isset($_GET['prompts_updated'])) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Prompts and category mappings saved successfully.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="fitoPromptsForm">
                <input type="hidden" name="action" value="fito_tryon_save_prompts">
                <?php wp_nonce_field('fito_tryon_save_prompts_nonce'); ?>

                <!-- 1. System Prompt Card -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2><?php esc_html_e('1. Global AI System Prompt', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                            <p class="fito-muted"><?php esc_html_e('Sets the role, photographic realism standards, identity preservation, and lighting consistency for vision models.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                        </div>
                        <button type="button" class="button button-secondary" id="fito-btn-reset-system-prompt" data-default="<?php echo esc_attr($default_system_prompt); ?>">
                            <?php esc_html_e('Reset to Default', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                        </button>
                    </div>
                    <div class="fito-card-body">
                        <textarea name="fito_tryon_system_prompt" id="fito_tryon_system_prompt" rows="4" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('This instruction is prepended as the system message to guide photorealism, body proportions, and skin/fabric fidelity.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                        </p>
                    </div>
                </div>

                <!-- 2. Category Fitting Prompts Repeater -->
                <div class="fito-card fito-settings-card">
                    <div class="fito-card-header">
                        <h2><?php esc_html_e('2. Category Try-On Prompts', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></h2>
                        <p class="fito-muted"><?php esc_html_e('Map WooCommerce product categories to specialized fitting prompts. Works across any language because mapping uses category IDs. If a product has no mapped prompt, the default Clothing & Apparel prompt is used.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                    </div>
                    <div class="fito-card-body">
                        <div class="fito-prompts-repeater" id="fitoPromptsRepeater">
                            <?php foreach ($prompts as $index => $prompt_item) :
                                $p_id = $prompt_item['id'] ?? ('prompt_' . $index);
                                $p_title = $prompt_item['title'] ?? __('Prompt', 'elfit-ai-virtual-try-on-for-woocommerce');
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
                                <div class="fito-prompt-row is-collapsed is-system-preset" data-index="<?php echo esc_attr($index); ?>">
                                    <div class="fito-prompt-row-header">
                                        <div class="fito-prompt-header-left">
                                            <span class="dashicons dashicons-menu fito-prompt-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>"></span>
                                            <input type="text" name="prompts[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($p_title); ?>" class="regular-text fito-prompt-title-input" placeholder="<?php esc_attr_e('Prompt Title', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>" required>
                                            <span class="fito-prompt-badge fito-badge-system">
                                                <?php esc_html_e('Built-in Preset', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                            </span>
                                        </div>
                                        <div class="fito-prompt-header-right">
                                            <span class="fito-prompt-cat-count" data-count="<?php echo esc_attr($cat_count); ?>">
                                                <?php
                                                if ($cat_count > 0) {
                                                    // translators: %d: Number of connected categories
                                                    printf(esc_html__('%d categories connected', 'elfit-ai-virtual-try-on-for-woocommerce'), absint($cat_count));
                                                } elseif ($p_id === 'clothing') {
                                                    esc_html_e('Default Fallback (All other categories)', 'elfit-ai-virtual-try-on-for-woocommerce');
                                                } else {
                                                    esc_html_e('No categories connected', 'elfit-ai-virtual-try-on-for-woocommerce');
                                                }
                                                ?>
                                            </span>
                                            <button type="button" class="button button-small fito-prompt-toggle" title="<?php esc_attr_e('Toggle Prompt Details', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="fito-prompt-row-body">
                                        <input type="hidden" name="prompts[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($p_id); ?>">
                                        <input type="hidden" name="prompts[<?php echo esc_attr($index); ?>][is_system]" value="<?php echo $p_is_system ? '1' : '0'; ?>">

                                        <div class="fito-prompt-fields-grid">
                                            <div class="fito-prompt-field-cats">
                                                <label class="fito-field-label">
                                                    <strong><?php esc_html_e('Connected WooCommerce Categories:', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></strong>
                                                </label>
                                                <div class="fito-cat-filter-wrap">
                                                    <input type="text" class="fito-prompt-cat-search" placeholder="<?php esc_attr_e('Filter categories...', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
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
                                                        <p class="fito-cat-empty-notice" style="display: none;"><?php esc_html_e('No available categories to select.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                                    <?php else : ?>
                                                        <p class="fito-muted"><?php esc_html_e('No WooCommerce product categories found.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="description"><?php esc_html_e('Check which store categories trigger this prompt. Categories can be named in any language.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></p>
                                            </div>

                                            <div class="fito-prompt-field-text">
                                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
                                                    <label class="fito-field-label" style="margin-bottom: 0;">
                                                        <strong><?php esc_html_e('Fitting Prompt Instruction:', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></strong>
                                                    </label>
                                                    <div class="fito-placeholder-helpers" style="margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
                                                        <span class="fito-helper-label"><?php esc_html_e('Available tag:', 'elfit-ai-virtual-try-on-for-woocommerce'); ?></span>
                                                        <button type="button" class="button button-small fito-insert-placeholder" data-tag="{product_title}">+ {product_title}</button>
                                                        <?php if ($p_is_system && !empty($default_text)) : ?>
                                                            <button type="button" class="button button-small fito-revert-default-prompt" data-default="<?php echo esc_attr($default_text); ?>" title="<?php esc_attr_e('Revert instruction back to built-in default', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>">
                                                                <span class="dashicons dashicons-undo"></span>
                                                                <?php esc_html_e('Revert to Default', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <textarea name="prompts[<?php echo esc_attr($index); ?>][prompt]" rows="5" class="large-text fito-prompt-textarea" required><?php echo esc_textarea($p_text); ?></textarea>
                                                <?php if ($p_is_system && !empty($default_text)) : ?>
                                                    <details class="fito-default-prompt-box" style="margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px;">
                                                        <summary style="cursor: pointer; font-size: 12px; font-weight: 600; color: #475569; user-select: none;">
                                                            <span class="dashicons dashicons-info" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; margin-right: 4px;"></span>
                                                            <?php esc_html_e('Original Built-in Default Prompt (Click to view):', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                                        </summary>
                                                        <div style="margin-top: 6px; font-size: 12px; color: #64748b; font-style: italic; line-height: 1.5; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px;">
                                                            <?php echo esc_html($default_text); ?>
                                                        </div>
                                                    </details>
                                                <?php endif; ?>
                                                <p class="description">
                                                    <?php esc_html_e('Image 1 is the customer photo, Image 2 is the product image. The tag {product_title} is dynamically replaced with the product name. Variation attributes are automatically appended.', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="fito-settings-submit-bar">
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e('Save Prompts', 'elfit-ai-virtual-try-on-for-woocommerce'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    public function handle_save_prompts(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'elfit-ai-virtual-try-on-for-woocommerce'));
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

            $id = !empty($item['id']) ? sanitize_key($item['id']) : '';
            if (empty($id) || !in_array($id, $default_ids, true)) {
                continue;
            }

            $default_obj = null;
            foreach ($default_prompts as $dp) {
                if ($dp['id'] === $id) {
                    $default_obj = $dp;
                    break;
                }
            }

            $title = !empty($item['title']) ? sanitize_text_field($item['title']) : ($default_obj['title'] ?? $id);
            $prompt = !empty($item['prompt']) ? sanitize_textarea_field($item['prompt']) : ($default_obj['prompt'] ?? '');

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
                'is_system'  => true,
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

        wp_safe_redirect(add_query_arg(['page' => 'fito-tryon-prompts', 'prompts_updated' => '1'], admin_url('admin.php')));
        exit;
    }
}
