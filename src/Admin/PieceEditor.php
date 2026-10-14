<?php

declare(strict_types=1);

namespace Orchestra\Library\Admin;

use Orchestra\Library\Capabilities;
use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileService;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\File\UploadRejected;
use Orchestra\Library\File\UploadValidator;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartNames;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Part\PartService;
use Orchestra\Library\Piece\PiecePostType;
use Orchestra\Library\Piece\PieceRepository;
use RuntimeException;
use WP_Post;

/**
 * The meta boxes on the piece edit screen: details, parts with their sheet music, and piece files.
 *
 * Everything is saved together with the piece when the post form is submitted.
 */
final class PieceEditor
{
    private const NONCE_ACTION = 'orchestra_library_save_piece';
    private const NONCE_FIELD = 'orchestra_library_piece_nonce';
    private const NEW_PART_ROWS = 5;

    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PartRepository $parts,
        private readonly PartService $partService,
        private readonly FileRepository $files,
        private readonly FileService $fileService,
        private readonly UploadValidator $uploads,
        private readonly Notices $notices,
    ) {
    }

    public function register(): void
    {
        \add_action('add_meta_boxes_' . PiecePostType::NAME, [$this, 'addMetaBoxes']);
        \add_action('post_edit_form_tag', [$this, 'addMultipartEncoding']);
        \add_action('save_post_' . PiecePostType::NAME, [$this, 'save'], 10, 2);
    }

    /**
     * @internal Hooked to `post_edit_form_tag`. File inputs need a multipart form.
     */
    public function addMultipartEncoding(WP_Post $post): void
    {
        if ($post->post_type === PiecePostType::NAME) {
            echo ' enctype="multipart/form-data"';
        }
    }

    /**
     * @internal Hooked to `add_meta_boxes_orchestra_piece`.
     */
    public function addMetaBoxes(): void
    {
        \add_meta_box(
            'orchestra-library-details',
            \__('Details', 'orchestra-library'),
            [$this, 'renderDetails'],
            PiecePostType::NAME,
            'side',
            'high'
        );
        \add_meta_box(
            'orchestra-library-parts',
            \__('Parts and Sheet Music', 'orchestra-library'),
            [$this, 'renderParts'],
            PiecePostType::NAME,
            'normal',
            'high'
        );
        \add_meta_box(
            'orchestra-library-piece-files',
            \__('Piece Files', 'orchestra-library'),
            [$this, 'renderPieceFiles'],
            PiecePostType::NAME,
            'normal'
        );
    }

    /**
     * @internal Meta box callback.
     */
    public function renderDetails(WP_Post $post): void
    {
        $piece = $this->pieces->find($post->ID);
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        <p>
            <label for="orchestra-library-composer">
                <strong><?php \esc_html_e('Composer', 'orchestra-library'); ?></strong>
            </label>
            <input type="text" id="orchestra-library-composer" name="orchestra_library_composer" class="widefat"
                   value="<?php echo \esc_attr($piece->composer ?? ''); ?>">
        </p>
        <p>
            <label>
                <input type="checkbox" name="orchestra_library_archived" value="1"
                    <?php \checked($piece->archived ?? false); ?>>
                <?php \esc_html_e('Archived', 'orchestra-library'); ?>
            </label>
        </p>
        <p class="description">
            <?php \esc_html_e(
                'Archived pieces are hidden in the library and cannot be added to new projects.',
                'orchestra-library'
            ); ?>
        </p>
        <?php
    }

    /**
     * @internal Meta box callback.
     */
    public function renderParts(WP_Post $post): void
    {
        $parts = $this->parts->forPiece($post->ID);
        $sheetMusic = $this->files->forParts(\array_map(static fn (Part $part): int => $part->id, $parts));
        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);
        $accept = UploadValidator::acceptAttribute();
        $nextPosition = self::nextPosition($parts);
        ?>
        <table class="widefat striped orchestra-library-parts">
            <thead>
            <tr>
                <th style="width: 5em;"><?php \esc_html_e('Order', 'orchestra-library'); ?></th>
                <th><?php \esc_html_e('Name', 'orchestra-library'); ?></th>
                <th><?php \esc_html_e('Description', 'orchestra-library'); ?></th>
                <th><?php \esc_html_e('Sheet music', 'orchestra-library'); ?></th>
                <th style="width: 5em;"><?php \esc_html_e('Delete', 'orchestra-library'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($parts as $part) :
                $field = 'orchestra_parts[' . $part->id . ']';
                $file = $sheetMusic[$part->id] ?? null;
                ?>
                <tr>
                    <td><input type="number" name="<?php echo \esc_attr($field); ?>[position]" class="small-text"
                               value="<?php echo \esc_attr((string) $part->position); ?>"></td>
                    <td><input type="text" name="<?php echo \esc_attr($field); ?>[name]" class="widefat" required
                               maxlength="<?php echo \esc_attr((string) PartNames::MAX_LENGTH); ?>"
                               value="<?php echo \esc_attr($part->name); ?>"></td>
                    <td><input type="text" name="<?php echo \esc_attr($field); ?>[description]" class="widefat"
                               value="<?php echo \esc_attr($part->description); ?>"></td>
                    <td>
                        <?php if ($file !== null) : ?>
                            <?php self::renderFileLink($file); ?>
                            <?php if ($canManageFiles) : ?>
                                <br><label>
                                    <input type="checkbox" value="1"
                                           name="<?php echo \esc_attr($field); ?>[remove_file]">
                                    <?php \esc_html_e('Remove', 'orchestra-library'); ?>
                                </label>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canManageFiles) : ?>
                            <br><input type="file"
                                       name="orchestra_part_file[<?php echo \esc_attr((string) $part->id); ?>]"
                                       accept="<?php echo \esc_attr($accept); ?>"
                                       aria-label="<?php echo \esc_attr($file === null
                                           ? \__('Upload sheet music', 'orchestra-library')
                                           : \__('Replace sheet music', 'orchestra-library')); ?>">
                        <?php endif; ?>
                    </td>
                    <td><input type="checkbox" name="<?php echo \esc_attr($field); ?>[delete]" value="1"
                               aria-label="<?php \esc_attr_e('Delete part', 'orchestra-library'); ?>"></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($row = 0; $row < self::NEW_PART_ROWS; $row++) :
                $field = 'orchestra_new_parts[' . $row . ']';
                ?>
                <tr class="orchestra-library-new-part">
                    <td><input type="number" name="<?php echo \esc_attr($field); ?>[position]" class="small-text"
                               value="<?php echo \esc_attr((string) ($nextPosition + $row)); ?>"></td>
                    <td><input type="text" name="<?php echo \esc_attr($field); ?>[name]" class="widefat"
                               maxlength="<?php echo \esc_attr((string) PartNames::MAX_LENGTH); ?>"
                               placeholder="<?php \esc_attr_e('New part, e.g. Horn 1', 'orchestra-library'); ?>"></td>
                    <td><input type="text" name="<?php echo \esc_attr($field); ?>[description]" class="widefat"></td>
                    <td>
                        <?php if ($canManageFiles) : ?>
                            <input type="file" name="orchestra_new_part_file[<?php echo \esc_attr((string) $row); ?>]"
                                   accept="<?php echo \esc_attr($accept); ?>"
                                   aria-label="<?php \esc_attr_e('Upload sheet music', 'orchestra-library'); ?>">
                        <?php endif; ?>
                    </td>
                    <td></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        <p class="description">
            <?php \esc_html_e(
                'Every part has one sheet music file; uploading a new file replaces the old one. '
                . 'Save the piece to add more parts.',
                'orchestra-library'
            ); ?>
        </p>
        <?php
    }

    /**
     * @internal Meta box callback.
     */
    public function renderPieceFiles(WP_Post $post): void
    {
        $pieceFiles = $this->files->pieceFiles($post->ID);
        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);
        ?>
        <p class="description">
            <?php \esc_html_e(
                'Files for the whole piece, e.g. the full score or performance notes.',
                'orchestra-library'
            ); ?>
        </p>
        <?php if ($pieceFiles !== []) : ?>
            <ul>
                <?php foreach ($pieceFiles as $file) : ?>
                    <li>
                        <?php self::renderFileLink($file); ?>
                        <?php if ($canManageFiles) : ?>
                            <label>
                                <input type="checkbox" name="orchestra_delete_piece_files[]"
                                       value="<?php echo \esc_attr((string) $file->id); ?>">
                                <?php \esc_html_e('Delete', 'orchestra-library'); ?>
                            </label>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageFiles) : ?>
            <p>
                <input type="file" name="orchestra_piece_files[]" multiple
                       accept="<?php echo \esc_attr(UploadValidator::acceptAttribute()); ?>"
                       aria-label="<?php \esc_attr_e('Upload piece files', 'orchestra-library'); ?>">
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * @internal Hooked to `save_post_orchestra_piece`.
     */
    public function save(int $pieceId, WP_Post $post): void
    {
        $nonce = $_POST[self::NONCE_FIELD] ?? null;
        if (
            (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || \wp_is_post_revision($pieceId) !== false
            || !\is_string($nonce)
            || \wp_verify_nonce(\wp_unslash($nonce), self::NONCE_ACTION) === false
            || !\current_user_can('edit_post', $pieceId)
        ) {
            return;
        }

        /** @var array<mixed> $input */
        $input = \wp_unslash($_POST);

        $this->pieces->setComposer($pieceId, self::string($input, 'orchestra_library_composer'));
        $this->pieces->setArchived($pieceId, !empty($input['orchestra_library_archived']));

        $this->saveParts($pieceId, $input);
        if (\current_user_can(Capabilities::SHEET_MUSIC_MANAGE)) {
            $this->savePieceFiles($pieceId, $input);
        }
    }

    /**
     * @param array<mixed> $input
     */
    private function saveParts(int $pieceId, array $input): void
    {
        $existing = $this->parts->forPiece($pieceId);
        $submitted = \is_array($input['orchestra_parts'] ?? null) ? $input['orchestra_parts'] : [];

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
                $this->notices->error(\sprintf($message, $part->name, \implode(' ', $blockers)));
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
        $nextPosition = self::nextPosition($existing);
        $newRows = \is_array($input['orchestra_new_parts'] ?? null) ? $input['orchestra_new_parts'] : [];
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

        $errors = PartNames::validate($names);
        if ($errors !== []) {
            $this->notices->error(\__('The parts were not saved.', 'orchestra-library'));
            $this->notices->errors($errors);
            return;
        }

        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);

        try {
            foreach ($toDelete as $part) {
                $this->notices->errors($this->partService->deletePart($part));
            }
            foreach ($toUpdate as ['part' => $part, 'removeFile' => $removeFile]) {
                $this->parts->update($part);
                if ($canManageFiles && $removeFile) {
                    $this->fileService->deleteForPart($part->id);
                }
                if ($canManageFiles) {
                    $this->attachUpload($part, UploadValidator::extract($_FILES, 'orchestra_part_file', $part->id));
                }
            }
            foreach ($toInsert as $key => $data) {
                $part = $this->parts->insert($pieceId, $data['name'], $data['description'], $data['position']);
                if ($canManageFiles) {
                    $this->attachUpload($part, UploadValidator::extract($_FILES, 'orchestra_new_part_file', $key));
                }
            }
        } catch (RuntimeException $exception) {
            $this->notices->error(\__('The parts could not be saved completely.', 'orchestra-library'));
            $this->notices->error($exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed>|null $upload
     */
    private function attachUpload(Part $part, ?array $upload): void
    {
        if ($upload === null) {
            return;
        }

        try {
            $this->fileService->attachToPart($part, $this->uploads->validate($upload), \get_current_user_id());
        } catch (UploadRejected $exception) {
            $this->notices->error($exception->getMessage());
        }
    }

    /**
     * @param array<mixed> $input
     */
    private function savePieceFiles(int $pieceId, array $input): void
    {
        $toDelete = \is_array($input['orchestra_delete_piece_files'] ?? null)
            ? self::ids($input['orchestra_delete_piece_files'])
            : [];
        foreach ($this->files->pieceFiles($pieceId) as $file) {
            if (\in_array($file->id, $toDelete, true)) {
                $this->fileService->delete($file);
            }
        }

        foreach (UploadValidator::keys($_FILES, 'orchestra_piece_files') as $key) {
            $upload = UploadValidator::extract($_FILES, 'orchestra_piece_files', $key);
            if ($upload === null) {
                continue;
            }

            try {
                $this->fileService->attachToPiece($pieceId, $this->uploads->validate($upload), \get_current_user_id());
            } catch (UploadRejected $exception) {
                $this->notices->error($exception->getMessage());
            } catch (RuntimeException $exception) {
                $this->notices->error($exception->getMessage());
            }
        }
    }

    private static function renderFileLink(LibraryFile $file): void
    {
        \printf(
            '<a href="%s">%s</a> <span class="description">(%s)</span>',
            \esc_url(DownloadController::fileUrl($file->id)),
            \esc_html($file->originalName),
            \esc_html(\size_format($file->size) ?: '')
        );
    }

    /**
     * @param list<Part> $parts
     */
    private static function nextPosition(array $parts): int
    {
        return $parts === [] ? 1 : \max(\array_map(static fn (Part $part): int => $part->position, $parts)) + 1;
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
