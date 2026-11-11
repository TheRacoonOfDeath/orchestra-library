<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use Orchestra\Library\Capabilities;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileService;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\File\UploadRejected;
use Orchestra\Library\File\UploadValidator;
use Orchestra\Library\Part\Instrument;
use Orchestra\Library\Part\Instruments;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartNames;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Part\PartService;
use Orchestra\Library\Part\Voice;
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
        private readonly Instruments $instruments,
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
        $existingNames = [];
        foreach ($existing as $part) {
            $existingNames[$part->id] = $part->name;
        }
        $submitted = \is_array($input[PieceForm::FIELD_PARTS] ?? null) ? $input[PieceForm::FIELD_PARTS] : [];
        // Positions follow the order of the rows; rows missing from the order go after the ordered ones.
        $order = \is_array($input[PieceForm::FIELD_PART_ORDER] ?? null)
            ? \array_values(\array_filter($input[PieceForm::FIELD_PART_ORDER], 'is_string'))
            : [];
        $unordered = \count($order);
        $position = static function (string $key) use ($order, &$unordered): int {
            $index = \array_search($key, $order, true);

            return ($index === false ? $unordered++ : $index) + 1;
        };
        $nextPosition = PieceForm::nextPosition($existing);
        $instruments = [];
        foreach ($this->instruments->all() as $instrument) {
            $instruments[$instrument->id] = $instrument;
        }

        $names = [];
        $voiceErrors = [];
        $toDelete = [];
        $toUpdate = [];
        $deleteButton = self::string($input, PieceForm::FIELD_DELETE_PART);
        $removeFileButton = self::string($input, PieceForm::FIELD_REMOVE_PART_FILE);
        foreach ($existing as $part) {
            $row = $submitted[$part->id] ?? null;
            $delete = $deleteButton === (string) $part->id || (\is_array($row) && !empty($row['delete']));
            $removeFile = $removeFileButton === (string) $part->id || (\is_array($row) && !empty($row['remove_file']));
            if (!\is_array($row) && !$delete && !$removeFile) {
                $names[] = PartNames::normalize($part->name);
                continue;
            }

            if ($delete) {
                $blockers = $this->partService->partDeleteBlockers($part->id);
                if ($blockers === []) {
                    $toDelete[] = $part;
                    continue;
                }
                /* translators: 1: part name, 2: reasons */
                $message = \__('The part "%1$s" cannot be deleted: %2$s', 'orchestra-library');
                $errors[] = \sprintf($message, $part->name, \implode(' ', $blockers));
            }

            $voice = \is_array($row) ? self::voice($row, $instruments, $voiceErrors) : $part->voice();
            $name = $voice->label($instruments[$voice->instrumentId] ?? null);
            $names[] = $name;
            $toUpdate[] = [
                // Parts have no description in the editor any more; a stored one is kept.
                'part' => $part->withVoice(
                    $voice,
                    $name,
                    $order === [] ? $part->position : $position((string) $part->id)
                ),
                'removeFile' => $removeFile,
            ];
        }

        $toInsert = [];
        $newRows = \is_array($input[PieceForm::FIELD_NEW_PARTS] ?? null) ? $input[PieceForm::FIELD_NEW_PARTS] : [];
        foreach ($newRows as $key => $row) {
            if (!\is_array($row)) {
                continue;
            }
            $voice = self::voice($row, $instruments, $voiceErrors);
            if ($voice->instrumentId === 0 && $voice->note === '') {
                continue;
            }
            $name = $voice->label($instruments[$voice->instrumentId] ?? null);
            $names[] = $name;
            $toInsert[$key] = [
                'voice' => $voice,
                'name' => $name,
                'description' => '',
                'position' => $order === [] ? $nextPosition++ : $position(PieceForm::NEW_PART_KEY_PREFIX . $key),
            ];
        }

        $nameErrors = [...\array_values(\array_unique($voiceErrors)), ...PartNames::validate($names)];
        if ($nameErrors !== []) {
            return [...$errors, \__('The parts were not saved.', 'orchestra-library'), ...$nameErrors];
        }

        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);
        try {
            foreach ($toDelete as $part) {
                $errors = [...$errors, ...$this->partService->deletePart($part)];
            }
            // New names first, all at once, so that names moving between parts do not collide.
            $renamed = [];
            foreach ($toUpdate as ['part' => $part]) {
                if ($part->name !== ($existingNames[$part->id] ?? null)) {
                    $renamed[$part->id] = $part->name;
                }
            }
            $this->parts->rename($renamed);
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
                $part = $this->parts->insert(
                    $pieceId,
                    $data['voice'],
                    $data['name'],
                    $data['description'],
                    $data['position']
                );
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
     * The voice of a submitted part row. An unknown instrument or number adds an error.
     *
     * @param array<mixed> $row
     * @param array<int, Instrument> $instruments
     * @param list<string> $errors
     */
    private static function voice(array $row, array $instruments, array &$errors): Voice
    {
        $instrumentId = \absint(self::string($row, 'instrument'));
        if ($instrumentId > 0 && !isset($instruments[$instrumentId])) {
            $errors[] = \__('An instrument no longer exists. Please choose another one.', 'orchestra-library');
            $instrumentId = 0;
        }
        $number = \absint(self::string($row, 'number'));
        if ($number > Voice::MAX_NUMBER) {
            $number = 0;
        }

        return new Voice(
            $instrumentId,
            !empty($row['solo']),
            $number,
            PartNames::normalize(\sanitize_text_field(self::string($row, 'note')))
        );
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
            $this->fileService->attachToPart($part, $this->uploads->validate($upload));
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
        // The delete button of a single file.
        $toDelete[] = \absint(self::string($input, PieceForm::FIELD_DELETE_PIECE_FILE));
        foreach ($this->files->pieceFiles($pieceId) as $file) {
            if (\in_array($file->id, $toDelete, true)) {
                $this->fileService->delete($file);
            }
        }

        $names = \is_array($input[PieceForm::FIELD_PIECE_FILE_NAMES] ?? null)
            ? $input[PieceForm::FIELD_PIECE_FILE_NAMES]
            : [];
        foreach ($this->files->pieceFiles($pieceId) as $file) {
            if (\in_array($file->id, $toDelete, true) || !\is_string($names[$file->id] ?? null)) {
                continue;
            }
            $name = self::displayName($names[$file->id]);
            if ($name === '') {
                /* translators: %s: name of a piece file */
                $message = \__('Every piece file needs a name. "%s" keeps its name.', 'orchestra-library');
                $errors[] = \sprintf($message, $file->displayName);
            } elseif ($name !== $file->displayName) {
                $this->files->rename($file->id, $name);
            }
        }

        $newNames = \is_array($input[PieceForm::FIELD_NEW_PIECE_FILES] ?? null)
            ? $input[PieceForm::FIELD_NEW_PIECE_FILES]
            : [];
        $keys = \array_unique([
            ...\array_keys($newNames),
            ...UploadValidator::keys($uploads, PieceForm::FIELD_NEW_PIECE_FILE),
        ]);
        foreach ($keys as $key) {
            $name = self::displayName(\is_string($newNames[$key] ?? null) ? $newNames[$key] : '');
            $upload = UploadValidator::extract($uploads, PieceForm::FIELD_NEW_PIECE_FILE, $key);
            if ($upload === null) {
                if ($name !== '') {
                    /* translators: %s: name of a piece file */
                    $errors[] = \sprintf(\__('Choose a file for "%s".', 'orchestra-library'), $name);
                }
                continue;
            }
            if ($name === '') {
                $fileName = \is_string($upload['name']) ? $upload['name'] : '';
                /* translators: %s: file name */
                $message = \__('Enter a name for the file "%s". It was not uploaded.', 'orchestra-library');
                $errors[] = \sprintf($message, \sanitize_file_name($fileName));
                continue;
            }

            try {
                $this->fileService->attachToPiece(
                    $pieceId,
                    $this->uploads->validate($upload),
                    $name
                );
            } catch (UploadRejected | RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return $errors;
    }

    private static function displayName(string $name): string
    {
        return \mb_substr(PartNames::normalize(\sanitize_text_field($name)), 0, LibraryFile::MAX_DISPLAY_NAME_LENGTH);
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
}
