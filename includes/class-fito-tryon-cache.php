<?php
/**
 * Result caching for identical photo and product combinations.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Cache {
    public function is_enabled(): bool {
        if (!Fito_Tryon::is_pro_active()) {
            return false;
        }
        return get_option('fito_tryon_cache_enabled', 'yes') === 'yes';
    }

    public function get_cache_key(string $image_path, int $product_id, int $variation_id, string $model): string {
        $file_hash = file_exists($image_path) ? md5_file($image_path) : md5($image_path);
        if (!Fito_Tryon::is_pro_active()) {
            $wm_fingerprint = 'free_default_wm';
        } else {
            $wm_enabled = get_option('fito_tryon_watermark_enabled', 'no') === 'yes';
            $wm_fingerprint = 'nowm';
            if ($wm_enabled) {
                $wm_fingerprint = implode(':', [
                    (int) get_option('fito_tryon_watermark_image_id', 0),
                    (string) get_option('fito_tryon_watermark_text', ''),
                    (string) get_option('fito_tryon_watermark_pos', 'bottom_right'),
                    (int) get_option('fito_tryon_watermark_opacity', 80),
                ]);
            }
        }
        return 'fito_res_' . md5($file_hash . '_' . $product_id . '_' . $variation_id . '_' . $model . '_' . $wm_fingerprint);
    }

    public function get(string $image_path, int $product_id, int $variation_id, string $model): ?string {
        if (!$this->is_enabled()) {
            return null;
        }

        $key = $this->get_cache_key($image_path, $product_id, $variation_id, $model);
        $cached_url = get_transient($key);

        return $cached_url && is_string($cached_url) ? $cached_url : null;
    }

    public function set(string $image_path, int $product_id, int $variation_id, string $model, string $result_url): void {
        if (!$this->is_enabled() || empty($result_url)) {
            return;
        }

        $hours = (int) get_option('fito_tryon_cache_hours', 72);
        $key = $this->get_cache_key($image_path, $product_id, $variation_id, $model);
        set_transient($key, $result_url, $hours * HOUR_IN_SECONDS);
    }

    public function flush(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch flush of custom cache transients.
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_fito_res_') . '%',
                $wpdb->esc_like('_transient_timeout_fito_res_') . '%'
            )
        );

        return $deleted !== false ? (int) $deleted : 0;
    }
}
