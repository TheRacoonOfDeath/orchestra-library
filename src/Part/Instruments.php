<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * The instruments that voices are built from, stored in an option. Until the conductors change
 * them, the defaults apply.
 */
final class Instruments
{
    public const OPTION = 'orchestra_library_instruments';

    /** The instruments of a new installation. They are data, not translated. */
    private const DEFAULTS = [1 => 'Bass', 2 => 'Gitarre', 3 => 'Oktavgitarre', 4 => 'Quartgitarre'];

    /**
     * @return list<Instrument> sorted by name
     */
    public function all(): array
    {
        $stored = \get_option(self::OPTION, null);
        if (!\is_array($stored)) {
            $instruments = [];
            foreach (self::DEFAULTS as $id => $name) {
                $instruments[] = new Instrument($id, $name);
            }

            return self::sort($instruments);
        }

        $instruments = [];
        foreach ($stored as $id => $name) {
            if (\is_int($id) && $id > 0 && \is_string($name) && $name !== '') {
                $instruments[] = new Instrument($id, $name);
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
            $stored[$instrument->id] = $instrument->name;
        }
        \update_option(self::OPTION, $stored, false);
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
