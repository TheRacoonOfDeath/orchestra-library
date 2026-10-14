<?php

declare(strict_types=1);

namespace Orchestra\Library;

use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Piece\Piece;
use Orchestra\Library\Piece\PieceRepository;

/**
 * The public, read-only API of orchestra-library for other plugins.
 *
 *     $library = \Orchestra\Library\Plugin::instance()->library();
 *
 * Other plugins must not access the library's tables or post meta directly.
 */
final class LibraryService
{
    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PartRepository $parts,
        private readonly FileRepository $files,
    ) {
    }

    public function piece(int $pieceId): ?Piece
    {
        return $this->pieces->find($pieceId);
    }

    /**
     * Published pieces matching the query in title, description or composer.
     *
     * @return list<Piece>
     */
    public function searchPieces(string $query = '', bool $includeArchived = false, int $limit = 100): array
    {
        return $this->pieces->search($query, $includeArchived, $limit);
    }

    public function part(int $partId): ?Part
    {
        return $this->parts->find($partId);
    }

    /**
     * @param list<int> $partIds
     * @return list<Part> in the given order; unknown IDs are skipped
     */
    public function partsByIds(array $partIds): array
    {
        return $this->parts->findMany($partIds);
    }

    /**
     * @return list<Part> ordered by position
     */
    public function parts(int $pieceId): array
    {
        return $this->parts->forPiece($pieceId);
    }

    public function sheetMusic(int $partId): ?LibraryFile
    {
        return $this->files->forPart($partId);
    }

    /**
     * @return list<LibraryFile> files of the piece itself (score, notes, …)
     */
    public function pieceFiles(int $pieceId): array
    {
        return $this->files->pieceFiles($pieceId);
    }

    public function downloadUrl(LibraryFile $file): string
    {
        return DownloadController::fileUrl($file->id);
    }

    /**
     * A URL that downloads the sheet music of the given parts as one zip archive.
     *
     * @param list<int> $partIds
     * @param string $archiveName File name of the archive without extension, e.g. "Spring Concert 2027"
     */
    public function bundleDownloadUrl(array $partIds, string $archiveName): string
    {
        return DownloadController::bundleUrl($partIds, $archiveName);
    }
}
