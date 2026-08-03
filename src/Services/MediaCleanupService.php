<?php

namespace WpAddon\Services;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RegexIterator;

/**
 * Service for cleaning up unused image sizes
 */
class MediaCleanupService
{
    /**
     * Get registered image sizes (static)
     *
     * @return array
     */
    public static function getRegisteredSizesStatic(): array
    {
        $sizes = apply_filters('intermediate_image_sizes', ['thumbnail', 'medium', 'large']);
        $registeredSizes = [];

        foreach ($sizes as $size) {
            switch ($size) {
                case 'thumbnail':
                    $width = get_option('thumbnail_size_w');
                    $height = get_option('thumbnail_size_h');
                    break;
                case 'medium':
                    $width = get_option('medium_size_w');
                    $height = get_option('medium_size_h');
                    break;
                case 'large':
                    $width = get_option('large_size_w');
                    $height = get_option('large_size_h');
                    break;
                default:
                    $additionalSizes = wp_get_additional_image_sizes();
                    if (isset($additionalSizes[$size])) {
                        $width = $additionalSizes[$size]['width'];
                        $height = $additionalSizes[$size]['height'];
                    } else {
                        continue 2;
                    }
                    break;
            }
            $registeredSizes[] = $width . 'x' . $height;
        }

        return $registeredSizes;
    }

    /**
     * Check if file should be deleted
     *
     * @param string $basename
     * @return bool
     */
    public function isFileToDelete(string $basename): bool
    {
        if (preg_match('/-(\d+)x(\d+)\.(jpg|jpeg|png|gif)$/i', $basename, $matches)) {
            $sizeKey = $matches[1] . 'x' . $matches[2];

            // Exclude scaled and other special files
            if (strpos($basename, '-scaled') !== false) {
                return false;
            }

            $activeSizes = self::getRegisteredSizesStatic();
            return !in_array($sizeKey, $activeSizes);
        }

        return false;
    }

    /**
     * Get all image files in directory
     *
     * @param string $directory
     * @return array
     */
    public function getAllImageFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $regex = new RegexIterator($iterator, '/\.(jpg|jpeg|png|gif)$/i');

        foreach ($regex as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get files to delete with total size
     *
     * @param string $uploadPath
     * @return array ['files' => array, 'totalSize' => int]
     */
    public function getFilesToDelete(string $uploadPath): array
    {
        $files = $this->getAllImageFiles($uploadPath);
        $toDelete = [];
        $totalSize = 0;

        foreach ($files as $file) {
            $basename = basename($file);
            if ($this->isFileToDelete($basename)) {
                $size = filesize($file);
                $toDelete[] = $file;
                $totalSize += $size;
            }
        }

        return [
            'files' => $toDelete,
            'totalSize' => $totalSize
        ];
    }

    /**
     * Delete files
     *
     * @param array $files
     * @param string $uploadPath
     * @return array ['deleted' => int, 'errors' => int]
     */
    public function deleteFiles(array $files, string $uploadPath = ''): array
    {
        $deleted = 0;
        $errors = 0;

        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            } else {
                $errors++;
            }
        }

        return [
            'deleted' => $deleted,
            'errors' => $errors
        ];
    }
}
