<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Library\Capabilities;
use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\File\UploadValidator;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartNames;
use Orchestra\Library\Part\PartRepository;

/**
 * The fields for editing a piece: details, parts with sheet music, and piece files. Rendered in the
 * meta boxes of the piece editor in wp-admin and on the library page; saved by {@see PieceFormHandler}.
 * The surrounding form must use `multipart/form-data`.
 */
final class PieceForm
{
    public const FIELD_COMPOSER = 'orchestra_library_composer';
    public const FIELD_ARCHIVED = 'orchestra_library_archived';
    public const FIELD_PARTS = 'orchestra_parts';
    public const FIELD_NEW_PARTS = 'orchestra_new_parts';
    /** The keys of the part rows in display order: part IDs, or "new:INDEX" for new rows. */
    public const FIELD_PART_ORDER = 'orchestra_part_order';
    public const NEW_PART_KEY_PREFIX = 'new:';
    public const FIELD_PART_FILE = 'orchestra_part_file';
    public const FIELD_NEW_PART_FILE = 'orchestra_new_part_file';
    public const FIELD_PIECE_FILES = 'orchestra_piece_files';
    public const FIELD_DELETE_PIECE_FILES = 'orchestra_delete_piece_files';

    public function __construct(
        private readonly PartRepository $parts,
        private readonly FileRepository $files,
    ) {
    }

    public function details(?Piece $piece): string
    {
        return '<div class="orchestra-stack">'
            . Html::field(
                'orchestra-library-composer',
                \__('Composer', 'orchestra-library'),
                '<input type="text" id="orchestra-library-composer" name="' . self::FIELD_COMPOSER . '" value="'
                    . \esc_attr($piece->composer ?? '') . '">'
            )
            . '<label class="orchestra-checkbox"><input type="checkbox" name="' . self::FIELD_ARCHIVED . '" value="1"'
            . \checked($piece->archived ?? false, true, false) . '> <span>'
            . \esc_html__('Archived', 'orchestra-library')
            . '</span></label><p class="orchestra-muted">' . \esc_html__(
                'Archived pieces are hidden in the library and cannot be added to new projects.',
                'orchestra-library'
            ) . '</p></div>';
    }

    public function parts(int $pieceId): string
    {
        $parts = $pieceId > 0 ? $this->parts->forPiece($pieceId) : [];
        $sheetMusic = $this->files->forParts(\array_map(static fn (Part $part): int => $part->id, $parts));
        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);

        $headings = [
            '',
            \__('Name', 'orchestra-library'),
            \__('Description', 'orchestra-library'),
            \__('Sheet music', 'orchestra-library'),
            \__('Delete', 'orchestra-library'),
        ];
        $html = '<div class="orchestra-table-wrap"><table class="orchestra-table orchestra-table--stacked"><thead><tr>';
        foreach ($headings as $heading) {
            $html .= '<th scope="col">' . \esc_html($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody id="orchestra-library-part-rows" data-orchestra-sortable>';

        foreach ($parts as $part) {
            $field = self::FIELD_PARTS . '[' . $part->id . ']';
            $file = $sheetMusic[$part->id] ?? null;
            $fileCell = '';
            if ($file !== null) {
                $fileCell .= '<span>' . self::fileLink($file) . '</span>';
                if ($canManageFiles) {
                    $fileCell .= '<label class="orchestra-checkbox"><input type="checkbox" value="1" name="'
                        . \esc_attr($field) . '[remove_file]"> <span>' . \esc_html__('Remove', 'orchestra-library')
                        . '</span></label>';
                }
            }
            if ($canManageFiles) {
                $label = $file === null
                    ? \__('Upload sheet music', 'orchestra-library')
                    : \__('Replace sheet music', 'orchestra-library');
                $fileCell .= self::fileInput(self::FIELD_PART_FILE . '[' . $part->id . ']', $label);
            }

            $html .= $this->row(
                $headings,
                self::dragHandle((string) $part->id, $part->name),
                '<input type="text" name="' . \esc_attr($field) . '[name]" required maxlength="' . PartNames::MAX_LENGTH
                    . '" value="' . \esc_attr($part->name) . '" aria-label="'
                    . \esc_attr__('Part name', 'orchestra-library') . '">',
                '<input type="text" name="' . \esc_attr($field) . '[description]" value="'
                    . \esc_attr($part->description)
                    . '" aria-label="' . \esc_attr__('Description', 'orchestra-library') . '">',
                $fileCell,
                '<label class="orchestra-checkbox"><input type="checkbox" name="' . \esc_attr($field)
                    . '[delete]" value="1"> <span>'
                    . \esc_html__('Delete part', 'orchestra-library') . '</span></label>'
            );
        }

        $html .= $this->newRow($headings, '0', $canManageFiles) . '</tbody></table></div>';
        $html .= '<template id="orchestra-library-new-part">'
            . $this->newRow($headings, '__INDEX__', $canManageFiles) . '</template>';

        return $html
            . '<div class="orchestra-actions"><button type="button" class="orchestra-button orchestra-button--small"'
            . ' data-orchestra-add-row="orchestra-library-new-part"'
            . ' data-orchestra-target="orchestra-library-part-rows">'
            . \esc_html__('+ Add part', 'orchestra-library') . '</button><span class="orchestra-muted">' . \esc_html__(
                'Drag the parts by their handle to change the order. Every part has one sheet music file;'
                    . ' uploading a new file replaces the old one.',
                'orchestra-library'
            ) . '</span></div>';
    }

    public function pieceFiles(int $pieceId): string
    {
        $files = $pieceId > 0 ? $this->files->pieceFiles($pieceId) : [];
        $canManageFiles = \current_user_can(Capabilities::SHEET_MUSIC_MANAGE);

        $html = '<p class="orchestra-muted">' . \esc_html__(
            'Files for the whole piece, e.g. the full score or performance notes.',
            'orchestra-library'
        ) . '</p>';
        if ($files !== []) {
            $html .= '<ul class="orchestra-list">';
            foreach ($files as $file) {
                $html .= '<li><span>' . self::fileLink($file) . '</span>';
                if ($canManageFiles) {
                    $html .= '<label class="orchestra-checkbox"><input type="checkbox" name="'
                        . self::FIELD_DELETE_PIECE_FILES
                        . '[]" value="' . $file->id . '"> <span>' . \esc_html__('Delete', 'orchestra-library')
                        . '</span></label>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        if ($canManageFiles) {
            $html .= '<input type="file" name="' . self::FIELD_PIECE_FILES . '[]" multiple accept="'
                . \esc_attr(UploadValidator::acceptAttribute()) . '" aria-label="'
                . \esc_attr__('Upload piece files', 'orchestra-library') . '">';
        }

        return $html;
    }

    /**
     * @param list<Part> $parts
     */
    public static function nextPosition(array $parts): int
    {
        return $parts === [] ? 1 : \max(\array_map(static fn (Part $part): int => $part->position, $parts)) + 1;
    }

    public static function fileLink(LibraryFile $file): string
    {
        return '<a href="' . \esc_url(DownloadController::fileUrl($file->id)) . '">' . \esc_html($file->originalName)
            . '</a> <span class="orchestra-muted">(' . \esc_html(\size_format($file->size) ?: '') . ')</span>';
    }

    /**
     * @param list<string> $headings
     */
    private function newRow(array $headings, string $key, bool $canManageFiles): string
    {
        $field = self::FIELD_NEW_PARTS . '[' . $key . ']';

        return $this->row(
            $headings,
            self::dragHandle(self::NEW_PART_KEY_PREFIX . $key, \__('New part', 'orchestra-library')),
            '<input type="text" name="' . \esc_attr($field) . '[name]" maxlength="' . PartNames::MAX_LENGTH
                . '" placeholder="'
                . \esc_attr__('New part, e.g. Horn 1', 'orchestra-library') . '" aria-label="'
                . \esc_attr__('Name of the new part', 'orchestra-library') . '">',
            '<input type="text" name="' . \esc_attr($field) . '[description]" aria-label="'
                . \esc_attr__('Description', 'orchestra-library') . '">',
            $canManageFiles
                ? self::fileInput(self::FIELD_NEW_PART_FILE . '[' . $key
                    . ']', \__('Upload sheet music', 'orchestra-library'))
                : '',
            '<button type="button" class="orchestra-button orchestra-button--small" data-orchestra-remove-row>'
                . \esc_html__('Remove', 'orchestra-library') . '</button>'
        );
    }

    /**
     * @param list<string> $headings
     */
    private function row(array $headings, string ...$cells): string
    {
        $html = '<tr>';
        foreach ($cells as $index => $cell) {
            $html .= '<td data-label="' . \esc_attr($headings[$index] ?? '') . '"><div class="orchestra-cell">' . $cell
                . '</div></td>';
        }

        return $html . '</tr>';
    }

    /**
     * The handle for dragging a row; it also submits the row's place in the order.
     */
    private static function dragHandle(string $key, string $name): string
    {
        /* translators: %s: part name */
        $move = \sprintf(\__('Move "%s"', 'orchestra-library'), $name);

        return Form::hidden(self::FIELD_PART_ORDER . '[]', $key)
            . '<button type="button" class="orchestra-drag-handle" data-orchestra-drag-handle aria-label="'
            . \esc_attr($move) . '" title="'
            . \esc_attr__('Drag to change the order, or use the arrow keys', 'orchestra-library') . '">'
            . '<span aria-hidden="true">&#8801;</span></button>';
    }

    private static function fileInput(string $name, string $label): string
    {
        return '<input type="file" name="' . \esc_attr($name) . '" accept="'
            . \esc_attr(UploadValidator::acceptAttribute())
            . '" aria-label="' . \esc_attr($label) . '">';
    }
}
