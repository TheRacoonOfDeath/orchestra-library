<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

use Orchestra\Library\File\FileService;

/**
 * Deletes parts and pieces only when no other plugin still refers to them.
 */
final class PartService
{
    /**
     * Consumers (e.g. orchestra-projects) return messages explaining why a part must be kept.
     * Arguments: list<string> $blockers, int $partId.
     */
    public const PART_DELETE_BLOCKERS_FILTER = 'orchestra_library_part_delete_blockers';

    /**
     * Arguments: list<string> $blockers, int $pieceId.
     */
    public const PIECE_DELETE_BLOCKERS_FILTER = 'orchestra_library_piece_delete_blockers';

    public function __construct(private readonly PartRepository $parts, private readonly FileService $files)
    {
    }

    /**
     * @return list<string> Reasons why the part cannot be deleted
     */
    public function partDeleteBlockers(int $partId): array
    {
        return self::messages(\apply_filters(self::PART_DELETE_BLOCKERS_FILTER, [], $partId));
    }

    /**
     * @return list<string> Reasons why the piece cannot be deleted, including those of its parts
     */
    public function pieceDeleteBlockers(int $pieceId): array
    {
        $blockers = self::messages(\apply_filters(self::PIECE_DELETE_BLOCKERS_FILTER, [], $pieceId));
        foreach ($this->parts->forPiece($pieceId) as $part) {
            $blockers = [...$blockers, ...$this->partDeleteBlockers($part->id)];
        }

        return \array_values(\array_unique($blockers));
    }

    /**
     * Deletes the part and its sheet music, unless it is still referenced.
     *
     * @return list<string> Reasons why the part was not deleted; empty on success
     */
    public function deletePart(Part $part): array
    {
        $blockers = $this->partDeleteBlockers($part->id);
        if ($blockers !== []) {
            return $blockers;
        }

        $this->files->deleteForPart($part->id);
        $this->parts->delete($part->id);

        return [];
    }

    /**
     * Deletes all parts and files of a piece that is being deleted. Blockers must be checked before.
     */
    public function deleteAllForPiece(int $pieceId): void
    {
        $this->files->deleteAllForPiece($pieceId);
        foreach ($this->parts->forPiece($pieceId) as $part) {
            $this->parts->delete($part->id);
        }
    }

    /**
     * @return list<string>
     */
    private static function messages(mixed $value): array
    {
        return \is_array($value) ? \array_values(\array_filter($value, \is_string(...))) : [];
    }
}
