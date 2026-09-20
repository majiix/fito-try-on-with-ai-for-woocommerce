<?php
/**
 * Rate limiter for virtual try-on requests.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Rate_Limiter {
    public function get_client_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        // Only trust proxy headers if Cloudflare or explicit reverse proxy configuration is active
        $has_cf = !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
        $trust_proxies = apply_filters('fito_tryon_trust_proxy_headers', $has_cf);
        if ($trust_proxies) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $raw_xff = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
                $parts = explode(',', $raw_xff);
                $ip = trim($parts[0]);
            }
        }

        $sanitized_ip = sanitize_text_field($ip);
        return filter_var($sanitized_ip, FILTER_VALIDATE_IP) ? $sanitized_ip : '127.0.0.1';
    }

    public function get_device_id($request = null): string {
        $raw_id = '';
        if ($request instanceof WP_REST_Request) {
            $raw_id = (string) ($request->get_header('X-Fito-Device-ID') ?: $request->get_param('device_id'));
        }
        if (empty($raw_id) && !empty($_COOKIE['fito_device_uuid'])) {
            $raw_id = sanitize_text_field(wp_unslash($_COOKIE['fito_device_uuid']));
        }
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $raw_id);
        return substr((string) $sanitized, 0, 64);
    }

    public function check_limit(int $user_id = 0, string $device_id = ''): array {
        $enabled = get_option('fito_tryon_rate_limit_enabled', 'yes') === 'yes';
        if (!$enabled) {
            return ['allowed' => true, 'remaining' => 999, 'retry_after' => 0];
        }

        $max_count = (int) get_option('fito_tryon_rate_limit_count', get_option('fito_tryon_rate_limit_hourly', 10000));
        $period = get_option('fito_tryon_rate_limit_period', 'hour');
        if (!in_array($period, ['minute', 'hour', 'day'], true)) {
            $period = 'hour';
        }

        $period_seconds = $this->get_period_seconds($period);
        $access_level = get_option('fito_tryon_access_level', 'everyone');

        // Mode 1: Logged-in user account
        if ($access_level === 'logged_in_only' || $user_id > 0) {
            $key = $this->get_user_transient_key($user_id, $period);
            $current = (int) get_transient($key);

            if ($current >= $max_count) {
                return [
                    'allowed'     => false,
                    'remaining'   => 0,
                    'retry_after' => $period_seconds,
                    'message'     => $this->get_rate_limit_message($max_count, $period, 'user'),
                ];
            }

            return [
                'allowed'     => true,
                'remaining'   => max(0, $max_count - $current),
                'retry_after' => 0,
            ];
        }

        // Mode 2: Guest device cookie & IP safety cap
        $dev_key = $this->get_device_transient_key($device_id, $period);
        $dev_current = (int) get_transient($dev_key);

        if ($dev_current >= $max_count) {
            return [
                'allowed'     => false,
                'remaining'   => 0,
                'retry_after' => $period_seconds,
                'message'     => $this->get_rate_limit_message($max_count, $period, 'device'),
            ];
        }

        // Overall network IP safety ceiling (allows shared household WiFi up to 3x device quota or min 15)
        $ip_key = $this->get_ip_transient_key($period);
        $ip_current = (int) get_transient($ip_key);
        $ip_ceiling = max(15, $max_count * 3);

        if ($ip_current >= $ip_ceiling) {
            return [
                'allowed'     => false,
                'remaining'   => 0,
                'retry_after' => $period_seconds,
                'message'     => __('Network try-on limit reached. Please try again later or sign in to continue.', 'fito-try-on-with-ai-for-woocommerce'),
            ];
        }

        return [
            'allowed'     => true,
            'remaining'   => max(0, $max_count - $dev_current),
            'retry_after' => 0,
        ];
    }

    public function increment_limit(int $user_id = 0, string $device_id = ''): void {
        $period = get_option('fito_tryon_rate_limit_period', 'hour');
        if (!in_array($period, ['minute', 'hour', 'day'], true)) {
            $period = 'hour';
        }
        $seconds = $this->get_period_seconds($period);
        $access_level = get_option('fito_tryon_access_level', 'everyone');

        if ($access_level === 'logged_in_only' || $user_id > 0) {
            $key = $this->get_user_transient_key($user_id, $period);
            $current = (int) get_transient($key);
            set_transient($key, $current + 1, $seconds);
        } else {
            // Increment per-device allowance
            $dev_key = $this->get_device_transient_key($device_id, $period);
            $dev_current = (int) get_transient($dev_key);
            set_transient($dev_key, $dev_current + 1, $seconds);

            // Increment shared IP safety ceiling
            $ip_key = $this->get_ip_transient_key($period);
            $ip_current = (int) get_transient($ip_key);
            set_transient($ip_key, $ip_current + 1, $seconds);
        }
    }

    public function get_period_seconds(string $period): int {
        return match ($period) {
            'minute' => MINUTE_IN_SECONDS,
            'day'    => DAY_IN_SECONDS,
            default  => HOUR_IN_SECONDS,
        };
    }

    private function get_rate_limit_message(int $max_count, string $period, string $type = 'device'): string {
        $target = $type === 'user'
            ? __('per user account', 'fito-try-on-with-ai-for-woocommerce')
            : __('per browser device', 'fito-try-on-with-ai-for-woocommerce');

        return match ($period) {
            'minute' => sprintf(
                // translators: 1: Maximum number of try-ons, 2: Rate limit scope description
                __('Rate limit exceeded: maximum %1$d try-ons per minute reached %2$s. Please try again in a moment.', 'fito-try-on-with-ai-for-woocommerce'),
                $max_count,
                $target
            ),
            'day'    => sprintf(
                // translators: 1: Maximum number of try-ons, 2: Rate limit scope description
                __('Rate limit exceeded: maximum %1$d try-ons per day reached %2$s. Please try again tomorrow.', 'fito-try-on-with-ai-for-woocommerce'),
                $max_count,
                $target
            ),
            default  => sprintf(
                // translators: 1: Maximum number of try-ons, 2: Rate limit scope description
                __('Rate limit exceeded: maximum %1$d try-ons per hour reached %2$s. Please try again later.', 'fito-try-on-with-ai-for-woocommerce'),
                $max_count,
                $target
            ),
        };
    }

    public function get_user_transient_key(int $user_id, string $period = 'hour'): string {
        return 'fito_tryon_rl_' . substr($period, 0, 1) . '_u_' . $user_id;
    }

    public function get_device_transient_key(string $device_id, string $period = 'hour'): string {
        $id = !empty($device_id) ? $device_id : $this->get_client_ip();
        return 'fito_tryon_rl_' . substr($period, 0, 1) . '_dev_' . md5($id);
    }

    public function get_ip_transient_key(string $period = 'hour'): string {
        return 'fito_tryon_rl_' . substr($period, 0, 1) . '_ip_' . md5($this->get_client_ip());
    }
}
