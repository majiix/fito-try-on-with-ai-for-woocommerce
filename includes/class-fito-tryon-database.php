<?php
/**
 * Database operations and schema manager for Elfit AI Virtual Try-On for WooCommerce.
 */

defined('ABSPATH') || exit;

if (!class_exists('Fito_Tryon_Database')) {
class Fito_Tryon_Database {
    public const DB_VERSION = '1.4.1';

    /**
     * Create custom database tables using dbDelta.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $jobs_table = $wpdb->prefix . 'fito_tryon_jobs';
        $logs_table = $wpdb->prefix . 'fito_tryon_logs';

        $sql_jobs = "CREATE TABLE {$jobs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_token varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            progress int(11) NOT NULL DEFAULT 0,
            status_message varchar(255) NOT NULL DEFAULT '',
            user_image_path varchar(255) NOT NULL DEFAULT '',
            user_image_url varchar(500) NOT NULL DEFAULT '',
            product_image_url varchar(500) NOT NULL DEFAULT '',
            result_image_url varchar(500) NOT NULL DEFAULT '',
            result_image_path varchar(255) NOT NULL DEFAULT '',
            model_used varchar(100) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_token (job_token),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY user_status_created (user_id, status, created_at)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            model_used varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            duration_sec decimal(6,2) NOT NULL DEFAULT 0.00,
            prompt_tokens int(11) NOT NULL DEFAULT 0,
            completion_tokens int(11) NOT NULL DEFAULT 0,
            cost_estimate decimal(8,4) NOT NULL DEFAULT 0.0000,
            user_image_url text DEFAULT NULL,
            result_image_url text DEFAULT NULL,
            error_message text,
            prompt_text longtext DEFAULT NULL,
            response_text longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY ip_product_status_created (ip_address, product_id, status, created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_jobs);
        dbDelta($sql_logs);
    }

    public static function maybe_upgrade_schema(): void {
        if (get_option('fito_tryon_db_version') !== self::DB_VERSION) {
            self::create_tables();

            // Backfill any empty response_text for logs that have an error_message
            global $wpdb;
            $logs_table = $wpdb->prefix . 'fito_tryon_logs';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $empty_rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, error_message FROM %i WHERE (response_text IS NULL OR response_text = \'\') AND error_message IS NOT NULL AND error_message != \'\' LIMIT 500',
                    $logs_table
                )
            );
            // phpcs:enable
            if (!empty($empty_rows)) {
                foreach ($empty_rows as $row) {
                    $backfill_json = wp_json_encode(['error' => $row->error_message], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table schema upgrade update.
                    $wpdb->update($logs_table, ['response_text' => $backfill_json], ['id' => $row->id]);
                }
            }

            update_option('fito_tryon_db_version', self::DB_VERSION);
        }
    }

    public function create_job(array $data): string {
        global $wpdb;
        $now = current_time('mysql');
        $token = wp_generate_password(32, false);

        $insert_data = [
            'job_token'           => $token,
            'user_id'             => isset($data['user_id']) ? absint($data['user_id']) : 0,
            'product_id'          => absint($data['product_id']),
            'variation_id'        => isset($data['variation_id']) ? absint($data['variation_id']) : 0,
            'status'              => 'pending',
            'progress'            => 10,
            'status_message'      => __('Preparing try-on request...', 'elfit-ai-virtual-try-on-for-woocommerce'),
            'user_image_path'     => isset($data['user_image_path']) ? sanitize_text_field($data['user_image_path']) : '',
            'user_image_url'      => isset($data['user_image_url']) ? esc_url_raw($data['user_image_url']) : '',
            'product_image_url'   => isset($data['product_image_url']) ? esc_url_raw($data['product_image_url']) : '',
            'result_image_url'    => '',
            'result_image_path'   => '',
            'model_used'          => isset($data['model_used']) ? sanitize_text_field($data['model_used']) : '',
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
        $wpdb->insert($wpdb->prefix . 'fito_tryon_jobs', $insert_data);
        return $token;
    }

    public function get_job_by_token(string $token): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_jobs';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE job_token = %s LIMIT 1', $table, $token));
        // phpcs:enable
        return $row ?: null;
    }

    public function update_job_by_token(string $token, array $data): bool {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
        $result = $wpdb->update(
            $wpdb->prefix . 'fito_tryon_jobs',
            $data,
            ['job_token' => $token]
        );
        return $result !== false;
    }

    public function add_log(array $data): int {
        if (get_option('fito_tryon_log_retention', '30') === 'none') {
            return 0;
        }

        global $wpdb;

        $insert_data = [
            'job_id'            => isset($data['job_id']) ? absint($data['job_id']) : 0,
            'user_id'           => isset($data['user_id']) ? absint($data['user_id']) : 0,
            'ip_address'        => isset($data['ip_address']) ? sanitize_text_field($data['ip_address']) : '',
            'product_id'        => isset($data['product_id']) ? absint($data['product_id']) : 0,
            'variation_id'      => isset($data['variation_id']) ? absint($data['variation_id']) : 0,
            'model_used'        => isset($data['model_used']) ? sanitize_text_field($data['model_used']) : '',
            'status'            => isset($data['status']) ? sanitize_text_field($data['status']) : 'success',
            'duration_sec'      => isset($data['duration_sec']) ? floatval($data['duration_sec']) : 0.00,
            'prompt_tokens'     => isset($data['prompt_tokens']) ? absint($data['prompt_tokens']) : 0,
            'completion_tokens' => isset($data['completion_tokens']) ? absint($data['completion_tokens']) : 0,
            'cost_estimate'     => isset($data['cost_estimate']) ? floatval($data['cost_estimate']) : 0.0000,
            'error_message'     => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
            'prompt_text'       => isset($data['prompt_text']) ? (is_string($data['prompt_text']) ? trim($data['prompt_text']) : sanitize_textarea_field((string) $data['prompt_text'])) : null,
            'response_text'     => !empty($data['response_text'])
                ? (is_array($data['response_text']) ? wp_json_encode($data['response_text'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (is_string($data['response_text']) ? trim($data['response_text']) : (string) $data['response_text']))
                : (!empty($data['error_message']) ? wp_json_encode(['error' => $data['error_message']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null),
            'created_at'        => current_time('mysql'),
        ];

        if (!empty($data['user_image_url'])) {
            $insert_data['user_image_url'] = esc_url_raw($data['user_image_url']);
        }
        if (!empty($data['result_image_url'])) {
            $insert_data['result_image_url'] = esc_url_raw($data['result_image_url']);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
        $wpdb->insert($wpdb->prefix . 'fito_tryon_logs', $insert_data);
        $insert_id = (int) $wpdb->insert_id;

        /**
         * Fires after a try-on audit log entry has been created.
         *
         * @param int $insert_id New log entry ID.
         * @param array<string, mixed> $data Raw log data.
         */
        do_action('fito_tryon_after_log_created', $insert_id, $data);

        return $insert_id;
    }

    public function get_logs(array $args = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        $where = ['1=1'];
        $params = [$table];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(model_used LIKE %s OR ip_address LIKE %s OR error_message LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $limit = isset($args['limit']) ? absint($args['limit']) : 20;
        $offset = isset($args['offset']) ? absint($args['offset']) : 0;

        $sql = "SELECT * FROM %i WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        // phpcs:enable
        return (array) $results;
    }

    public function get_logs_count(array $args = []): int {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        $where = ['1=1'];
        $params = [$table];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(model_used LIKE %s OR ip_address LIKE %s OR error_message LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count = $wpdb->get_var($wpdb->prepare($sql, ...$params));
        // phpcs:enable
        return (int) $count;
    }

    public function delete_log(int $log_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare('SELECT user_image_url, result_image_url FROM %i WHERE id = %d LIMIT 1', $table, $log_id));
        if ($log) {
            if (!empty($log->user_image_url)) {
                Fito_Tryon_Storage::delete_file_by_url($log->user_image_url);
            }
            if (!empty($log->result_image_url)) {
                Fito_Tryon_Storage::delete_file_by_url($log->result_image_url);
            }
        }
        return $wpdb->delete($table, ['id' => $log_id]) !== false;
        // phpcs:enable
    }

    public function clear_all_logs(): bool {
        global $wpdb;
        wp_cache_delete('fito_analytics_summary', 'fito_tryon');
        wp_cache_delete('fito_chart_data_14', 'fito_tryon');
        wp_cache_delete('fito_top_products_5', 'fito_tryon');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table truncation.
        return $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}fito_tryon_logs") !== false;
    }

    public function get_analytics_summary(): array {
        $cached = wp_cache_get('fito_analytics_summary', 'fito_tryon');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $today_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE DATE(created_at) = CURDATE()', $table));
        $week_count  = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', $table));
        $month_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $table));
        $total_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));

        $success_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status = 'success'", $table));
        $success_rate = $total_count > 0 ? round(($success_count / $total_count) * 100, 1) : 100;

        $avg_duration = (float) $wpdb->get_var($wpdb->prepare("SELECT AVG(duration_sec) FROM %i WHERE status = 'success'", $table));
        $total_spend  = (float) $wpdb->get_var($wpdb->prepare('SELECT SUM(cost_estimate) FROM %i', $table));
        // phpcs:enable

        $summary = [
            'today'        => $today_count,
            'week'         => $week_count,
            'month'        => $month_count,
            'total'        => $total_count,
            'success_rate' => $success_rate,
            'avg_duration' => round($avg_duration, 1),
            'total_spend'  => round($total_spend, 4),
        ];

        wp_cache_set('fito_analytics_summary', $summary, 'fito_tryon', 300);
        return $summary;
    }

    public function get_chart_data(int $days = 14): array {
        $cache_key = 'fito_chart_data_' . $days;
        $cached = wp_cache_get($cache_key, 'fito_tryon');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as log_date, 
                    COUNT(*) as total, 
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful
             FROM %i
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY log_date ASC",
            $table,
            $days
        ));
        // phpcs:enable

        $labels = [];
        $totals = [];
        $successes = [];

        // Fill all dates in range even if 0
        for ($i = $days; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', strtotime("-{$i} days"));
            $labels[] = gmdate('M j', strtotime($d));
            $found = false;
            if ($results) {
                foreach ($results as $r) {
                    if ($r->log_date === $d) {
                        $totals[] = (int) $r->total;
                        $successes[] = (int) $r->successful;
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                $totals[] = 0;
                $successes[] = 0;
            }
        }

        $chart_data = [
            'labels'    => $labels,
            'totals'    => $totals,
            'successes' => $successes,
        ];

        wp_cache_set($cache_key, $chart_data, 'fito_tryon', 300);
        return $chart_data;
    }

    public function get_top_products(int $limit = 5): array {
        $cache_key = 'fito_top_products_' . $limit;
        $cached = wp_cache_get($cache_key, 'fito_tryon');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, 
                    COUNT(*) as tryon_count, 
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
             FROM %i
             WHERE product_id > 0
             GROUP BY product_id
             ORDER BY tryon_count DESC
             LIMIT %d",
            $table,
            $limit
        ));
        // phpcs:enable

        $top = [];
        if (!empty($results)) {
            $product_ids = array_filter(array_map(static function($r) {
                return absint($r->product_id);
            }, $results));

            if (!empty($product_ids) && function_exists('_prime_post_caches')) {
                _prime_post_caches($product_ids, true, true);
            }

            foreach ($results as $row) {
                $pid = (int) $row->product_id;
                $product = $pid > 0 ? wc_get_product($pid) : null;
                if ($product) {
                    $prod_fallback = $product->get_name();
                } elseif ($pid > 0) {
                    // translators: %d: Product ID number
                    $prod_fallback = sprintf(__('Deleted Product (ID %d)', 'elfit-ai-virtual-try-on-for-woocommerce'), $pid);
                } else {
                    $prod_fallback = __('Unknown Product', 'elfit-ai-virtual-try-on-for-woocommerce');
                }
                $top[] = [
                    'id'            => $pid,
                    'name'          => $prod_fallback,
                    'url'           => ($product && $pid > 0) ? get_permalink($pid) : '#',
                    'image'         => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
                    'tryon_count'   => (int) $row->tryon_count,
                    'success_count' => (int) $row->success_count,
                ];
            }
        }

        wp_cache_set($cache_key, $top, 'fito_tryon', 3600);
        return $top;
    }

    public function get_expired_jobs(int $retention_minutes): array {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_jobs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_image_path, result_image_path FROM %i 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)
             LIMIT 100",
            $table,
            max(1, $retention_minutes)
        ));
        // phpcs:enable

        if (!is_array($results) || empty($results)) {
            return [];
        }

        /**
         * Filter expired jobs prior to file deletion and database purge.
         *
         * @param array $results List of expired job rows.
         */
        return (array) apply_filters('fito_tryon_expired_jobs_to_purge', $results);
    }

    /**
     * Atomically batch delete expired jobs by their IDs.
     *
     * @param int[] $job_ids
     * @return int
     */
    public function delete_expired_jobs(array $job_ids): int {
        if (empty($job_ids)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_jobs';
        $sanitized_ids = array_filter(array_map('absint', $job_ids));
        if (empty($sanitized_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE id IN ({$placeholders})", $table, ...$sanitized_ids));
        // phpcs:enable
        return $deleted !== false ? (int) $deleted : 0;
    }

    public function get_expired_logs(int $days, int $limit = 200): array {
        if ($days <= 0) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            'SELECT id, user_image_url, result_image_url FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT %d',
            $table,
            $days,
            $limit
        ));
        // phpcs:enable
        return is_array($results) ? $results : [];
    }

    public function delete_expired_logs(int $days): int {
        if ($days <= 0) {
            return 0;
        }

        $logs = $this->get_expired_logs($days, 500);
        if (!empty($logs)) {
            do_action('fito_tryon_before_delete_expired_logs', $days);
            foreach ($logs as $log) {
                if (!empty($log->user_image_url)) {
                    Fito_Tryon_Storage::delete_file_by_url($log->user_image_url);
                }
                if (!empty($log->result_image_url)) {
                    Fito_Tryon_Storage::delete_file_by_url($log->result_image_url);
                }
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $deleted = $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
            $table,
            $days
        ));
        // phpcs:enable
        return $deleted !== false ? (int) $deleted : 0;
    }

    public function purge_temporary_jobs(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_jobs';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i', $table));
        // phpcs:enable
        return $deleted !== false ? (int) $deleted : 0;
    }

    public function has_recent_failed_log(string $ip_address, int $product_id, int $seconds = 60): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM %i WHERE ip_address = %s AND product_id = %d AND status = 'failed' AND created_at >= %s LIMIT 1",
            $table,
            $ip_address,
            $product_id,
            $since
        ));
        // phpcs:enable
        return !empty($recent);
    }
}
}
