<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * Reads and validates the form in which the conductors change the instruments.
 */
final class InstrumentsInput
{
    public const FIELD_INSTRUMENTS = 'orchestra_instruments';
    public const FIELD_NEW_INSTRUMENTS = 'orchestra_new_instruments';
    /** The delete button of an instrument submits its ID in this field. */
    public const FIELD_DELETE = 'orchestra_delete_instrument';

    /**
     * @param list<Instrument> $instruments All instruments after saving, sorted by name
     * @param list<Instrument> $renamed Instruments whose name changed, with the new name
     * @param list<string> $errors Nothing is saved if there are any
     */
    private function __construct(
        public readonly array $instruments,
        public readonly array $renamed,
        public readonly array $errors,
    ) {
    }

    /**
     * @param array<mixed> $input Unslashed form data
     * @param list<Instrument> $current
     * @param array<int, int> $usage Instrument ID => number of voices that use it
     */
    public static function fromForm(array $input, array $current, array $usage): self
    {
        $submitted = \is_array($input[self::FIELD_INSTRUMENTS] ?? null) ? $input[self::FIELD_INSTRUMENTS] : [];
        $delete = \is_string($input[self::FIELD_DELETE] ?? null) ? (int) $input[self::FIELD_DELETE] : 0;

        $instruments = [];
        $renamed = [];
        $errors = [];
        foreach ($current as $instrument) {
            $name = \is_string($submitted[$instrument->id] ?? null)
                ? PartNames::normalize($submitted[$instrument->id])
                : $instrument->name;
            if ($instrument->id === $delete) {
                $used = $usage[$instrument->id] ?? 0;
                if ($used === 0) {
                    continue;
                }
                $errors[] = \sprintf(
                    /* translators: 1: instrument name, 2: number of voices */
                    \_n(
                        'The instrument "%1$s" cannot be deleted: %2$d voice uses it.',
                        'The instrument "%1$s" cannot be deleted: %2$d voices use it.',
                        $used,
                        'orchestra-library'
                    ),
                    $instrument->name,
                    $used
                );
            }
            $changed = new Instrument($instrument->id, $name);
            $instruments[] = $changed;
            if ($name !== $instrument->name) {
                $renamed[] = $changed;
            }
        }

        $nextId = Instruments::nextId($current);
        $newRows = \is_array($input[self::FIELD_NEW_INSTRUMENTS] ?? null) ? $input[self::FIELD_NEW_INSTRUMENTS] : [];
        foreach ($newRows as $row) {
            $name = \is_array($row) && \is_string($row['name'] ?? null) ? PartNames::normalize($row['name']) : '';
            if ($name !== '') {
                $instruments[] = new Instrument($nextId++, $name);
            }
        }

        $seen = [];
        foreach ($instruments as $instrument) {
            if ($instrument->name === '') {
                $errors[] = \__('Instrument names must not be empty.', 'orchestra-library');
                continue;
            }
            if (\mb_strlen($instrument->name) > Instrument::NAME_MAX_LENGTH) {
                /* translators: 1: instrument name, 2: maximum number of characters */
                $message = \__('The instrument name "%1$s" is longer than %2$d characters.', 'orchestra-library');
                $errors[] = \sprintf($message, $instrument->name, Instrument::NAME_MAX_LENGTH);
            }
            $key = \mb_strtolower($instrument->name);
            if (isset($seen[$key])) {
                /* translators: %s: instrument name */
                $message = \__('The instrument "%s" exists more than once.', 'orchestra-library');
                $errors[] = \sprintf($message, $instrument->name);
            }
            $seen[$key] = true;
        }

        return new self(Instruments::sort($instruments), $renamed, \array_values(\array_unique($errors)));
    }
}
