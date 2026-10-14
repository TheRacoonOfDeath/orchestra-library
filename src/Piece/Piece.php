<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

final class Piece
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $composer,
        public readonly string $description,
        public readonly bool $archived,
        public readonly bool $published,
    ) {
    }
}
