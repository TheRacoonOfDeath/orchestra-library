<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * An instrument that voices are built from, e.g. "Gitarre". Maintained by the conductors.
 */
final class Instrument
{
    public const NAME_MAX_LENGTH = 60;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
