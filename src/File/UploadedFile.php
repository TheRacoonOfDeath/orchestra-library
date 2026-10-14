<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

/**
 * An upload that passed validation and is waiting in PHP's temporary upload directory.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
    ) {
    }
}
