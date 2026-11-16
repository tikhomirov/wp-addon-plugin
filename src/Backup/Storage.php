<?php

/**
 * Backup storage: safe directory access, listing, deletion, uploads.
 */

declare(strict_types=1);

namespace WpAddon\Backup;

if (! defined('ABSPATH')) {
    exit('-1');
}

/**
 * Manages dump files under uploads/backup.
 */
final class Storage
{
    /** Dump directory name under wp-content/uploads. */
    public const DIRNAME = 'backup';

    /** Dump file name prefix. */
    private const PREFIX = 'backup_';

    /** Dump file extension. */
    private const EXTENSION = '.sql';

    /** Subdirectory for uploaded dumps. */
    private const UPLOADED_DIRNAME = 'uploaded';

    /** @var string Absolute path to backup root. */
    private string $root;

    /**
     * @param  string|null  $root  Backup directory; default uploads/backup.
     *
     * @throws \RuntimeException If the directory cannot be created.
     */
    public function __construct(?string $root = null)
    {
        $base = $root ?? (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR.'/uploads' : '');
        $this->root = rtrim($base, '/').'/'.self::DIRNAME;
    }

    /**
     * Absolute backup directory path.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Directory for uploaded dumps.
     */
    public function uploaded_root(): string
    {
        return $this->root.'/'.self::UPLOADED_DIRNAME;
    }

    /**
     * Creates directories and blocks web access.
     *
     * On shared hosting, .htaccess and index.php are the only
     * protection when nginx/apache are configured externally.
     *
     * @throws \RuntimeException If directories are not writable.
     */
    public function ensure_directories(): void
    {
        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            if (! is_dir($dir) && ! wp_mkdir_p($dir)) {
                throw new \RuntimeException(__('Could not create the backup directory.', 'wp-addon'));
            }

            $this->write_protection($dir);
        }
    }

    /**
     * Writes stubs that deny direct HTTP access.
     */
    private function write_protection(string $dir): void
    {
        $htaccess = $dir.'/.htaccess';
        $index = $dir.'/index.php';

        if (! is_file($htaccess)) {
            $rules = "# Created by backup module: deny direct HTTP access to dumps.\n"
                ."<IfModule mod_authz_core.c>\n"
                ."    Require all denied\n"
                ."</IfModule>\n"
                ."<IfModule !mod_authz_core.c>\n"
                ."    Order deny,allow\n"
                ."    Deny from all\n"
                ."</IfModule>\n";

            @file_put_contents($htaccess, $rules);
        }

        if (! is_file($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\nexit;\n");
        }
    }

    /**
     * List backups: name, size, modified time.
     *
     * @return array<int,array{name:string,size:int,modified:int}>
     */
    public function list_backups(): array
    {
        $backups = [];

        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $entries = @scandir($dir);

            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! $this->is_backup_name($entry)) {
                    continue;
                }

                $path = $dir.'/'.$entry;

                if (! is_file($path)) {
                    continue;
                }

                $backups[] = [
                    'name' => $entry,
                    'size' => (int) @filesize($path),
                    'modified' => (int) @filemtime($path),
                ];
            }
        }

        usort($backups, static function (array $a, array $b): int {
            $by_date = $b['modified'] <=> $a['modified'];

            return $by_date !== 0 ? $by_date : strcmp($b['name'], $a['name']);
        });

        return $backups;
    }

    /**
     * Resolve a file name to a path inside the backup directory.
     *
     * Path traversal guard: whitelist the name, then
     * compare realpath to the directory root. User input
     * becomes a file path only here.
     *
     * @param  string  $name  File name from the request.
     * @return string Absolute file path.
     *
     * @throws \RuntimeException If the name is invalid or the file is outside the directory.
     */
    public function resolve(string $name): string
    {
        $safe = basename(trim($name));

        if ($safe === '' || $safe === '.' || $safe === '..' || ! $this->is_backup_name($safe)) {
            throw new \RuntimeException(__('Invalid backup file name.', 'wp-addon'));
        }

        $found = $this->find($safe);

        if ($found === null) {
            throw new \RuntimeException(__('Backup file not found.', 'wp-addon'));
        }

        return $found;
    }

    /**
     * Find a dump in the root or upload subdirectory.
     */
    private function find(string $name): ?string
    {
        foreach ([$this->root, $this->uploaded_root()] as $dir) {
            $candidate = $dir.'/'.$name;

            if (! is_file($candidate)) {
                continue;
            }

            $real = @realpath($candidate);
            $real_root = @realpath($dir);

            if ($real === false || $real_root === false) {
                continue;
            }

            // Reject symlink or path outside the directory.
            if (strncmp($real, $real_root.DIRECTORY_SEPARATOR, strlen($real_root) + 1) !== 0) {
                continue;
            }

            return $real;
        }

        return null;
    }

    /**
     * Check the name matches the dump file pattern.
     *
     * Only backup_<label>.sql or uploaded
     * upload_<label>.sql — blocks index.php,
     * .htaccess, or other directory files.
     */
    public function is_backup_name(string $name): bool
    {
        return (bool) preg_match(
            '/^(?:backup|upload)_[A-Za-z0-9._-]+'.preg_quote(self::EXTENSION, '/').'$/',
            basename($name)
        );
    }

    /**
     * Delete a dump.
     *
     * @throws \RuntimeException If the file is missing or could not be deleted.
     */
    public function delete(string $name): void
    {
        $path = $this->resolve($name);

        if (! @unlink($path)) {
            throw new \RuntimeException(__('Could not delete the backup file.', 'wp-addon'));
        }
    }

    /**
     * Path for a new timestamped dump.
     */
    public function new_dump_path(string $label): string
    {
        return $this->root.'/'.self::PREFIX.$label.self::EXTENSION;
    }

    /**
     * Accept a user-uploaded dump file.
     *
     * Content is validated by format markers to block
     * uploading non-SQL payloads.
     *
     * @param  array  $file  $_FILES entry.
     * @return string Saved file name.
     *
     * @throws \RuntimeException On invalid upload or content.
     */
    public function store_uploaded(array $file): string
    {
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %d: upload error code */
                    __('File not uploaded (error code: %d).', 'wp-addon'),
                    $error
                )
            );
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            throw new \RuntimeException(__('Invalid file upload.', 'wp-addon'));
        }

        $size = (int) @filesize($tmp);

        if ($size < 32) {
            throw new \RuntimeException(__('File is too small to be a database backup.', 'wp-addon'));
        }

        $head = (string) @file_get_contents($tmp, false, null, 0, 4096);

        if (! $this->looks_like_dump($head)) {
            throw new \RuntimeException(__('File does not look like a WordPress database backup.', 'wp-addon'));
        }

        $this->ensure_directories();

        $name = 'upload_'.gmdate('Y-m-d_H-i-s').'_'.substr(bin2hex(random_bytes(4)), 0, 8).self::EXTENSION;
        $target = $this->uploaded_root().'/'.$name;

        $moved = @move_uploaded_file($tmp, $target);

        if (! $moved) {
            throw new \RuntimeException(__('Could not save the uploaded backup.', 'wp-addon'));
        }

        $this->write_protection($this->uploaded_root());

        return $name;
    }

    /**
     * Whether file head looks like an SQL dump.
     *
     * Accepts this plugin's dumps and exports from
     * phpMyAdmin, mysqldump. Otherwise site migration
     * would break on phpMyAdmin imports.
     *
     * Also verify SQL-like content to reject
     * executable uploads.
     */
    public function looks_like_dump(string $head): bool
    {
        if ($head === '' || ! mb_check_encoding($head, 'UTF-8')) {
            return false;
        }

        if (str_contains($head, '-- Woo2iiko WordPress database backup')) {
            return true;
        }

        // One characteristic SQL dump marker is enough.
        return (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|--\s*MySQL\s+dump|--\s*Table\s+structure)/i', $head);
    }

    /**
     * Human-readable file size.
     */
    public static function format_size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 1).' '.$units[$index];
    }
}
