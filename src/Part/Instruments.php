<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * The instruments that voices are built from, stored in an option as `ID => ['name' => …,
 * 'short' => …]`. Until the conductors change them, the defaults apply.
 */
final class Instruments
{
    public const OPTION = 'orchestra_library_instruments';

    /** The instruments of a new installation, as name and short name. They are data, not translated. */
    private const DEFAULTS = [
        1 => ['Bass', ''],
        2 => ['Gitarre', 'Git'],
        3 => ['Oktavgitarre', 'Okt'],
        4 => ['Quartgitarre', 'Quart'],
    ];

    /**
     * @return list<Instrument> sorted by name
     */
    public function all(): array
    {
        $stored = \get_option(self::OPTION, null);
        if (!\is_array($stored)) {
            $instruments = [];
            foreach (self::DEFAULTS as $id => [$name, $shortName]) {
                $instruments[] = new Instrument($id, $name, $shortName);
            }

            return self::sort($instruments);
        }

        $instruments = [];
        foreach ($stored as $id => $value) {
            $instrument = \is_int($id) && $id > 0 ? self::fromStored($id, $value) : null;
            if ($instrument !== null) {
                $instruments[] = $instrument;
            }
        }

        return self::sort($instruments);
    }

    public function find(int $id): ?Instrument
    {
        foreach ($this->all() as $instrument) {
            if ($instrument->id === $id) {
                return $instrument;
            }
        }

        return null;
    }

    /**
     * @param list<Instrument> $instruments
     */
    public function save(array $instruments): void
    {
        $stored = [];
        foreach ($instruments as $instrument) {
            $stored[$instrument->id] = ['name' => $instrument->name, 'short' => $instrument->shortName];
        }
        \update_option(self::OPTION, $stored, false);
    }

    /**
     * Up to 0.10 the option held only the names. Those instruments get the short name of the
     * default instrument with the same name, if there is one.
     */
    private static function fromStored(int $id, mixed $value): ?Instrument
    {
        if (\is_string($value)) {
            $shortName = '';
            foreach (self::DEFAULTS as [$name, $defaultShortName]) {
                if ($name === $value) {
                    $shortName = $defaultShortName;
                }
            }

            return $value === '' ? null : new Instrument($id, $value, $shortName);
        }
        if (!\is_array($value) || !\is_string($value['name'] ?? null) || $value['name'] === '') {
            return null;
        }

        return new Instrument($id, $value['name'], \is_string($value['short'] ?? null) ? $value['short'] : '');
    }

    /**
     * Alphabetically, ignoring case.
     *
     * @param list<Instrument> $instruments
     * @return list<Instrument>
     */
    public static function sort(array $instruments): array
    {
        \usort(
            $instruments,
            static fn (Instrument $a, Instrument $b): int => [\mb_strtolower($a->name), $a->id]
                <=> [\mb_strtolower($b->name), $b->id]
        );

        return $instruments;
    }

    /**
     * @param list<Instrument> $instruments
     */
    public static function nextId(array $instruments): int
    {
        return $instruments === [] ? 1 : \max(\array_map(static fn (Instrument $i): int => $i->id, $instruments)) + 1;
    }
}
