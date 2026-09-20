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

        // Apply watermark if configured
        $this->apply_watermark_if_enabled($dest_path);

        return [
            'success' => true,
            'path'    => $dest_path,
            'url'     => $dest_url,
        ];
    }

    public function apply_watermark_if_enabled(string $image_path, ?array $custom_options = null): bool {
        if (!file_exists($image_path) || !extension_loaded('gd')) {
            return false;
        }

        $is_pro = Fito_Tryon::is_pro_active();

        if ($custom_options === null) {
            $enabled = (get_option('fito_tryon_watermark_enabled', 'no') === 'yes');
            if (!$enabled) {
                return false;
            }

            if (!$is_pro) {
                // Free tier opt-in: Default branding watermark
                $watermark_id   = 0;
                $watermark_text = 'Made with Fito Try-On with AI for WooCommerce';
                $position       = 'bottom_center';
                $opacity        = 80;
            } else {
                // Pro tier: Optional custom watermark
                $watermark_id   = (int) get_option('fito_tryon_watermark_image_id', 0);
                $watermark_text = trim((string) get_option('fito_tryon_watermark_text', ''));
                $position       = (string) get_option('fito_tryon_watermark_pos', 'bottom_right');
                $opacity        = max(10, min(100, (int) get_option('fito_tryon_watermark_opacity', 80)));

                if (!$watermark_id && empty($watermark_text)) {
                    return false;
                }
            }
        } else {
            // Explicit options provided (e.g. preview overlay generation or unit testing)
            $enabled = !empty($custom_options['enabled']);
            if (!$enabled) {
                return false;
            }

            $watermark_id   = (int) ($custom_options['watermark_image_id'] ?? 0);
            $watermark_text = trim((string) ($custom_options['watermark_text'] ?? ''));
            $position       = (string) ($custom_options['watermark_pos'] ?? 'bottom_right');
            $opacity        = max(10, min(100, (int) ($custom_options['watermark_opacity'] ?? 80)));

            if (!$watermark_id && empty($watermark_text)) {
                return false;
            }
        }

        $raw_content = (string) file_get_contents($image_path);
        if (empty($raw_content)) {
            return false;
        }

        $main_img = @imagecreatefromstring($raw_content);
        if (!$main_img) {
            return false;
        }

        $main_w = imagesx($main_img);
        $main_h = imagesy($main_img);
        $modified = false;

        $wm_img   = null;
        $target_w = 0;
        $target_h = 0;
        $orig_w   = 0;
        $orig_h   = 0;

        if ($watermark_id > 0) {
            $wm_raw = '';
            $wm_path = get_attached_file($watermark_id);
            if ($wm_path && file_exists($wm_path)) {
                $wm_raw = (string) @file_get_contents($wm_path);
            }
            if (empty($wm_raw)) {
                $wm_url = wp_get_attachment_url($watermark_id);
                if ($wm_url) {
                    $sslverify = (bool) apply_filters('fito_tryon_sslverify', !in_array(wp_get_environment_type(), ['local', 'development'], true));
                    $resp = wp_remote_get($wm_url, ['timeout' => 10, 'sslverify' => $sslverify]);
                    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                        $wm_raw = (string) wp_remote_retrieve_body($resp);
                    }
                }
            }
            if (!empty($wm_raw)) {
                $wm_loaded = @imagecreatefromstring($wm_raw);
                if ($wm_loaded) {
                    $wm_img   = $wm_loaded;
                    $orig_w   = imagesx($wm_img);
                    $orig_h   = imagesy($wm_img);
                    $target_w = min((int) ($main_w * 0.22), max(60, $orig_w));
                    $target_h = (int) ($orig_h * ($target_w / max(1, $orig_w)));
                }
            }
        }

        $has_image = ($wm_img !== null);
        $has_text  = ($watermark_text !== '');

        if ($has_image && $has_text) {
            if (str_starts_with($position, 'top')) {
                [$dest_x, $dest_y] = $this->calculate_position($main_w, $main_h, $target_w, $target_h, $position, 20);
                $this->merge_image_watermark($main_img, $wm_img, $dest_x, $dest_y, $target_w, $target_h, $orig_w, $orig_h, $opacity);
                $this->apply_text_watermark($main_img, $main_w, $main_h, $watermark_text, $position, $opacity, $target_h + 8);
            } else {
                $this->apply_text_watermark($main_img, $main_w, $main_h, $watermark_text, $position, $opacity, 0);
                $badge_est_h = 36;
                [$dest_x, $dest_y] = $this->calculate_position($main_w, $main_h, $target_w, $target_h, $position, 20);
                $dest_y = max(0, $dest_y - $badge_est_h - 8);
                $this->merge_image_watermark($main_img, $wm_img, $dest_x, $dest_y, $target_w, $target_h, $orig_w, $orig_h, $opacity);
            }
            $modified = true;
        } elseif ($has_image) {
            [$dest_x, $dest_y] = $this->calculate_position($main_w, $main_h, $target_w, $target_h, $position, 20);
            $this->merge_image_watermark($main_img, $wm_img, $dest_x, $dest_y, $target_w, $target_h, $orig_w, $orig_h, $opacity);
            $modified = true;
        } elseif ($has_text) {
            $this->apply_text_watermark($main_img, $main_w, $main_h, $watermark_text, $position, $opacity, 0);
            $modified = true;
        }

        if ($wm_img) {
            imagedestroy($wm_img);
        }

        if ($modified) {
            imagejpeg($main_img, $image_path, 90);
        }

        imagedestroy($main_img);
        return $modified;
    }

    public function generate_watermark_preview(array $options = []): array {
        if (!Fito_Tryon::is_pro_active()) {
            return [
                'success' => false,
                'message' => __('Watermark customization is a Pro feature.', 'fito-try-on-with-ai-for-woocommerce'),
                'path'    => '',
                'url'     => '',
                'applied' => false,
            ];
        }

        self::setup_storage_directory();

        $results_dir = trailingslashit(self::get_base_dir()) . 'results';
        $sample_file = '';
        $existing_results = glob($results_dir . '/tryon_*.jpg');
        if (!empty($existing_results)) {
            $sample_file = end($existing_results);
        }

        $preview_filename = 'watermark_preview_' . wp_generate_password(12, false) . '.jpg';
        $preview_path = $results_dir . '/' . $preview_filename;
        $preview_url  = trailingslashit(self::get_base_url()) . 'results/' . $preview_filename;

        if (!empty($sample_file) && file_exists($sample_file)) {
            copy($sample_file, $preview_path);
        } else {
            $canvas = imagecreatetruecolor(600, 800);
            for ($i = 0; $i < 800; $i++) {
                $r = (int) (30 + (15 - 30) * ($i / 800));
                $g = (int) (41 + (23 - 41) * ($i / 800));
                $b = (int) (59 + (42 - 59) * ($i / 800));
                $line_color = imagecolorallocate($canvas, $r, $g, $b);
                imageline($canvas, 0, $i, 600, $i, $line_color);
            }
            $label_col = imagecolorallocate($canvas, 148, 163, 184);
            imagestring($canvas, 5, 210, 390, 'Sample Try-On Canvas', $label_col);
            imagejpeg($canvas, $preview_path, 90);
            imagedestroy($canvas);
        }

        $applied = $this->apply_watermark_if_enabled($preview_path, $options);

        return [
            'success' => true,
            'applied' => $applied,
            'path'    => $preview_path,
            'url'     => $preview_url,
        ];
    }

    protected function merge_image_watermark($main_img, $wm_img, int $dest_x, int $dest_y, int $target_w, int $target_h, int $orig_w, int $orig_h, int $opacity = 80): void {
        if ($target_w <= 0 || $target_h <= 0 || $orig_w <= 0 || $orig_h <= 0) {
            return;
        }

        $scaled = imagecreatetruecolor($target_w, $target_h);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefilledrectangle($scaled, 0, 0, $target_w, $target_h, $transparent);
        imagealphablending($scaled, true);
        imagecopyresampled($scaled, $wm_img, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h);

        $opacity = max(10, min(100, $opacity));
        $opacity_factor = $opacity / 100.0;

        if ($opacity >= 100) {
            imagealphablending($main_img, true);
            imagecopy($main_img, $scaled, $dest_x, $dest_y, 0, 0, $target_w, $target_h);
        } else {
            imagealphablending($scaled, false);
            for ($x = 0; $x < $target_w; $x++) {
                for ($y = 0; $y < $target_h; $y++) {
                    $rgba = imagecolorat($scaled, $x, $y);
                    $orig_alpha = ($rgba >> 24) & 0x7F;
                    if ($orig_alpha >= 127) {
                        continue;
                    }
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    $new_alpha = 127 - (int) round((127 - $orig_alpha) * $opacity_factor);
                    $new_alpha = max(0, min(127, $new_alpha));
                    $color = imagecolorallocatealpha($scaled, $r, $g, $b, $new_alpha);
                    imagesetpixel($scaled, $x, $y, $color);
                }
            }
            imagealphablending($main_img, true);
            imagecopy($main_img, $scaled, $dest_x, $dest_y, 0, 0, $target_w, $target_h);
        }

        imagedestroy($scaled);
    }

    protected function calculate_position(int $container_w, int $container_h, int $elem_w, int $elem_h, string $position, int $margin = 20): array {
        switch ($position) {
            case 'top_left':
                $x = $margin;
                $y = $margin;
                break;
            case 'top_center':
                $x = (int) max(0, ($container_w - $elem_w) / 2);
                $y = $margin;
                break;
            case 'top_right':
                $x = $container_w - $elem_w - $margin;
                $y = $margin;
                break;
            case 'center':
                $x = (int) max(0, ($container_w - $elem_w) / 2);
                $y = (int) max(0, ($container_h - $elem_h) / 2);
                break;
            case 'bottom_left':
                $x = $margin;
                $y = $container_h - $elem_h - $margin;
                break;
            case 'bottom_center':
                $x = (int) max(0, ($container_w - $elem_w) / 2);
                $y = $container_h - $elem_h - $margin;
                break;
            case 'bottom_right':
            default:
                $x = $container_w - $elem_w - $margin;
                $y = $container_h - $elem_h - $margin;
                break;
        }
        return [max(0, $x), max(0, $y)];
    }

    protected function get_ttf_font_path(): ?string {
        $candidates = [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            'C:/Windows/Fonts/tahoma.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ];

        foreach ($candidates as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        return null;
    }

    protected function apply_text_watermark($main_img, int $main_w, int $main_h, string $text, string $position, int $opacity = 80, int $offset_y = 0): void {
        $font_file = $this->get_ttf_font_path();
        imagealphablending($main_img, true);

        $f = max(0.1, min(1.0, $opacity / 100));
        $bg_alpha = (int) round(127 - (127 - 45) * $f);
        $border_alpha = (int) round(127 - (127 - 100) * $f);
        $text_alpha = (int) round(127 * (1.0 - $f));

        if (function_exists('imagettfbbox') && function_exists('imagettftext') && $font_file) {
            $font_size = max(9, min(16, (int) round($main_w * 0.016)));
            $bbox = @imagettfbbox($font_size, 0, $font_file, $text);
            if ($bbox) {
                $text_w = abs($bbox[4] - $bbox[0]);
                $text_h = abs($bbox[5] - $bbox[1]);
                if (($text_w + 32) > ($main_w * 0.92)) {
                    $font_size = max(8, (int) floor($font_size * (($main_w * 0.90) / max(1, $text_w + 32))));
                    $bbox = @imagettfbbox($font_size, 0, $font_file, $text);
                    if ($bbox) {
                        $text_w = abs($bbox[4] - $bbox[0]);
                        $text_h = abs($bbox[5] - $bbox[1]);
                    }
                }
            } else {
                $text_w = strlen($text) * 8;
                $text_h = 14;
            }

            $pad_x = 14;
            $pad_y = 8;
            $badge_w = $text_w + ($pad_x * 2);
            $badge_h = $text_h + ($pad_y * 2);

            [$rx, $ry] = $this->calculate_position($main_w, $main_h, $badge_w, $badge_h, $position, 20);
            if ($offset_y !== 0) {
                $ry = max(0, min($main_h - $badge_h, $ry + $offset_y));
            }

            $bg_color     = imagecolorallocatealpha($main_img, 15, 23, 42, $bg_alpha);
            $border_color = imagecolorallocatealpha($main_img, 255, 255, 255, $border_alpha);
            $text_color   = imagecolorallocatealpha($main_img, 255, 255, 255, $text_alpha);

            imagefilledrectangle($main_img, $rx, $ry, $rx + $badge_w, $ry + $badge_h, $bg_color);
            imagerectangle($main_img, $rx, $ry, $rx + $badge_w, $ry + $badge_h, $border_color);
            imagettftext($main_img, $font_size, 0, $rx + $pad_x, $ry + $pad_y + $text_h, $text_color, $font_file, $text);
            return;
        }

        $font   = 5;
        $font_w = imagefontwidth($font);
        $font_h = imagefontheight($font);
        $text_w = strlen($text) * $font_w;
        $text_h = $font_h;

        $pad_x = 10;
        $pad_y = 6;
        $badge_w = $text_w + ($pad_x * 2);
        $badge_h = $text_h + ($pad_y * 2);

        [$rx, $ry] = $this->calculate_position($main_w, $main_h, $badge_w, $badge_h, $position, 20);
        if ($offset_y !== 0) {
            $ry = max(0, min($main_h - $badge_h, $ry + $offset_y));
        }

        $bg_color     = imagecolorallocatealpha($main_img, 15, 23, 42, $bg_alpha);
        $border_color = imagecolorallocatealpha($main_img, 255, 255, 255, $border_alpha);
        $text_color   = imagecolorallocatealpha($main_img, 255, 255, 255, $text_alpha);

        imagefilledrectangle($main_img, $rx, $ry, $rx + $badge_w, $ry + $badge_h, $bg_color);
        imagerectangle($main_img, $rx, $ry, $rx + $badge_w, $ry + $badge_h, $border_color);
        imagestring($main_img, $font, $rx + $pad_x, $ry + $pad_y, $text, $text_color);
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
}
