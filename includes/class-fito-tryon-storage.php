<?php
/**
 * Storage manager for Fito Try-On with AI.
 */

defined('ABSPATH') || exit;

class Fito_Tryon_Storage {
    public const UPLOAD_DIR_NAME = 'fito-tryon';

    public static function get_base_dir(): string {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . self::UPLOAD_DIR_NAME;
    }

    public static function get_base_url(): string {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . self::UPLOAD_DIR_NAME;
    }

    public static function setup_storage_directory(): void {
        $base = self::get_base_dir();
        $subdirs = ['uploads', 'results'];

        if (!file_exists($base)) {
            wp_mkdir_p($base);
        }

        foreach ($subdirs as $subdir) {
            $path = trailingslashit($base) . $subdir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }

        // Write protective .htaccess and index.php
        $htaccess_file = trailingslashit($base) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Fito Try-On Security\nOptions -Indexes\n<FilesMatch \"\.(php|php5|phtml|phar|pl|py|cgi)$\">\nDeny from all\n</FilesMatch>\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        $index_file = trailingslashit($base) . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php // Silence is golden.\n");
        }
    }

    public function handle_upload(array $file): array {
        self::setup_storage_directory();

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => __('No valid uploaded file found.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $max_size = (int) get_option('fito_tryon_max_upload_size', 10) * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return [
                'success' => false,
                'message' => sprintf(
                    // translators: %d: Maximum allowed upload size in megabytes
                    __('Image size exceeds maximum limit of %d MB.', 'fito-try-on-with-ai-for-woocommerce'),
                    (int) get_option('fito_tryon_max_upload_size', 10)
                ),
            ];
        }

        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return ['success' => false, 'message' => __('File is not a valid or recognizable image.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $mime_type = $image_info['mime'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes, true)) {
            return ['success' => false, 'message' => __('Only JPG, PNG, and WebP images are supported.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        if ($image_info[0] < 150 || $image_info[1] < 150) {
            return ['success' => false, 'message' => __('Image dimensions too small (minimum 150x150 pixels required).', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $ext = 'jpg';
        if ($mime_type === 'image/png') {
            $ext = 'png';
        } elseif ($mime_type === 'image/webp') {
            $ext = 'webp';
        }

        $filename = 'user_' . wp_generate_password(24, false) . '.' . $ext;
        $dest_path = trailingslashit(self::get_base_dir()) . 'uploads/' . $filename;
        $dest_url  = trailingslashit(self::get_base_url()) . 'uploads/' . $filename;

        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Direct file upload validation with strict is_uploaded_file and mime inspection used in custom media storage endpoint.
        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            return ['success' => false, 'message' => __('Failed to save uploaded photo to storage.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        return [
            'success' => true,
            'path'    => $dest_path,
            'url'     => $dest_url,
            'mime'    => $mime_type,
            'width'   => $image_info[0],
            'height'  => $image_info[1],
        ];
    }

    public function handle_upload_base64(string $base64_data): array {
        self::setup_storage_directory();

        if (str_starts_with($base64_data, 'data:image')) {
            $parts = explode(',', $base64_data);
            $binary = base64_decode(end($parts), true);
        } else {
            $binary = base64_decode($base64_data, true);
        }

        if (!$binary) {
            return ['success' => false, 'message' => __('Invalid base64 image data received.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $max_size = (int) get_option('fito_tryon_max_upload_size', 10) * 1024 * 1024;
        if (strlen($binary) > $max_size) {
            return [
                'success' => false,
                'message' => sprintf(
                    // translators: %d: Maximum allowed upload size in megabytes
                    __('Image size exceeds maximum limit of %d MB.', 'fito-try-on-with-ai-for-woocommerce'),
                    (int) get_option('fito_tryon_max_upload_size', 10)
                ),
            ];
        }

        $image_info = @getimagesizefromstring($binary);
        if ($image_info === false) {
            return ['success' => false, 'message' => __('Uploaded data is not a valid or recognizable image.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $mime_type = $image_info['mime'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes, true)) {
            return ['success' => false, 'message' => __('Only JPG, PNG, and WebP images are supported.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        if ($image_info[0] < 150 || $image_info[1] < 150) {
            return ['success' => false, 'message' => __('Image dimensions too small (minimum 150x150 pixels required).', 'fito-try-on-with-ai-for-woocommerce')];
        }

        $ext = 'jpg';
        if ($mime_type === 'image/png') {
            $ext = 'png';
        } elseif ($mime_type === 'image/webp') {
            $ext = 'webp';
        }

        $filename = 'user_' . wp_generate_password(24, false) . '.' . $ext;
        $dest_path = trailingslashit(self::get_base_dir()) . 'uploads/' . $filename;
        $dest_url  = trailingslashit(self::get_base_url()) . 'uploads/' . $filename;

        if (file_put_contents($dest_path, $binary) === false) {
            return ['success' => false, 'message' => __('Failed to save uploaded photo to storage.', 'fito-try-on-with-ai-for-woocommerce')];
        }

        return [
            'success' => true,
            'path'    => $dest_path,
            'url'     => $dest_url,
            'mime'    => $mime_type,
            'width'   => $image_info[0],
            'height'  => $image_info[1],
        ];
    }

    public function save_result_image(string $data_or_url): array {
        self::setup_storage_directory();

        $filename = 'tryon_' . wp_generate_password(24, false) . '.jpg';
        $dest_path = trailingslashit(self::get_base_dir()) . 'results/' . $filename;
        $dest_url  = trailingslashit(self::get_base_url()) . 'results/' . $filename;

        if (str_starts_with($data_or_url, 'data:image')) {
            $parts = explode(',', $data_or_url);
            $binary = base64_decode(end($parts), true);
            if (!$binary) {
                return ['success' => false, 'message' => __('Invalid base64 image data received.', 'fito-try-on-with-ai-for-woocommerce')];
            }
            file_put_contents($dest_path, $binary);
        } elseif (str_starts_with($data_or_url, 'http://') || str_starts_with($data_or_url, 'https://')) {
            $sslverify = (bool) apply_filters('fito_tryon_sslverify', !in_array(wp_get_environment_type(), ['local', 'development'], true));
            $response = wp_remote_get($data_or_url, ['timeout' => 30, 'sslverify' => $sslverify]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return ['success' => false, 'message' => __('Failed to download generated try-on image from provider.', 'fito-try-on-with-ai-for-woocommerce')];
            }
            file_put_contents($dest_path, wp_remote_retrieve_body($response));
        } elseif (file_exists($data_or_url)) {
            copy($data_or_url, $dest_path);
        } else {
            $binary = base64_decode($data_or_url, true);
            if ($binary && strlen($binary) > 100) {
                file_put_contents($dest_path, $binary);
            } else {
                return ['success' => false, 'message' => __('Unrecognized image format received.', 'fito-try-on-with-ai-for-woocommerce')];
            }
        }

        // Fire action hook allowing extensions (e.g. Pro watermark overlay) to process saved image.
        do_action('fito_tryon_after_result_image_saved', $dest_path);

        return [
            'success' => true,
            'path'    => $dest_path,
            'url'     => $dest_url,
        ];
    }

    public function delete_file(?string $path): bool {
        if (!empty($path) && file_exists($path) && str_starts_with(realpath($path) ?: '', realpath(self::get_base_dir()) ?: '')) {
            return (bool) wp_delete_file($path);
        }
        return false;
    }

    public function delete_file_by_url(?string $url): bool {
        if (empty($url)) {
            return false;
        }
        $base_url = self::get_base_url();
        if (!str_starts_with($url, $base_url)) {
            return false;
        }
        $rel_path = ltrim(substr($url, strlen($base_url)), '/\\');
        $full_path = trailingslashit(self::get_base_dir()) . $rel_path;
        return $this->delete_file($full_path);
    }

    public function purge_all(): int {
        $base = self::get_base_dir();
        $count = 0;
        foreach (['uploads', 'results'] as $subdir) {
            $dir = trailingslashit($base) . $subdir;
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file) && basename($file) !== 'index.php') {
                            if (wp_delete_file($file)) {
                                $count++;
                            }
                        }
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Convert local file to base64 data URI string.
     *
     * @param string $path
     * @return string
     */
    public static function image_to_base64(string $path): string {
        if (!file_exists($path)) {
            return '';
        }
        $mime = 'image/jpeg';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if ($detected) {
                $mime = $detected;
            }
        } elseif (function_exists('wp_check_filetype')) {
            $ft = wp_check_filetype($path);
            if (!empty($ft['type'])) {
                $mime = $ft['type'];
            }
        }
        $data = file_get_contents($path);
        if ($data === false) {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    /**
     * Resolve image path, URL, or attachment ID into a base64 data URI string.
     *
     * @param string $path_or_url
     * @param int $attachment_id
     * @return string
     */
    public static function resolve_image_base64(string $path_or_url, int $attachment_id = 0): string {
        // 1. Try attachment ID
        if ($attachment_id > 0) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                return self::image_to_base64($file);
            }
        }

        // 2. Try direct local file path
        if (!empty($path_or_url) && file_exists($path_or_url)) {
            return self::image_to_base64($path_or_url);
        }

        // 3. Try mapping from WordPress uploads URL to local filesystem path
        if (!empty($path_or_url) && str_starts_with($path_or_url, 'http')) {
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['baseurl']) && str_starts_with($path_or_url, $upload_dir['baseurl'])) {
                $mapped = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $path_or_url);
                if (file_exists($mapped)) {
                    return self::image_to_base64($mapped);
                }
            }

            // 4. Validate URL against SSRF before remote fetching
            $validated_url = wp_http_validate_url($path_or_url);
            if ($validated_url) {
                $host = wp_parse_url($validated_url, PHP_URL_HOST);
                $is_private_ip = false;
                if ($host && filter_var($host, FILTER_VALIDATE_IP)) {
                    $is_private_ip = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                }
                if ($host === 'localhost' && !in_array(wp_get_environment_type(), ['local', 'development'], true)) {
                    $is_private_ip = true;
                }

                if (!$is_private_ip) {
                    $sslverify = (bool) apply_filters('fito_tryon_sslverify', !in_array(wp_get_environment_type(), ['local', 'development'], true));
                    $resp = wp_remote_get($validated_url, ['timeout' => 15, 'sslverify' => $sslverify]);
                    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                        $bytes = wp_remote_retrieve_body($resp);
                        $content_type = wp_remote_retrieve_header($resp, 'content-type') ?: 'image/jpeg';
                        if (str_contains($content_type, ';')) {
                            $content_type = trim(explode(';', $content_type)[0]);
                        }
                        if (str_starts_with($content_type, 'image/')) {
                            return 'data:' . $content_type . ';base64,' . base64_encode($bytes);
                        }
                    }
                }
            }
        }

        // 5. Already a data URL
        if (str_starts_with($path_or_url, 'data:image')) {
            return $path_or_url;
        }

        return '';
    }
}
