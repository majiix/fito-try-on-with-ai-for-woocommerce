<?php
/**
 * Automated tests for Watermark, Upload Handling, and Cache Invalidation.
 */

// Load WordPress environment
require_once dirname(__DIR__, 4) . '/wp-load.php';

class Fito_Watermark_Test {
    private Fito_Tryon $plugin;
    private array $created_files = [];

    public function __construct() {
        $this->plugin = Fito_Tryon::instance();
    }

    public function run_all(): void {
        echo "=== Running Watermark & Cache Tests ===\n";

        $this->test_free_tier_default_watermark();
        $this->test_pro_tier_watermark_free_clean_output();
        $this->test_pro_tier_custom_watermark();
        $this->test_preview_watermark_rest_endpoint_gating();
        $this->test_cache_key_incorporates_watermark();
        $this->test_cache_flush();
        $this->test_handle_upload_base64_typical();
        $this->test_handle_upload_base64_invalid_failure();
        $this->test_apply_watermark_disabled();
        $this->test_apply_watermark_image_and_text();
        $this->test_generate_watermark_preview();

        $this->cleanup();
        echo "\n[PASS] All Watermark & Cache Tests Completed Successfully!\n";
    }

    private function test_free_tier_default_watermark(): void {
        echo "- Testing Free Tier Opt-In Branding Watermark... ";

        add_filter('fito_tryon_is_pro_active', '__return_false');
        update_option('fito_tryon_watermark_enabled', 'yes');

        $temp_img = __DIR__ . '/temp_test_free_wm.jpg';
        $img = imagecreatetruecolor(400, 500);
        $white = imagecolorallocate($img, 240, 240, 240);
        imagefilledrectangle($img, 0, 0, 400, 500, $white);
        imagejpeg($img, $temp_img, 90);
        imagedestroy($img);
        $this->created_files[] = $temp_img;

        $hash_before = md5_file($temp_img);
        $applied = $this->plugin->storage->apply_watermark_if_enabled($temp_img);

        update_option('fito_tryon_watermark_enabled', 'no');
        remove_filter('fito_tryon_is_pro_active', '__return_false');

        assert($applied === true, 'Free tier should apply default branding watermark when opted in');
        assert(md5_file($temp_img) !== $hash_before, 'Image should be modified with watermark');

        echo "PASSED\n";
    }

    private function test_pro_tier_watermark_free_clean_output(): void {
        echo "- Testing Pro Tier Clean Watermark-Free Output... ";

        add_filter('fito_tryon_is_pro_active', '__return_true');
        update_option('fito_tryon_watermark_enabled', 'no');

        $temp_img = __DIR__ . '/temp_test_pro_clean.jpg';
        $img = imagecreatetruecolor(400, 500);
        $white = imagecolorallocate($img, 240, 240, 240);
        imagefilledrectangle($img, 0, 0, 400, 500, $white);
        imagejpeg($img, $temp_img, 90);
        imagedestroy($img);
        $this->created_files[] = $temp_img;

        $hash_before = md5_file($temp_img);
        $applied = $this->plugin->storage->apply_watermark_if_enabled($temp_img);

        remove_filter('fito_tryon_is_pro_active', '__return_true');

        assert($applied === false, 'Pro tier should not apply watermark when disabled');
        assert(md5_file($temp_img) === $hash_before, 'Image must remain untouched and watermark-free');

        echo "PASSED\n";
    }

    private function test_pro_tier_custom_watermark(): void {
        echo "- Testing Pro Tier Custom Store Watermark... ";

        add_filter('fito_tryon_is_pro_active', '__return_true');
        update_option('fito_tryon_watermark_enabled', 'yes');
        update_option('fito_tryon_watermark_text', 'myboutique.com');
        update_option('fito_tryon_watermark_pos', 'bottom_left');
        update_option('fito_tryon_watermark_opacity', 85);

        $temp_img = __DIR__ . '/temp_test_pro_custom.jpg';
        $img = imagecreatetruecolor(400, 500);
        $white = imagecolorallocate($img, 240, 240, 240);
        imagefilledrectangle($img, 0, 0, 400, 500, $white);
        imagejpeg($img, $temp_img, 90);
        imagedestroy($img);
        $this->created_files[] = $temp_img;

        $hash_before = md5_file($temp_img);
        $applied = $this->plugin->storage->apply_watermark_if_enabled($temp_img);

        remove_filter('fito_tryon_is_pro_active', '__return_true');

        assert($applied === true, 'Pro tier should apply watermark when enabled');
        assert(md5_file($temp_img) !== $hash_before, 'Image should be stamped with custom watermark');

        echo "PASSED\n";
    }

    private function test_preview_watermark_rest_endpoint_gating(): void {
        echo "- Testing /preview-watermark REST Endpoint Gating... ";

        // Free tier attempt
        add_filter('fito_tryon_is_pro_active', '__return_false');
        $req = new WP_REST_Request('POST', '/fito-tryon/v1/preview-watermark');
        $res = $this->plugin->rest->handle_preview_watermark($req);
        remove_filter('fito_tryon_is_pro_active', '__return_false');

        assert($res->get_status() === 403, 'Free tier should receive 403 Forbidden for preview-watermark');

        // Pro tier attempt
        add_filter('fito_tryon_is_pro_active', '__return_true');
        $req = new WP_REST_Request('POST', '/fito-tryon/v1/preview-watermark');
        $req->set_param('enabled', 'yes');
        $req->set_param('watermark_text', 'TestWatermark');
        $res = $this->plugin->rest->handle_preview_watermark($req);
        remove_filter('fito_tryon_is_pro_active', '__return_true');

        assert($res->get_status() === 200, 'Pro tier should successfully generate watermark preview');
        $data = $res->get_data();
        assert(!empty($data['url']), 'Preview URL must be returned for Pro tier');

        echo "PASSED\n";
    }

    private function test_cache_key_incorporates_watermark(): void {
        echo "- Testing Cache Key Variation with Watermark Settings... ";

        $img_path = __FILE__;
        $prod_id  = 123;
        $var_id   = 0;
        $model    = 'test-model';

        // 0. Free tier cache key
        add_filter('fito_tryon_is_pro_active', '__return_false');
        $key_free = $this->plugin->cache->get_cache_key($img_path, $prod_id, $var_id, $model);
        remove_filter('fito_tryon_is_pro_active', '__return_false');

        // Pro tier
        add_filter('fito_tryon_is_pro_active', '__return_true');

        // 1. Watermark disabled
        update_option('fito_tryon_watermark_enabled', 'no');
        $key1 = $this->plugin->cache->get_cache_key($img_path, $prod_id, $var_id, $model);

        // 2. Watermark enabled with text
        update_option('fito_tryon_watermark_enabled', 'yes');
        update_option('fito_tryon_watermark_text', 'www.firststore.com');
        $key2 = $this->plugin->cache->get_cache_key($img_path, $prod_id, $var_id, $model);

        // 3. Watermark text changed
        update_option('fito_tryon_watermark_text', 'www.secondstore.com');
        $key3 = $this->plugin->cache->get_cache_key($img_path, $prod_id, $var_id, $model);

        remove_filter('fito_tryon_is_pro_active', '__return_true');

        assert($key_free !== $key1, 'Free tier key must differ from Pro clean key');
        assert($key1 !== $key2, 'Key should differ when watermark is enabled');
        assert($key2 !== $key3, 'Key should differ when watermark text changes');

        echo "PASSED\n";
    }

    private function test_cache_flush(): void {
        echo "- Testing Cache Flush... ";

        $key = 'fito_res_test_' . wp_generate_password(8, false);
        set_transient($key, 'http://localhost/test.jpg', 3600);
        assert(get_transient($key) === 'http://localhost/test.jpg', 'Transient should be set');

        $flushed = $this->plugin->cache->flush();
        assert($flushed >= 1, 'Flush should delete at least 1 transient');
        assert(get_transient($key) === false, 'Transient should no longer exist after flush');

        echo "PASSED\n";
    }

    private function test_handle_upload_base64_typical(): void {
        echo "- Testing handle_upload_base64 Typical Input... ";

        // Create a 200x200 dummy JPEG in memory
        $img = imagecreatetruecolor(200, 200);
        $blue = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, 200, 200, $blue);
        ob_start();
        imagejpeg($img, null, 90);
        $raw = ob_get_clean();
        imagedestroy($img);

        $b64 = 'data:image/jpeg;base64,' . base64_encode($raw);
        $res = $this->plugin->storage->handle_upload_base64($b64);

        assert($res['success'] === true, 'Upload should succeed');
        assert(file_exists($res['path']), 'File should exist on disk');
        assert(str_contains($res['path'], 'uploads'), 'User upload must be saved in uploads/, not results/');
        $this->created_files[] = $res['path'];

        // Confirm it was NOT watermarked
        $md5_saved = md5_file($res['path']);
        assert($md5_saved === md5($raw), 'User uploaded image should not be modified/watermarked');

        echo "PASSED\n";
    }

    private function test_handle_upload_base64_invalid_failure(): void {
        echo "- Testing handle_upload_base64 Failure Case... ";

        $res = $this->plugin->storage->handle_upload_base64('not_valid_image_content');
        assert($res['success'] === false, 'Invalid base64 should return failure');
        assert(!empty($res['message']), 'Error message should be present');

        echo "PASSED\n";
    }

    private function test_apply_watermark_disabled(): void {
        echo "- Testing apply_watermark When Disabled... ";

        $temp_img = __DIR__ . '/temp_test_disabled.jpg';
        $img = imagecreatetruecolor(300, 300);
        imagejpeg($img, $temp_img, 90);
        imagedestroy($img);
        $this->created_files[] = $temp_img;

        $hash_before = md5_file($temp_img);
        $applied = $this->plugin->storage->apply_watermark_if_enabled($temp_img, [
            'enabled' => false,
            'watermark_text' => 'www.test.com',
        ]);

        assert($applied === false, 'Should return false when disabled');
        assert(md5_file($temp_img) === $hash_before, 'File should not be modified when disabled');

        echo "PASSED\n";
    }

    private function test_apply_watermark_image_and_text(): void {
        echo "- Testing apply_watermark With Image and Text... ";

        $temp_img = __DIR__ . '/temp_test_watermark.jpg';
        $img = imagecreatetruecolor(400, 500);
        imagejpeg($img, $temp_img, 90);
        imagedestroy($img);
        $this->created_files[] = $temp_img;

        $wm_id = (int) get_option('fito_tryon_watermark_image_id', 0);
        $applied = $this->plugin->storage->apply_watermark_if_enabled($temp_img, [
            'enabled'            => true,
            'watermark_image_id' => $wm_id,
            'watermark_text'     => 'www.watermarktest.com',
            'watermark_pos'      => 'bottom_right',
            'watermark_opacity'  => 70,
        ]);

        assert($applied === true, 'Watermark should be applied successfully');
        assert(filesize($temp_img) > 0, 'Watermarked image should be non-empty');

        echo "PASSED\n";
    }

    private function test_generate_watermark_preview(): void {
        echo "- Testing generate_watermark_preview... ";

        $preview = $this->plugin->storage->generate_watermark_preview([
            'enabled'            => true,
            'watermark_text'     => 'LivePreviewTest.com',
            'watermark_pos'      => 'bottom_left',
            'watermark_opacity'  => 85,
        ]);

        assert($preview['success'] === true, 'Preview generation should succeed');
        assert(file_exists($preview['path']), 'Preview file must exist');
        assert(!empty($preview['url']), 'Preview URL must be returned');
        $this->created_files[] = $preview['path'];

        echo "PASSED\n";
    }

    private function cleanup(): void {
        foreach ($this->created_files as $f) {
            if (file_exists($f)) {
                wp_delete_file($f);
            }
        }
    }
}

$tester = new Fito_Watermark_Test();
$tester->run_all();
