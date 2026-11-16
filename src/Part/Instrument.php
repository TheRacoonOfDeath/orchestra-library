<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * An instrument that voices are built from, e.g. "Gitarre". Maintained by the conductors.
 */
final class Instrument
{
    public const NAME_MAX_LENGTH = 60;
    public const SHORT_NAME_MAX_LENGTH = 20;

    /**
     * @param string $shortName Since 0.11: shown where space is short, e.g. "Git"; "" for none
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $shortName = '',
    ) {
    }

    /**
     * The short name, or the name if the instrument has none.
     */
    public function shortOrName(): string
    {
        return $this->shortName === '' ? $this->name : $this->shortName;
    }
}
