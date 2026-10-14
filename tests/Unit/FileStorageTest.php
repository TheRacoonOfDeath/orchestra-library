<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use InvalidArgumentException;
use Orchestra\Library\File\FileStorage;
use Orchestra\Library\File\UploadedFile;
use PHPUnit\Framework\TestCase;

final class FileStorageTest extends TestCase
{
    private string $directory;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/orchestra-library-storage-' . \bin2hex(\random_bytes(4));
        $this->storage = new FileStorage($this->directory, \rename(...));
    }

    protected function tearDown(): void
    {
        foreach ((array) \glob($this->directory . '/{,.}*', GLOB_BRACE) as $file) {
            if (\is_file((string) $file)) {
                \unlink((string) $file);
            }
        }
        if (\is_dir($this->directory)) {
            \rmdir($this->directory);
        }
    }

    public function testStoresFilesUnderGeneratedNamesInAProtectedDirectory(): void
    {
        $source = (string) \tempnam(\sys_get_temp_dir(), 'upload');
        \file_put_contents($source, 'sheet music');

        $storageName = $this->storage->store(new UploadedFile($source, 'Horn.pdf', 'application/pdf', 11));

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $storageName);
        $this->assertSame('sheet music', \file_get_contents($this->storage->path($storageName)));
        $this->assertFileDoesNotExist($source);
        $htaccess = (string) \file_get_contents($this->directory . '/.htaccess');
        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertFileExists($this->directory . '/index.php');
    }

    public function testDeletesStoredFiles(): void
    {
        $source = (string) \tempnam(\sys_get_temp_dir(), 'upload');
        $storageName = $this->storage->store(new UploadedFile($source, 'Horn.pdf', 'application/pdf', 1));

        $this->storage->delete($storageName);

        $this->assertFileDoesNotExist($this->storage->path($storageName));
    }

    public function testRejectsStorageNamesThatCouldLeaveTheDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->storage->path('../../wp-config.php');
    }

    public function testRemoveDirectoryKeepsDirectoriesThatStillContainFiles(): void
    {
        $this->storage->ensureDirectory();
        \file_put_contents($this->directory . '/foreign-file', 'x');

        $this->storage->removeDirectory();

        $this->assertDirectoryExists($this->directory);
        $this->assertFileDoesNotExist($this->directory . '/.htaccess');
    }
}
