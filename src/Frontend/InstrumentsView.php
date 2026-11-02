<?php

declare(strict_types=1);

namespace Orchestra\Library\Frontend;

use Orchestra\Core\Ui\Form;
use Orchestra\Library\Part\Instrument;
use Orchestra\Library\Part\Instruments;
use Orchestra\Library\Part\InstrumentsInput;
use Orchestra\Library\Part\PartRepository;

/**
 * The form in which conductors maintain the instruments that voices are built from. Shown by
 * `[orchestra_library]` and in wp-admin; saved by {@see InstrumentsController}.
 */
final class InstrumentsView
{
    private const NEW_ROW_TEMPLATE = 'orchestra-library-new-instrument';
    private const ROWS = 'orchestra-library-instrument-rows';

    public function __construct(private readonly Instruments $instruments, private readonly PartRepository $parts)
    {
    }

    public function form(): string
    {
        $usage = $this->parts->countByInstrument();
        $headings = [
            \__('Instrument', 'orchestra-library'),
            \__('Used by', 'orchestra-library'),
            '',
        ];

        $html = '<p class="orchestra-muted">' . \esc_html__(
            'Every voice of a piece is built from one of these instruments. They are listed alphabetically.'
                . ' Renaming an instrument renames its voices in all pieces.',
            'orchestra-library'
        ) . '</p>'
            . Form::open(InstrumentsController::ACTION, InstrumentsController::ACTION, [], [
                'class' => 'orchestra-form',
            ])
            // Enter in a text field saves instead of pressing the first delete button.
            . '<button type="submit" class="orchestra-default-submit" tabindex="-1" aria-hidden="true">'
            . \esc_html__('Save', 'orchestra-library') . '</button>'
            . '<div class="orchestra-table-wrap"><table class="orchestra-table orchestra-table--stacked"><thead><tr>';
        foreach ($headings as $heading) {
            $html .= '<th scope="col">' . \esc_html($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody id="' . self::ROWS . '">';

        foreach ($this->instruments->all() as $instrument) {
            $used = $usage[$instrument->id] ?? 0;
            $html .= self::row($headings, [
                self::nameInput(
                    InstrumentsInput::FIELD_INSTRUMENTS . '[' . $instrument->id . ']',
                    $instrument->name,
                    true
                ),
                \esc_html(\sprintf(
                    /* translators: %d: number of voices */
                    \_n('%d voice', '%d voices', $used, 'orchestra-library'),
                    $used
                )),
                $used === 0 ? self::deleteButton($instrument) : '',
            ]);
        }

        return $html . self::newRow($headings, '0') . '</tbody></table></div>'
            . '<template id="' . self::NEW_ROW_TEMPLATE . '">' . self::newRow($headings, '__INDEX__') . '</template>'
            . '<div class="orchestra-actions"><button type="button" class="orchestra-button orchestra-button--small"'
            . ' data-orchestra-add-row="' . self::NEW_ROW_TEMPLATE . '" data-orchestra-target="' . self::ROWS . '">'
            . \esc_html__('+ Add instrument', 'orchestra-library') . '</button><span class="orchestra-muted">'
            . \esc_html__('Instruments that are used by a voice cannot be deleted.', 'orchestra-library')
            . '</span></div>'
            . '<div class="orchestra-actions">' . Form::submit(\__('Save', 'orchestra-library'), 'primary') . '</div>'
            . '</form>';
    }

    /**
     * @param list<string> $headings
     */
    private static function newRow(array $headings, string $key): string
    {
        return self::row($headings, [
            '<input type="text" name="' . \esc_attr(InstrumentsInput::FIELD_NEW_INSTRUMENTS . '[' . $key . ']')
                . '[name]" maxlength="' . Instrument::NAME_MAX_LENGTH . '" placeholder="'
                . \esc_attr__('New instrument, e.g. Mandoline', 'orchestra-library') . '" aria-label="'
                . \esc_attr__('Name of the new instrument', 'orchestra-library') . '">',
            '',
            '<button type="button" class="orchestra-button orchestra-button--small" data-orchestra-remove-row>'
                . \esc_html__('Remove', 'orchestra-library') . '</button>',
        ]);
    }

    private static function deleteButton(Instrument $instrument): string
    {
        /* translators: %s: instrument name */
        $confirm = \__('Delete the instrument "%s"?', 'orchestra-library');

        return '<button type="submit" class="orchestra-button orchestra-button--small orchestra-button--danger"'
            . ' name="' . InstrumentsInput::FIELD_DELETE . '" value="' . $instrument->id . '" formnovalidate'
            . ' data-orchestra-confirm="' . \esc_attr(\sprintf($confirm, $instrument->name)) . '">'
            . \esc_html__('Delete', 'orchestra-library') . '</button>';
    }

    private static function nameInput(string $name, string $value, bool $required): string
    {
        return '<input type="text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '" maxlength="'
            . Instrument::NAME_MAX_LENGTH . '"' . ($required ? ' required' : '') . ' aria-label="'
            . \esc_attr__('Instrument', 'orchestra-library') . '">';
    }

    /**
     * @param list<string> $headings
     * @param list<string> $cells HTML
     */
    private static function row(array $headings, array $cells): string
    {
        $html = '<tr>';
        foreach ($cells as $index => $cell) {
            $html .= '<td data-label="' . \esc_attr($headings[$index] ?? '') . '"><div class="orchestra-cell">' . $cell
                . '</div></td>';
        }

        return $html . '</tr>';
    }
}
