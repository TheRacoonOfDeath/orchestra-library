<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use Orchestra\Library\Capabilities;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileService;
use Orchestra\Library\File\UploadRejected;
use Orchestra\Library\File\UploadValidator;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartNames;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Part\PartService;
use RuntimeException;

/**
 * Saves the fields of {@see PieceForm}: composer, archived flag, parts with their sheet music, and
 * piece files. Used by the piece editor in wp-admin and by the editor on the library page.
 */
final class PieceFormHandler
{
    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PartRepository $parts,
        private readonly PartService $partService,
        private readonly FileRepository $files,
        private readonly FileService $fileService,
        private readonly UploadValidator $uploads,
    ) {
    }

    /**
     * The caller checks that the user may edit the piece.
     *
     * @param array<mixed> $input Unslashed form data
     * @param array<mixed> $uploads `$_FILES`
     * @return list<string> error messages; the rest is saved
     */
    public function save(int $pieceId, array $input, array $uploads): array
    {
        $this->pieces->setComposer($pieceId, \sanitize_text_field(self::string($input, PieceForm::FIELD_COMPOSER)));
        $this->pieces->setArchived($pieceId, !empty($input[PieceForm::FIELD_ARCHIVED]));

        $errors = $this->saveParts($pieceId, $input, $uploads);
        if (\current_user_can(Capabilities::SHEET_MUSIC_MANAGE)) {
            $errors = [...$errors, ...$this->savePieceFiles($pieceId, $input, $uploads)];
        }

        return $errors;
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed> $uploads
     * @return list<string>
     */
    private function saveParts(int $pieceId, array $input, array $uploads): array
    {
        $errors = [];
        $existing = $this->parts->forPiece($pieceId);
        $submitted = \is_array($input[PieceForm::FIELD_PARTS] ?? null) ? $input[PieceForm::FIELD_PARTS] : [];

        $names = [];
        $toDelete = [];
        $toUpdate = [];
        foreach ($existing as $part) {
            $row = $submitted[$part->id] ?? null;
            if (!\is_array($row)) {
                $names[] = PartNames::normalize($part->name);
                continue;
            }

            if (!empty($row['delete'])) {
                $blockers = $this->partService->partDeleteBlockers($part->id);
                if ($blockers === []) {
                    $toDelete[] = $part;
                    continue;
                }
                /* translators: 1: part name, 2: reasons */
                $message = \__('The part "%1$s" cannot be deleted: %2$s', 'orchestra-library');
                $errors[] = \sprintf($message, $part->name, \implode(' ', $blockers));
            }

            $name = PartNames::normalize(self::string($row, 'name'));
            $names[] = $name;
            $toUpdate[] = [
                'part' => new Part(
                    $part->id,
                    $pieceId,
                    $name,
                    \sanitize_text_field(self::string($row, 'description')),
                    self::int($row, 'position', $part->position),
                ),
                'removeFile' => !empty($row['remove_file']),
            ];
        }

        $toInsert = [];
        $nextPosition = PieceForm::nextPosition($existing);
        $newRows = \is_array($input[PieceForm::FIELD_NEW_PARTS] ?? null) ? $input[PieceForm::FIELD_NEW_PARTS] : [];
        foreach ($newRows as $key => $row) {
            if (!\is_array($row)) {
                continue;
            }
            $name = PartNames::normalize(self::string($row, 'name'));
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            $toInsert[$key] = [
                'name' => $name,
                'description' => \sanitize_text_field(self::string($row, 'description')),
                'position' => self::int($row, 'position', $nextPosition++),
            ];
        }

        $nameErrors = PartNames::validate($names);
        if ($nameErrors !== []) {
            return [...$errors, \__('The parts were not saved.', 'orchestra-library'), ...$nameErrors];
        }

        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);
        try {
            foreach ($toDelete as $part) {
                $errors = [...$errors, ...$this->partService->deletePart($part)];
            }
            foreach ($toUpdate as ['part' => $part, 'removeFile' => $removeFile]) {
                $this->parts->update($part);
                if ($canManageFiles && $removeFile) {
                    $this->fileService->deleteForPart($part->id);
                }
                if ($canManageFiles) {
                    $upload = UploadValidator::extract($uploads, PieceForm::FIELD_PART_FILE, $part->id);
                    $errors = [...$errors, ...$this->attach($part, $upload)];
                }
            }
            foreach ($toInsert as $key => $data) {
                $part = $this->parts->insert($pieceId, $data['name'], $data['description'], $data['position']);
                if ($canManageFiles) {
                    $upload = UploadValidator::extract($uploads, PieceForm::FIELD_NEW_PART_FILE, $key);
                    $errors = [...$errors, ...$this->attach($part, $upload)];
                }
            }
        } catch (RuntimeException $exception) {
            $errors[] = \__('The parts could not be saved completely.', 'orchestra-library');
            $errors[] = $exception->getMessage();
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $upload
     * @return list<string>
     */
    private function attach(Part $part, ?array $upload): array
    {
        if ($upload === null) {
            return [];
        }

        try {
            $this->fileService->attachToPart($part, $this->uploads->validate($upload), \get_current_user_id());
        } catch (UploadRejected $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed> $uploads
     * @return list<string>
     */
    private function savePieceFiles(int $pieceId, array $input, array $uploads): array
    {
        $errors = [];
        $toDelete = \is_array($input[PieceForm::FIELD_DELETE_PIECE_FILES] ?? null)
            ? self::ids($input[PieceForm::FIELD_DELETE_PIECE_FILES])
            : [];
        foreach ($this->files->pieceFiles($pieceId) as $file) {
            if (\in_array($file->id, $toDelete, true)) {
                $this->fileService->delete($file);
            }
        }

        foreach (UploadValidator::keys($uploads, PieceForm::FIELD_PIECE_FILES) as $key) {
            $upload = UploadValidator::extract($uploads, PieceForm::FIELD_PIECE_FILES, $key);
            if ($upload === null) {
                continue;
            }

            try {
                $this->fileService->attachToPiece($pieceId, $this->uploads->validate($upload), \get_current_user_id());
            } catch (UploadRejected | RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param array<mixed> $values
     * @return list<int>
     */
    private static function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (\is_string($value) && \ctype_digit($value)) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * @param array<mixed> $values
     */
    private static function string(array $values, string $key): string
    {
        return \is_string($values[$key] ?? null) ? $values[$key] : '';
    }

    /**
     * @param array<mixed> $values
     */
    private static function int(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? null;

        return \is_string($value) && \is_numeric($value) ? (int) $value : $default;
    }
}
