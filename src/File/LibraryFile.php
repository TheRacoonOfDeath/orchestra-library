<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

/**
 * Metadata of a stored file: either the sheet music of a part or a file of the whole piece.
 */
final class LibraryFile
{
    public function __construct(
        public readonly int $id,
        public readonly int $pieceId,
        public readonly ?int $partId,
        public readonly string $storageName,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly int $uploadedBy,
        public readonly string $uploadedAt,
    ) {
    }

    public function isPieceFile(): bool
    {
        return $this->partId === null;
    }

    public function extension(): string
    {
        return \strtolower(\pathinfo($this->originalName, PATHINFO_EXTENSION));
    }
}
