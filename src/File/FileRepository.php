<?php

declare(strict_types=1);

namespace Orchestra\Library\File;

use Orchestra\Library\Database\Row;
use Orchestra\Library\Database\Schema;
use RuntimeException;
use wpdb;

final class FileRepository
{
    public function __construct(private readonly wpdb $wpdb, private readonly Schema $schema)
    {
    }

    public function find(int $fileId): ?LibraryFile
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM %i WHERE id = %d', $this->schema->filesTable(), $fileId),
            ARRAY_A
        );

        return \is_array($row) ? self::hydrate($row) : null;
    }

    public function forPart(int $partId): ?LibraryFile
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM %i WHERE part_id = %d', $this->schema->filesTable(), $partId),
            ARRAY_A
        );

        return \is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * @param list<int> $partIds
     * @return array<int, LibraryFile> part ID => sheet music; parts without a file are missing
     */
    public function forParts(array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }

        $rows = Row::all($this->wpdb->get_results(
            "SELECT * FROM {$this->schema->filesTable()} WHERE part_id IN (" . Row::idList($partIds) . ')',
            ARRAY_A
        ));

        $byPart = [];
        foreach ($rows as $row) {
            $file = self::hydrate($row);
            if ($file->partId !== null) {
                $byPart[$file->partId] = $file;
            }
        }

        return $byPart;
    }

    /**
     * @return list<LibraryFile> files of the piece itself, not of its parts
     */
    public function pieceFiles(int $pieceId): array
    {
        return $this->hydrateAll($this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE piece_id = %d AND part_id IS NULL ORDER BY original_name, id',
                $this->schema->filesTable(),
                $pieceId
            ),
            ARRAY_A
        ));
    }

    /**
     * @param list<int> $pieceIds
     * @return array<int, list<LibraryFile>> piece ID => files of the piece itself
     */
    public function pieceFilesForPieces(array $pieceIds): array
    {
        if ($pieceIds === []) {
            return [];
        }

        $rows = Row::all($this->wpdb->get_results(
            "SELECT * FROM {$this->schema->filesTable()} WHERE piece_id IN (" . Row::idList($pieceIds) . ')'
            . ' AND part_id IS NULL ORDER BY original_name, id',
            ARRAY_A
        ));

        $byPiece = [];
        foreach ($rows as $row) {
            $file = self::hydrate($row);
            $byPiece[$file->pieceId][] = $file;
        }

        return $byPiece;
    }

    /**
     * @return list<LibraryFile> piece files and the sheet music of all parts
     */
    public function allForPiece(int $pieceId): array
    {
        return $this->hydrateAll($this->wpdb->get_results(
            $this->wpdb->prepare('SELECT * FROM %i WHERE piece_id = %d', $this->schema->filesTable(), $pieceId),
            ARRAY_A
        ));
    }

    /**
     * @return list<LibraryFile>
     */
    public function all(): array
    {
        return $this->hydrateAll($this->wpdb->get_results("SELECT * FROM {$this->schema->filesTable()}", ARRAY_A));
    }

    public function insert(
        int $pieceId,
        ?int $partId,
        string $storageName,
        string $originalName,
        string $mimeType,
        int $size,
        int $uploadedBy,
    ): LibraryFile {
        $uploadedAt = \current_time('mysql', true);
        $data = [
            'piece_id' => $pieceId,
            'storage_name' => $storageName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => $uploadedAt,
        ];
        $formats = ['%d', '%s', '%s', '%s', '%d', '%d', '%s'];
        // $wpdb->insert() cannot write NULL with a format, so the column is left out for piece files.
        if ($partId !== null) {
            $data['part_id'] = $partId;
            $formats[] = '%d';
        }

        if ($this->wpdb->insert($this->schema->filesTable(), $data, $formats) === false) {
            throw new RuntimeException('Could not store file metadata: ' . $this->wpdb->last_error);
        }

        return new LibraryFile(
            (int) $this->wpdb->insert_id,
            $pieceId,
            $partId,
            $storageName,
            $originalName,
            $mimeType,
            $size,
            $uploadedBy,
            $uploadedAt,
        );
    }

    public function delete(int $fileId): void
    {
        $this->wpdb->delete($this->schema->filesTable(), ['id' => $fileId], ['%d']);
    }

    /**
     * @return list<LibraryFile>
     */
    private function hydrateAll(mixed $results): array
    {
        return \array_map(self::hydrate(...), Row::all($results));
    }

    /**
     * @param array<mixed> $row
     */
    private static function hydrate(array $row): LibraryFile
    {
        return new LibraryFile(
            Row::int($row, 'id'),
            Row::int($row, 'piece_id'),
            Row::nullableInt($row, 'part_id'),
            Row::string($row, 'storage_name'),
            Row::string($row, 'original_name'),
            Row::string($row, 'mime_type'),
            Row::int($row, 'size'),
            Row::int($row, 'uploaded_by'),
            Row::string($row, 'uploaded_at'),
        );
    }
}
