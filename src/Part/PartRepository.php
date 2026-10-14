<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

use Orchestra\Library\Database\Row;
use Orchestra\Library\Database\Schema;
use RuntimeException;
use wpdb;

final class PartRepository
{
    public function __construct(private readonly wpdb $wpdb, private readonly Schema $schema)
    {
    }

    public function find(int $partId): ?Part
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM %i WHERE id = %d', $this->schema->partsTable(), $partId),
            ARRAY_A
        );

        return \is_array($row) ? self::hydrate($row) : null;
    }

    /**
     * @param list<int> $partIds
     * @return list<Part> in the order of the given IDs; unknown IDs are skipped
     */
    public function findMany(array $partIds): array
    {
        $partIds = \array_values(\array_unique(\array_filter($partIds, static fn (int $id): bool => $id > 0)));
        if ($partIds === []) {
            return [];
        }

        $rows = Row::all($this->wpdb->get_results(
            "SELECT * FROM {$this->schema->partsTable()} WHERE id IN (" . Row::idList($partIds) . ')',
            ARRAY_A
        ));

        $byId = [];
        foreach ($rows as $row) {
            $part = self::hydrate($row);
            $byId[$part->id] = $part;
        }

        return \array_values(\array_filter(\array_map(static fn (int $id): ?Part => $byId[$id] ?? null, $partIds)));
    }

    /**
     * @return list<Part> ordered by position
     */
    public function forPiece(int $pieceId): array
    {
        $rows = Row::all($this->wpdb->get_results(
            $this->wpdb->prepare(
                'SELECT * FROM %i WHERE piece_id = %d ORDER BY position, id',
                $this->schema->partsTable(),
                $pieceId
            ),
            ARRAY_A
        ));

        return \array_map(self::hydrate(...), $rows);
    }

    /**
     * @param list<int> $pieceIds
     * @return array<int, list<Part>> piece ID => parts ordered by position; pieces without parts are missing
     */
    public function forPieces(array $pieceIds): array
    {
        if ($pieceIds === []) {
            return [];
        }

        $rows = Row::all($this->wpdb->get_results(
            "SELECT * FROM {$this->schema->partsTable()}"
            . ' WHERE piece_id IN (' . Row::idList($pieceIds) . ') ORDER BY position, id',
            ARRAY_A
        ));

        $byPiece = [];
        foreach ($rows as $row) {
            $part = self::hydrate($row);
            $byPiece[$part->pieceId][] = $part;
        }

        return $byPiece;
    }

    public function countForPiece(int $pieceId): int
    {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare('SELECT COUNT(*) FROM %i WHERE piece_id = %d', $this->schema->partsTable(), $pieceId)
        );

        return \is_numeric($count) ? (int) $count : 0;
    }

    public function insert(int $pieceId, string $name, string $description, int $position): Part
    {
        $inserted = $this->wpdb->insert(
            $this->schema->partsTable(),
            ['piece_id' => $pieceId, 'name' => $name, 'description' => $description, 'position' => $position],
            ['%d', '%s', '%s', '%d']
        );
        if ($inserted === false) {
            throw new RuntimeException('Could not create part: ' . $this->wpdb->last_error);
        }

        return new Part((int) $this->wpdb->insert_id, $pieceId, $name, $description, $position);
    }

    public function update(Part $part): void
    {
        $updated = $this->wpdb->update(
            $this->schema->partsTable(),
            ['name' => $part->name, 'description' => $part->description, 'position' => $part->position],
            ['id' => $part->id],
            ['%s', '%s', '%d'],
            ['%d']
        );
        if ($updated === false) {
            throw new RuntimeException('Could not update part: ' . $this->wpdb->last_error);
        }
    }

    public function delete(int $partId): void
    {
        $this->wpdb->delete($this->schema->partsTable(), ['id' => $partId], ['%d']);
    }

    /**
     * @param array<mixed> $row
     */
    private static function hydrate(array $row): Part
    {
        return new Part(
            Row::int($row, 'id'),
            Row::int($row, 'piece_id'),
            Row::string($row, 'name'),
            Row::string($row, 'description'),
            Row::int($row, 'position'),
        );
    }
}
