<?php

namespace WpAddon\Services;

/**
 * Service for image optimization and blur placeholder generation
 */
class ImageOptimizationService
{
    /**
     * Generate blur placeholder for image
     *
     * @param  string  $imagePath  Path to the image file
     * @param  int  $blurIntensity  Blur intensity (1-20, default 5)
     * @param  int  $thumbnailSize  Maximum thumbnail size in pixels (default 50)
     * @param  int  $quality  JPEG quality (1-100, default 70)
     * @return string Base64 encoded blur placeholder or empty string on error
     */
    public function generateBlurPlaceholder(
        string $imagePath,
        int $blurIntensity = 5,
        int $thumbnailSize = 50,
        int $quality = 70
    ): string {
        try {
            if (! $this->isGdAvailable()) {
                return '';
            }

            // Validate input parameters
            if (! file_exists($imagePath) || ! is_readable($imagePath)) {
                return '';
            }

            $blurIntensity = max(1, min(20, $blurIntensity));
            $thumbnailSize = max(10, min(200, $thumbnailSize));
            $quality = max(1, min(100, $quality));

            // Get image info
            $imageInfo = @getimagesize($imagePath);
            if (! $imageInfo) {
                return '';
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            if ($originalWidth < 1 || $originalHeight < 1) {
                return '';
            }
            $mimeType = $imageInfo['mime'] ?? '';

            // Load image based on type
            $sourceImage = $this->loadImage($imagePath, $mimeType);
            if (! $sourceImage) {
                return '';
            }

            // Calculate thumbnail dimensions maintaining aspect ratio
            $aspectRatio = $originalWidth / $originalHeight;
            if ($aspectRatio > 1) {
                // Landscape
                $thumbWidth = $thumbnailSize;
                $thumbHeight = (int) ($thumbnailSize / $aspectRatio);
            } else {
                // Portrait or square
                $thumbWidth = (int) ($thumbnailSize * $aspectRatio);
                $thumbHeight = $thumbnailSize;
            }

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            if (! $thumbnail) {
                imagedestroy($sourceImage);

                return '';
            }

            // Handle transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }

            // Resize image
            if (! imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight)) {
                imagedestroy($sourceImage);
                imagedestroy($thumbnail);

                return '';
            }

            imagedestroy($sourceImage);

            // Apply blur effect
            if ($blurIntensity > 1) {
                $thumbnail = $this->applyBlur($thumbnail, $blurIntensity);
                if (! $thumbnail) {
                    return '';
                }
            }

            // Convert to base64
            $base64Data = $this->imageToBase64($thumbnail, $quality, $mimeType);
            imagedestroy($thumbnail);

            return $base64Data;

        } catch (\Throwable $e) {
            error_log('ImageOptimizationService error: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Load image from file based on MIME type
     *
     * @return resource|\GdImage|null
     */
    private function loadImage(string $imagePath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($imagePath);
            case 'image/png':
                return @imagecreatefrompng($imagePath);
            case 'image/gif':
                return @imagecreatefromgif($imagePath);
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    return @imagecreatefromwebp($imagePath);
                }
                break;
        }

        return null;
    }

    private function isGdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagefilter')
            && function_exists('imagejpeg')
            && function_exists('imagepng')
            && function_exists('imagedestroy');
    }

    /**
     * Apply blur effect to image
     *
     * @param  resource|\GdImage  $image
     * @return resource|\GdImage|null
     */
    private function applyBlur($image, int $intensity)
    {
        // Simple blur implementation using imagefilter
        for ($i = 0; $i < $intensity; $i++) {
            if (! imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR)) {
                return null;
            }
        }

        return $image;
    }

    /**
     * Convert image to base64 string
     *
     * @param  resource|\GdImage  $image
     */
    private function imageToBase64($image, int $quality, string $mimeType = 'image/jpeg'): string
    {
        ob_start();
        if ($mimeType === 'image/png') {
            imagepng($image, null, 8);
        } else {
            imagejpeg($image, null, $quality);
        }
        $imageData = ob_get_clean();

        if (! $imageData) {
            return '';
        }

        return 'data:'.($mimeType === 'image/png' ? 'image/png' : 'image/jpeg').';base64,'.base64_encode($imageData);
    }

    /**
     * Optimize image file (reduce size without quality loss)
     *
     * @param  int  $quality  JPEG quality (1-100, default 85)
     * @return bool Success
     */
    public function optimizeImage(string $imagePath, int $quality = 85): bool
    {
        try {
            if (! $this->isGdAvailable()) {
                return false;
            }

            if (! file_exists($imagePath) || ! is_writable($imagePath)) {
                return false;
            }

            $imageInfo = @getimagesize($imagePath);
            if (! $imageInfo) {
                return false;
            }

            $mimeType = $imageInfo['mime'] ?? '';
            $sourceImage = $this->loadImage($imagePath, $mimeType);

            if (! $sourceImage) {
                return false;
            }

            $quality = max(1, min(100, $quality));

            // Create temporary file
            $tempFile = tempnam(dirname($imagePath), '.wp_addon_opt_');
            if (! $tempFile) {
                imagedestroy($sourceImage);

                return false;
            }

            $success = false;

            // Save optimized image
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $success = imagejpeg($sourceImage, $tempFile, $quality);
                    break;
                case 'image/png':
                    $success = imagepng($sourceImage, $tempFile, 9); // Maximum compression for PNG
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $success = imagewebp($sourceImage, $tempFile, $quality);
                    }
                    break;
            }

            imagedestroy($sourceImage);

            if ($success && filesize($tempFile) < filesize($imagePath)) {
                // Replace original file if optimized version is smaller
                if (rename($tempFile, $imagePath)) {
                    return true;
                }
            }

            unlink($tempFile);

            return false;

        } catch (\Throwable $e) {
            error_log('ImageOptimizationService optimizeImage error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Generate responsive image thumbnails
     *
     * @param  array  $sizes  Array of sizes ['width' => height] or ['width']
     * @return array Array of generated thumbnail paths
     */
    public function generateThumbnails(string $imagePath, array $sizes): array
    {
        $thumbnails = [];

        try {
            if (! file_exists($imagePath)) {
                return $thumbnails;
            }

            $imageInfo = @getimagesize($imagePath);
            if (! $imageInfo) {
                return $thumbnails;
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'] ?? '';

            $sourceImage = $this->loadImage($imagePath, $mimeType);
            if (! $sourceImage) {
                return $thumbnails;
            }

            foreach ($sizes as $sizeKey => $size) {
                if (is_array($size)) {
                    $thumbWidth = $size['width'] ?? $size[0] ?? null;
                    $thumbHeight = $size['height'] ?? $size[1] ?? null;
                } else {
                    $thumbWidth = $size;
                    $thumbHeight = null;
                }

                if (! $thumbWidth) {
                    continue;
                }

                // Calculate height maintaining aspect ratio if not specified
                if (! $thumbHeight) {
                    $aspectRatio = $originalWidth / $originalHeight;
                    $thumbHeight = (int) ($thumbWidth / $aspectRatio);
                }

                $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
                if (! $thumbnail) {
                    continue;
                }

                // Handle transparency
                if ($mimeType === 'image/png') {
                    imagealphablending($thumbnail, false);
                    imagesavealpha($thumbnail, true);
                    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                    imagefill($thumbnail, 0, 0, $transparent);
                }

                // Resize
                if (imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight)) {
                    // Generate filename
                    $pathInfo = pathinfo($imagePath);
                    $thumbnailPath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'-'.$thumbWidth.'x'.$thumbHeight.'.'.$pathInfo['extension'];

                    // Save thumbnail
                    $saved = false;
                    switch ($mimeType) {
                        case 'image/jpeg':
                        case 'image/jpg':
                            $saved = imagejpeg($thumbnail, $thumbnailPath, 85);
                            break;
                        case 'image/png':
                            $saved = imagepng($thumbnail, $thumbnailPath, 8);
                            break;
                        case 'image/webp':
                            if (function_exists('imagewebp')) {
                                $saved = imagewebp($thumbnail, $thumbnailPath, 85);
                            }
                            break;
                    }

                    if ($saved) {
                        $thumbnails[] = $thumbnailPath;
                    }
                }

                imagedestroy($thumbnail);
            }

            imagedestroy($sourceImage);

        } catch (\Exception $e) {
            error_log('ImageOptimizationService generateThumbnails error: '.$e->getMessage());
        }

        return $thumbnails;
    }
}
