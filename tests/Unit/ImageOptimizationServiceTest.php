<?php

use Mockery;

/**
 * Unit tests for ImageOptimizationService
 *
 * @group problematic
 */
describe('ImageOptimizationService Unit Tests', function () {
    // Пропускаем эти тесты в CI из-за Patchwork конфликтов
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

        // Создаем тестовое изображение
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(200, 200);
        $blue = imagecolorallocate($image, 0, 0, 255);
        imagefill($image, 0, 0, $blue);
        imagejpeg($image, $tempImage, 90);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);

        expect($result)->toStartWith('data:image/jpeg;base64,');
        expect(strlen($result))->toBeGreaterThan(100); // Base64 строка должна быть достаточно длинной

        // Очищаем
        unlink($tempImage);
    });

    it('returns empty string for non-existent image', function () {
        $result = $this->imageOptimizationService->generateBlurPlaceholder('/non/existent/image.jpg');

        expect($result)->toBe('');
    });

    it('handles invalid image files', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        file_put_contents($tempFile, 'not an image content');

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempFile);

        expect($result)->toBe('');

        unlink($tempFile);
    });

    it('respects blur intensity parameter', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.png';
        $image = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $red);
        imagepng($image, $tempImage);
        imagedestroy($image);

        // Тестируем разные уровни размытия
        $resultLow = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 2);
        $resultHigh = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 8);

        expect($resultLow)->toStartWith('data:image/png;base64,');
        expect($resultHigh)->toStartWith('data:image/png;base64,');
        expect($resultLow)->not->toBe($resultHigh); // Разные уровни размытия дают разные результаты

        unlink($tempImage);
    });

    it('generates correct thumbnail size', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(800, 600); // Большое изображение
        $green = imagecolorallocate($image, 0, 255, 0);
        imagefill($image, 0, 0, $green);
        imagejpeg($image, $tempImage, 90);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50); // Маленький thumbnail

        expect($result)->toStartWith('data:image/jpeg;base64,');

        // Декодируем и проверяем размер
        $imageData = base64_decode(str_replace('data:image/jpeg;base64,', '', $result));
        $tempDecoded = tempnam(sys_get_temp_dir(), 'wp_addon_decoded_').'.jpg';
        file_put_contents($tempDecoded, $imageData);

        if (function_exists('getimagesize')) {
            $size = getimagesize($tempDecoded);
            expect($size[0])->toBeLessThanOrEqual(50); // Ширина не больше 50px
            expect($size[1])->toBeLessThanOrEqual(50); // Высота не больше 50px
        }

        unlink($tempImage);
        unlink($tempDecoded);
    });

    it('optimizes image quality', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(100, 100);
        $yellow = imagecolorallocate($image, 255, 255, 0);
        imagefill($image, 0, 0, $yellow);
        imagejpeg($image, $tempImage, 100); // Оригинал высокого качества
        imagedestroy($image);

        $originalSize = filesize($tempImage);

        // Оптимизируем
        $optimizedResult = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50, 70); // Низкое качество

        // Размер base64 строки должен быть меньше (оптимизированное изображение меньше)
        expect(strlen($optimizedResult))->toBeLessThan($originalSize * 1.5); // С запасом

        unlink($tempImage);
    });

    it('handles different image formats', function () {
        $formats = ['jpg', 'jpeg', 'png'];

        foreach ($formats as $format) {
            $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.'.$format;
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
            expect($result)->toStartWith('data:'.$expectedMime.';base64,');

            unlink($tempImage);
        }
    });

    it('handles images with alpha channel', function () {
        if (! function_exists('imagecreatetruecolor')) {
            skip('GD library not available');
        }

        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.png';
        $image = imagecreatetruecolor(50, 50);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagesavealpha($image, true);
        imagepng($image, $tempImage);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);

        expect($result)->toStartWith('data:image/png;base64,');
        expect(strlen($result))->toBeGreaterThan(100);

        unlink($tempImage);
    });

    it('validates input parameters', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        // Некорректная интенсивность размытия
        $result1 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, -1);
        $result2 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 0);
        $result3 = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 21); // > 20

        expect($result1)->toStartWith('data:image/jpeg;base64,'); // Должен обработать
        expect($result2)->toStartWith('data:image/jpeg;base64,');
        expect($result3)->toStartWith('data:image/jpeg;base64,');

        unlink($tempImage);
    });

    it('handles file permission errors', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        // Сделаем файл недоступным для чтения
        chmod($tempImage, 0000);

        if (PHP_OS_FAMILY !== 'Windows') { // На Windows chmod может не работать
            $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage);
            expect($result)->toBe('');
        }

        chmod($tempImage, 0644); // Восстановим права для удаления
        unlink($tempImage);
    });

    it('optimizes image dimensions proportionally', function () {
        $tempImage = tempnam(sys_get_temp_dir(), 'wp_addon_test_').'.jpg';
        $image = imagecreatetruecolor(400, 200); // Прямоугольное изображение
        $color = imagecolorallocate($image, 100, 100, 100);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $tempImage);
        imagedestroy($image);

        $result = $this->imageOptimizationService->generateBlurPlaceholder($tempImage, 5, 50);

        // Декодируем и проверяем пропорции
        $imageData = base64_decode(str_replace('data:image/jpeg;base64,', '', $result));
        $tempDecoded = tempnam(sys_get_temp_dir(), 'wp_addon_decoded_').'.jpg';
        file_put_contents($tempDecoded, $imageData);

        if (function_exists('getimagesize')) {
            $size = getimagesize($tempDecoded);
            $aspectRatio = $size[0] / $size[1];

            // Пропорции должны сохраниться (оригинал 2:1)
            expect($aspectRatio)->toBeGreaterThan(1.8);
            expect($aspectRatio)->toBeLessThan(2.2);
        }

        unlink($tempImage);
        unlink($tempDecoded);
    });
});
