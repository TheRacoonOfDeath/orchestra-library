<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

/**
 * Metadata of a stored file: either the sheet music of a part or a file of the whole piece.
 */
final class LibraryFile
{
    public const MAX_DISPLAY_NAME_LENGTH = 191;

    public function __construct(
        public readonly int $id,
        public readonly int $pieceId,
        public readonly ?int $partId,
        public readonly string $storageName,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $uploadedAt,
        /** The name shown for a piece file, entered by the uploader; '' for sheet music of parts. */
        public readonly string $displayName = '',
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

    /**
     * The name of the downloaded file: the display name with the original extension, or the
     * original name for files without a display name.
     */
    public function downloadName(): string
    {
        if ($this->displayName === '') {
            return $this->originalName;
        }

        return $this->displayName . ($this->extension() === '' ? '' : '.' . $this->extension());
    }

    /**
     * The display name for a file that has none, e.g. "Full score" for "Full score.pdf".
     */
    public static function defaultDisplayName(string $originalName): string
    {
        $name = \pathinfo($originalName, PATHINFO_FILENAME);

        return $name === '' ? $originalName : $name;
    }
}
