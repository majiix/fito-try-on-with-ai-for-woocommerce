<?php
/**
 * Automated test suite for Result Caching, Save Images in Logs, and Photo Retention Pro gating.
 */

// Load WordPress environment
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/class-fito-tryon-admin.php';

class Fito_Privacy_Pro_Gating_Test {
    private Fito_Tryon $plugin;
    private array $created_log_ids = [];
    private array $created_job_ids = [];

    public function __construct() {
        $this->plugin = Fito_Tryon::instance();
        if ($this->plugin->admin === null) {
            $this->plugin->admin = new Fito_Tryon_Admin($this->plugin);
        }
    }

    public function run_all(): void {
        echo "=== Running Privacy, Caching & Retention Pro Gating Tests ===\n";

        $this->test_result_caching_gating();
        $this->test_log_images_gating();
        $this->test_photo_retention_window_gating();
        $this->test_settings_save_gating();
        $this->test_go_pro_page_anchors();
        $this->test_em_dash_guardrail();

        $this->cleanup();
        echo "\n[PASS] All Privacy, Caching & Retention Pro Gating Tests Completed Successfully!\n";
    }

    private function test_result_caching_gating(): void {
        echo "- Testing Result Caching Pro Gating... ";

        update_option('fito_tryon_cache_enabled', 'yes');

        // 1. Free tier: cache must be disabled regardless of option
        add_filter('fito_tryon_is_pro_active', '__return_false');
        assert($this->plugin->cache->is_enabled() === false, 'Cache must be disabled in Free tier even when option is yes');
        remove_filter('fito_tryon_is_pro_active', '__return_false');

        // 2. Pro tier: cache respects option
        add_filter('fito_tryon_is_pro_active', '__return_true');
        assert($this->plugin->cache->is_enabled() === true, 'Cache must be enabled in Pro tier when option is yes');

        update_option('fito_tryon_cache_enabled', 'no');
        assert($this->plugin->cache->is_enabled() === false, 'Cache must be disabled in Pro tier when option is no');
        remove_filter('fito_tryon_is_pro_active', '__return_true');

        echo "PASSED\n";
    }

    private function test_log_images_gating(): void {
        echo "- Testing Save Images in Logs Pro Gating... ";

        update_option('fito_tryon_log_images', 'yes');

        // 1. Free tier: images must NOT be stored in log records
        add_filter('fito_tryon_is_pro_active', '__return_false');
        $log_id_free = $this->plugin->db->add_log([
            'status'           => 'success',
            'user_image_url'   => 'https://example.com/user_test.jpg',
            'result_image_url' => 'https://example.com/result_test.jpg',
            'prompt_text'      => 'Test prompt free',
        ]);
        remove_filter('fito_tryon_is_pro_active', '__return_false');
        $this->created_log_ids[] = $log_id_free;

        $log_free = $this->get_log_by_id($log_id_free);
        assert(empty($log_free->user_image_url), 'Free tier log must not store user_image_url');
        assert(empty($log_free->result_image_url), 'Free tier log must not store result_image_url');
        assert(!empty($log_free->prompt_text), 'Free tier log must still preserve prompt text and metadata');

        // 2. Pro tier: images ARE stored when option is yes
        add_filter('fito_tryon_is_pro_active', '__return_true');
        $log_id_pro = $this->plugin->db->add_log([
            'status'           => 'success',
            'user_image_url'   => 'https://example.com/user_test_pro.jpg',
            'result_image_url' => 'https://example.com/result_test_pro.jpg',
            'prompt_text'      => 'Test prompt pro',
        ]);
        remove_filter('fito_tryon_is_pro_active', '__return_true');
        $this->created_log_ids[] = $log_id_pro;

        $log_pro = $this->get_log_by_id($log_id_pro);
        assert($log_pro->user_image_url === 'https://example.com/user_test_pro.jpg', 'Pro tier log must store user_image_url');
        assert($log_pro->result_image_url === 'https://example.com/result_test_pro.jpg', 'Pro tier log must store result_image_url');

        echo "PASSED\n";
    }

    private function test_photo_retention_window_gating(): void {
        echo "- Testing Photo Retention Window (Free = 30m max)... ";

        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_jobs';

        // Insert a dummy job created 45 minutes ago
        $token = 'test_retention_' . wp_generate_password(8, false);
        $wpdb->insert($table, [
            'job_token'            => $token,
            'status'               => 'completed',
            'created_at'           => gmdate('Y-m-d H:i:s', time() - (45 * 60)),
            'updated_at'           => gmdate('Y-m-d H:i:s', time() - (45 * 60)),
            'product_id'           => 1,
            'user_image_path'      => '',
            'result_image_path'    => '',
            'is_saved_to_account'  => 0,
        ]);
        $job_id = (int) $wpdb->insert_id;
        $this->created_job_ids[] = $job_id;

        // Query with 30 minutes (Free tier limit)
        $expired_30m = $this->plugin->db->get_expired_jobs(30);
        $found = false;
        foreach ($expired_30m as $job) {
            if ((int) $job->id === $job_id) {
                $found = true;
                break;
            }
        }
        assert($found === true, 'Job from 45 minutes ago must be recognized as expired under 30m retention');

        // Query with 60 minutes (1 hour)
        $expired_60m = $this->plugin->db->get_expired_jobs(60);
        $found_60m = false;
        foreach ($expired_60m as $job) {
            if ((int) $job->id === $job_id) {
                $found_60m = true;
                break;
            }
        }
        assert($found_60m === false, 'Job from 45 minutes ago must not be expired under 60m retention');

        echo "PASSED\n";
    }

    private function test_settings_save_gating(): void {
        echo "- Testing Settings Save Gating (Free tier cannot modify Pro privacy options)... ";

        update_option('fito_tryon_cache_enabled', 'no');
        update_option('fito_tryon_log_images', 'no');
        update_option('fito_tryon_retention_hours', 24);

        wp_set_current_user(1);

        $_POST = [
            '_wpnonce'                   => wp_create_nonce('fito_tryon_save_settings_nonce'),
            'fito_tryon_cache_enabled'   => 'yes',
            'fito_tryon_log_images'      => 'yes',
            'fito_tryon_retention_hours' => '48',
        ];
        $_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

        add_filter('wp_redirect', function ($location) {
            throw new Exception("Redirect: $location");
        });

        add_filter('fito_tryon_is_pro_active', '__return_false');
        try {
            $this->plugin->admin->handle_save_settings();
        } catch (Exception $e) {
            // Caught redirect
        }
        remove_filter('fito_tryon_is_pro_active', '__return_false');
        remove_all_filters('wp_redirect');

        assert(get_option('fito_tryon_cache_enabled') === 'no', 'Free tier save must not alter fito_tryon_cache_enabled');
        assert(get_option('fito_tryon_log_images') === 'no', 'Free tier save must not alter fito_tryon_log_images');
        assert((int) get_option('fito_tryon_retention_hours') === 24, 'Free tier save must not alter fito_tryon_retention_hours');

        $_POST = [];
        echo "PASSED\n";
    }

    private function test_go_pro_page_anchors(): void {
        echo "- Testing Go Pro Page Caching & Privacy Anchors... ";

        ob_start();
        $this->plugin->admin->render_go_pro_page();
        $html = ob_get_clean();

        assert(str_contains($html, 'id="pro-feature-caching"'), 'Anchor #pro-feature-caching must exist');
        assert(str_contains($html, 'id="pro-feature-log-images"'), 'Anchor #pro-feature-log-images must exist');
        assert(str_contains($html, 'id="pro-feature-retention"'), 'Anchor #pro-feature-retention must exist');
        assert(str_contains($html, 'Strict 30 Minutes Max'), 'Comparison table must include 30-minute free limit');

        echo "PASSED\n";
    }

    private function test_em_dash_guardrail(): void {
        echo "- Testing Em Dash Guardrail... ";

        $files = [
            dirname(__DIR__) . '/includes/class-fito-tryon-cache.php',
            dirname(__DIR__) . '/includes/class-fito-tryon-database.php',
            dirname(__DIR__) . '/includes/class-fito-tryon-privacy.php',
            dirname(__DIR__) . '/admin/class-fito-tryon-admin.php',
            __FILE__,
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            assert(!str_contains($content, "\xE2\x80\x94"), "File {$file} must not contain em dashes");
        }

        echo "PASSED\n";
    }

    private function get_log_by_id(int $id): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'fito_tryon_logs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    private function cleanup(): void {
        global $wpdb;
        if (!empty($this->created_log_ids)) {
            $table = $wpdb->prefix . 'fito_tryon_logs';
            $ids = implode(',', array_map('absint', $this->created_log_ids));
            $wpdb->query("DELETE FROM {$table} WHERE id IN ({$ids})");
        }
        if (!empty($this->created_job_ids)) {
            $table = $wpdb->prefix . 'fito_tryon_jobs';
            $ids = implode(',', array_map('absint', $this->created_job_ids));
            $wpdb->query("DELETE FROM {$table} WHERE id IN ({$ids})");
        }
    }
}

$tester = new Fito_Privacy_Pro_Gating_Test();
$tester->run_all();
