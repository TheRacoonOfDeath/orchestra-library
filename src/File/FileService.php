<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

use Orchestra\Library\Part\Part;
use Throwable;

/**
 * Keeps file metadata and stored files consistent.
 */
final class FileService
{
    public function __construct(private readonly FileRepository $files, private readonly FileStorage $storage)
    {
    }

    /**
     * Stores the sheet music of a part, replacing an existing file.
     */
    public function attachToPart(Part $part, UploadedFile $upload, int $uploadedBy): LibraryFile
    {
        $storageName = $this->storage->store($upload);

        try {
            $previous = $this->files->forPart($part->id);
            if ($previous !== null) {
                $this->delete($previous);
            }

            return $this->files->insert(
                $part->pieceId,
                $part->id,
                $storageName,
                $upload->originalName,
                $upload->mimeType,
                $upload->size,
                $uploadedBy,
            );
        } catch (Throwable $exception) {
            $this->storage->delete($storageName);
            throw $exception;
        }
    }

    public function attachToPiece(int $pieceId, UploadedFile $upload, int $uploadedBy): LibraryFile
    {
        $storageName = $this->storage->store($upload);

        try {
            return $this->files->insert(
                $pieceId,
                null,
                $storageName,
                $upload->originalName,
                $upload->mimeType,
                $upload->size,
                $uploadedBy,
            );
        } catch (Throwable $exception) {
            $this->storage->delete($storageName);
            throw $exception;
        }
    }

    public function delete(LibraryFile $file): void
    {
        $this->files->delete($file->id);
        $this->storage->delete($file->storageName);
    }

    public function deleteForPart(int $partId): void
    {
        $file = $this->files->forPart($partId);
        if ($file !== null) {
            $this->delete($file);
        }
    }

    public function deleteAllForPiece(int $pieceId): void
    {
        foreach ($this->files->allForPiece($pieceId) as $file) {
            $this->delete($file);
        }
    }

    public function path(LibraryFile $file): string
    {
        return $this->storage->path($file->storageName);
    }
}
