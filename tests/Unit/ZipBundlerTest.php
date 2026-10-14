<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Download\ZipBundler;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipBundlerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/orchestra-library-test-' . \bin2hex(\random_bytes(4));
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ((array) \glob($this->directory . '/*') as $file) {
            \unlink((string) $file);
        }
        \rmdir($this->directory);
    }

    public function testBundlesFilesUnderTheirNames(): void
    {
        $archive = (new ZipBundler())->bundle([
            ['path' => $this->file('a', 'horn'), 'name' => 'Pirates - Horn 1.pdf'],
            ['path' => $this->file('b', 'flute'), 'name' => 'Pirates - Flute.pdf'],
        ], $this->directory);

        $this->assertSame(
            ['Pirates - Horn 1.pdf' => 'horn', 'Pirates - Flute.pdf' => 'flute'],
            $this->contents($archive)
        );
    }

    public function testRenamesDuplicatesAndRemovesPathSeparators(): void
    {
        $archive = (new ZipBundler())->bundle([
            ['path' => $this->file('a', '1'), 'name' => 'Score.pdf'],
            ['path' => $this->file('b', '2'), 'name' => 'score.pdf'],
            ['path' => $this->file('c', '3'), 'name' => '../AC/DC.pdf'],
        ], $this->directory);

        $this->assertSame(
            ['Score.pdf' => '1', 'score (2).pdf' => '2', '..-AC-DC.pdf' => '3'],
            $this->contents($archive)
        );
    }

    private function file(string $name, string $content): string
    {
        \file_put_contents($this->directory . '/' . $name, $content);

        return $this->directory . '/' . $name;
    }

    /**
     * @return array<string, string>
     */
    private function contents(string $archive): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive));

        $contents = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $contents[$name] = (string) $zip->getFromIndex($index);
        }
        $zip->close();

        return $contents;
    }
}
