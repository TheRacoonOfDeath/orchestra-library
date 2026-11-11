<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\File\LibraryFile;
use PHPUnit\Framework\TestCase;

final class LibraryFileTest extends TestCase
{
    public function testDownloadsPieceFilesUnderTheirDisplayName(): void
    {
        $this->assertSame('Full score.pdf', self::file('partitur_v3.PDF', 'Full score')->downloadName());
        $this->assertSame('Notes', self::file('notes', 'Notes')->downloadName());
    }

    public function testDownloadsFilesWithoutDisplayNameUnderTheirOriginalName(): void
    {
        $this->assertSame('Horn 1.pdf', self::file('Horn 1.pdf', '')->downloadName());
    }

    public function testDefaultDisplayNameIsTheFileNameWithoutExtension(): void
    {
        $this->assertSame('Full score', LibraryFile::defaultDisplayName('Full score.pdf'));
        $this->assertSame('archive.tar', LibraryFile::defaultDisplayName('archive.tar.gz'));
        $this->assertSame('notes', LibraryFile::defaultDisplayName('notes'));
    }

    private static function file(string $originalName, string $displayName): LibraryFile
    {
        return new LibraryFile(1, 2, null, 'uuid', $originalName, 'application/pdf', 10, '2026-11-06', $displayName);
    }
}
