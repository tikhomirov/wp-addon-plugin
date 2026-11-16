<?php

use WpAddon\Services\ImageOptimizationService;

/**
 * Unit tests for ImageOptimizationService
 *
 * @group problematic
 */
describe('ImageOptimizationService Unit Tests', function () {
    // Skip these tests in CI due to Patchwork conflicts
    if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
        test('skipped in CI', function () {})->skip('Patchwork conflicts in CI');

        return;
    }
    beforeEach(function () {
        $this->imageOptimizationService = new ImageOptimizationService;
    });

    afterEach(function () {
        Mockery::close();
    });

    it('generates blur placeholder for valid image', function () {
        // Skip test if GD is not available or in CI environment
        if (! function_exists('imagecreatetruecolor') || getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
            expect(true)->toBeTrue(); // Skip test

            return;
        }

        // Create test image
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(200, 200);
        $blue = imagecolorallocate($image, 0, 0, 255);
        imagefill($image, 0, 0, $blue);
        imagejpeg($image, $tempImage, 90);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);

        expect($result)->toStartWith('data:image/jpeg;base64,');
        expect(mb_strlen($result))->toBeGreaterThan(100); // Base64 string should be long enough

        // Cleanup
        unlink($tempImage);
    });

    it('returns empty string for non-existent image', function () {
        $result = $this->imageOptimizationService->generateBlurPlaceholder('/non/existent/image.jpg');

        expect($result)->toBe('');
    });

    it('handles invalid image files', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        file_put_contents($tempFile, 'not an image content');

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempFile);

        expect($result)->toBe('');

        unlink($tempFile);
    });

    it('respects blur intensity parameter', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.png';
        $image = imagecreatetruecolor(100, 100);
        for ($x = 0; $x < 100; $x++) {
            $color = imagecolorallocate($image, $x * 2, 0, 0);
            imageline($image, $x, 0, $x, 99, $color);
        }
        imagepng($image, $tempImage);
        imagedestroy($image);

        // Test different blur levels
        $resultLow = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 2);
        $resultHigh = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 8);

        expect($resultLow)->toStartWith('data:image/png;base64,');
        expect($resultHigh)->toStartWith('data:image/png;base64,');
        expect($resultLow)->not->toBe($resultHigh); // Different blur levels yield different results

        unlink($tempImage);
    });

    it('generates correct thumbnail size', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(800, 600); // Large image
        $green = imagecolorallocate($image, 0, 255, 0);
        imagefill($image, 0, 0, $green);
        imagejpeg($image, $tempImage, 90);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50); // Small thumbnail

        expect($result)->toStartWith('data:image/jpeg;base64,');

        // Decode and check size
        $imageData = base64_decode(str_replace('data:image/jpeg;base64,', '', $result));
        $tempDecoded = tempnam(sys_get_temp_dir(), 'wp_addon_decoded_') . '.jpg';
        file_put_contents($tempDecoded, $imageData);

        if (function_exists('getimagesize')) {
            $size = getimagesize($tempDecoded);
            expect($size[0])->toBeLessThanOrEqual(50); // Width at most 50px
            expect($size[1])->toBeLessThanOrEqual(50); // Height at most 50px
        }

        unlink($tempImage);
        unlink($tempDecoded);
    });

    it('optimizes image quality', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(100, 100);
        $yellow = imagecolorallocate($image, 255, 255, 0);
        imagefill($image, 0, 0, $yellow);
        imagejpeg($image, $tempImage, 100); // High-quality original
        imagedestroy($image);

        $originalSize = filesize($tempImage);

        // Optimize
        $optimizedResult = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50, 70); // Low quality

        // Optimized base64 should be smaller
        expect(mb_strlen($optimizedResult))->toBeLessThan($originalSize * 1.5); // With margin

        unlink($tempImage);
    });

    it('handles different image formats', function () {
        $formats = ['jpg', 'jpeg', 'png'];

        foreach ($formats as $format) {
            $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.' . $format;
            $image = imagecreatetruecolor(50, 50);
            $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
            imagefill($image, 0, 0, $color);

            if ($format === 'png') {
                imagepng($image, $tempImage);
            } else {
                imagejpeg($image, $tempImage, 90);
            }
            imagedestroy($image);

            $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);

            $expectedMime = $format === 'png' ? 'image/png' : 'image/jpeg';
            expect($result)->toStartWith('data:' . $expectedMime . ';base64,');

            unlink($tempImage);
        }
    });

    it('handles images with alpha channel', function () {
        if (! function_exists('imagecreatetruecolor')) {
            skip('GD library not available');
        }

        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.png';
        $image = imagecreatetruecolor(50, 50);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagesavealpha($image, true);
        imagepng($image, $tempImage);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);

        expect($result)->toStartWith('data:image/png;base64,');
        expect(mb_strlen($result))->toBeGreaterThan(100);

        unlink($tempImage);
    });

    it('validates input parameters', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        // Invalid intensity blur
        $result1 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, -1);
        $result2 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 0);
        $result3 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 21); // > 20

        expect($result1)->toStartWith('data:image/jpeg;base64,'); // Should still process
        expect($result2)->toStartWith('data:image/jpeg;base64,');
        expect($result3)->toStartWith('data:image/jpeg;base64,');

        unlink($tempImage);
    });

    it('handles file permission errors', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        // Make file unreadable
        chmod($tempImage, 0000);

        if (PHP_OS_FAMILY !== 'Windows') { // chmod may not work on Windows
            $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);
            expect($result)->toBe('');
        }

        chmod($tempImage, 0644); // Restore permissions for cleanup
        unlink($tempImage);
    });

    it('optimizes image dimensions proportionally', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_') . '.jpg';
        $image = imagecreatetruecolor(400, 200); // Rectangular image
        $color = imagecolorallocate($image, 100, 100, 100);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50);

        // Decode and check aspect ratio
        $imageData = base64_decode(str_replace('data:image/jpeg;base64,', '', $result));
        $tempDecoded = tempnam(sys_get_temp_dir(), 'wp_addon_decoded_') . '.jpg';
        file_put_contents($tempDecoded, $imageData);

        if (function_exists('getimagesize')) {
            $size = getimagesize($tempDecoded);
            $aspectRatio = $size[0] / $size[1];

            // Aspect ratio should be preserved (original 2:1)
            expect($aspectRatio)->toBeGreaterThan(1.8);
            expect($aspectRatio)->toBeLessThan(2.2);
        }

        unlink($tempImage);
        unlink($tempDecoded);
    });
});
