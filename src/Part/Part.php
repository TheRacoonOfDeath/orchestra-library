<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

final class Part
{
    public function __construct(
        public readonly int $id,
        public readonly int $pieceId,
        public readonly string $name,
        public readonly string $description,
        public readonly int $position,
    ) {
    }
}
