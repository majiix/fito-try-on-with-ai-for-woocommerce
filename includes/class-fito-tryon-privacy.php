<?php
/**
 * Privacy, data retention, and automatic cleanup.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Privacy {
    public const CRON_HOOK = 'fito_tryon_cleanup_cron';

    public function __construct() {
        add_action(self::CRON_HOOK, [$this, 'run_cleanup']);
        add_action('admin_init', [$this, 'add_privacy_policy_content']);
    }

    public static function schedule_cleanup_cron(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'twicedaily', self::CRON_HOOK);
        }
    }

    public static function clear_cleanup_cron(): void {
        wp_unschedule_hook(self::CRON_HOOK);
    }

    public function run_cleanup(): void {
        global $wpdb;
        $db = Fito_Tryon::instance()->db;
        $storage = Fito_Tryon::instance()->storage;

        // 1. Clean up temporary try-on jobs & customer photos
        $retention_hours = (int) apply_filters('fito_tryon_retention_hours', (int) get_option('fito_tryon_retention_hours', 24));
        $retention_minutes = max(1, $retention_hours * 60);

        if ($retention_minutes > 0) {
            $expired = $db->get_expired_jobs($retention_minutes);
            $job_ids_to_delete = [];

            foreach ($expired as $job) {
                if (!empty($job->user_image_path)) {
                    $storage->delete_file($job->user_image_path);
                }
                if (!empty($job->result_image_path)) {
                    $storage->delete_file($job->result_image_path);
                }

                $job_ids_to_delete[] = (int) $job->id;
            }

            if (!empty($job_ids_to_delete)) {
                $db->delete_expired_jobs($job_ids_to_delete);
            }
        }

        // 2. Clean up expired audit logs and associated stored images
        $log_retention = get_option('fito_tryon_log_retention', '30');
        if ($log_retention !== 'never' && is_numeric($log_retention) && (int) $log_retention > 0) {
            $days = (int) $log_retention;
            $expired_logs = $db->get_expired_logs($days);
            foreach ($expired_logs as $log_row) {
                if (!empty($log_row->user_image_url)) {
                    $storage->delete_file_by_url($log_row->user_image_url);
                }
                if (!empty($log_row->result_image_url)) {
                    $storage->delete_file_by_url($log_row->result_image_url);
                }
            }
            $db->delete_expired_logs($days);
        }
    }

    public function purge_all_temporary_photos(): int {
        $storage = Fito_Tryon::instance()->storage;
        $deleted_files = $storage->purge_all();

        // Remove unsaved jobs
        Fito_Tryon::instance()->db->delete_unsaved_jobs();

        return $deleted_files;
    }

    public function add_privacy_policy_content(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = sprintf(
            '<h2>%s</h2><p>%s</p><p>%s</p>',
            esc_html__('AI Virtual Try-On', 'fito-try-on-with-ai-for-woocommerce'),
            esc_html__('When you use our virtual try-on feature, your uploaded or camera-captured photo is temporarily transmitted to our AI processing service provider solely to render the preview.', 'fito-try-on-with-ai-for-woocommerce'),
            esc_html__('Customer photos are automatically deleted from server storage within 24 hours unless you explicitly save the try-on result to your customer account. Photos are never used to train third-party public models.', 'fito-try-on-with-ai-for-woocommerce')
        );

        wp_add_privacy_policy_content(
            __('Fito Try-On with AI for WooCommerce', 'fito-try-on-with-ai-for-woocommerce'),
            wp_kses_post($content)
        );
    }

    public static function get_consent_text(): string {
        $custom = get_option('fito_tryon_consent_text', '');
        if (!empty($custom)) {
            return $custom;
        }
        return __('I consent to my photo being processed by AI to generate a virtual try-on preview.', 'fito-try-on-with-ai-for-woocommerce');
    }
}
