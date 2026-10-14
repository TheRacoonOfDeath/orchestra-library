<?php

declare(strict_types=1);

namespace Orchestra\Library\Download;

use RuntimeException;
use ZipArchive;

/**
 * Packs several files into one temporary zip archive.
 */
final class ZipBundler
{
    /**
     * @param list<array{path: string, name: string}> $entries Files and their names inside the archive
     * @return string Path of the temporary archive; the caller deletes it
     */
    public function bundle(array $entries, string $temporaryDirectory): string
    {
        $archivePath = \tempnam($temporaryDirectory, 'orchestra-library-');
        if ($archivePath === false) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::OVERWRITE) !== true) {
            \unlink($archivePath);
            throw new RuntimeException('Could not create the zip archive.');
        }

        $usedNames = [];
        foreach ($entries as $entry) {
            $name = self::uniqueName(self::sanitize($entry['name']), $usedNames);
            $usedNames[\mb_strtolower($name)] = true;
            $zip->addFile($entry['path'], $name);
        }

        if (!$zip->close()) {
            \unlink($archivePath);
            throw new RuntimeException('Could not write the zip archive.');
        }

        return $archivePath;
    }

    private static function sanitize(string $name): string
    {
        $name = \trim(\str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0"], '-', $name));

        return $name === '' ? 'file' : $name;
    }

    /**
     * @param array<string, true> $usedNames lowercase names already in the archive
     */
    private static function uniqueName(string $name, array $usedNames): string
    {
        if (!isset($usedNames[\mb_strtolower($name)])) {
            return $name;
        }

        $extension = \pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : \substr($name, 0, -\strlen($extension) - 1);
        $suffix = $extension === '' ? '' : '.' . $extension;

        for ($counter = 2;; $counter++) {
            $candidate = "{$base} ({$counter}){$suffix}";
            if (!isset($usedNames[\mb_strtolower($candidate)])) {
                return $candidate;
            }
        }
    }
}
