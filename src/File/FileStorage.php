<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

use InvalidArgumentException;
use RuntimeException;

/**
 * Stores files in a private directory under generated names.
 *
 * The directory is `ORCHESTRA_LIBRARY_STORAGE_DIR` if defined (preferably outside the web root),
 * otherwise a directory with an unguessable name inside the uploads directory, protected by
 * `.htaccess` (Apache only; nginx must deny it in its configuration).
 */
final class FileStorage
{
    public const OPTION_DIRECTORY_SUFFIX = 'orchestra_library_storage_suffix';

    private const STORAGE_NAME_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param \Closure(string $source, string $target): bool $mover Moves a file into the storage;
     *        defaults to PHP's move_uploaded_file()
     */
    public function __construct(private readonly string $directory, private readonly ?\Closure $mover = null)
    {
    }

    public static function fromWordPress(): self
    {
        if (\defined('ORCHESTRA_LIBRARY_STORAGE_DIR') && \is_string(ORCHESTRA_LIBRARY_STORAGE_DIR)) {
            return new self(\untrailingslashit(ORCHESTRA_LIBRARY_STORAGE_DIR));
        }

        $suffix = \get_option(self::OPTION_DIRECTORY_SUFFIX);
        if (!\is_string($suffix) || \preg_match('/^[0-9a-z]{32}$/', $suffix) !== 1) {
            $suffix = \strtolower(\wp_generate_password(32, false));
            \update_option(self::OPTION_DIRECTORY_SUFFIX, $suffix, false);
        }

        return new self(\wp_upload_dir(null, false)['basedir'] . '/orchestra-library-' . $suffix);
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Moves an uploaded file into the storage.
     *
     * @return string The generated storage name
     */
    public function store(UploadedFile $file): string
    {
        $this->ensureDirectory();

        $storageName = \wp_generate_uuid4();
        $mover = $this->mover ?? \move_uploaded_file(...);
        if (!$mover($file->temporaryPath, $this->path($storageName))) {
            throw new RuntimeException(\sprintf('Could not store "%s".', $file->originalName));
        }

        return $storageName;
    }

    public function path(string $storageName): string
    {
        if (\preg_match(self::STORAGE_NAME_PATTERN, $storageName) !== 1) {
            throw new InvalidArgumentException('Invalid storage name.');
        }

        return $this->directory . '/' . $storageName;
    }

    public function delete(string $storageName): void
    {
        $path = $this->path($storageName);
        if (\is_file($path)) {
            \unlink($path);
        }
    }

    /**
     * Removes the protection files and the directory if it is empty afterwards. Only for uninstalling.
     */
    public function removeDirectory(): void
    {
        foreach (['.htaccess', 'index.php'] as $protectionFile) {
            if (\is_file($this->directory . '/' . $protectionFile)) {
                \unlink($this->directory . '/' . $protectionFile);
            }
        }
        if (\is_dir($this->directory) && \count((array) \scandir($this->directory)) <= 2) {
            \rmdir($this->directory);
        }
    }

    public function ensureDirectory(): void
    {
        if (!\is_dir($this->directory) && !\wp_mkdir_p($this->directory)) {
            throw new RuntimeException('Could not create the sheet music storage directory.');
        }

        $protection = [
            '.htaccess' => "# Sheet music is only delivered through orchestra-library's download endpoint.\n"
                . "Require all denied\n"
                . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n",
            'index.php' => "<?php\n// Silence is golden.\n",
        ];
        foreach ($protection as $fileName => $content) {
            $path = $this->directory . '/' . $fileName;
            if (!\is_file($path) && \file_put_contents($path, $content) === false) {
                throw new RuntimeException(\sprintf('Could not write %s.', $path));
            }
        }
    }
}
