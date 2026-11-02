<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * A voice of a piece. `name` is the label of its {@see Voice}, e.g. "Solo Gitarre 1 (tief)".
 */
final class Part
{
    public function __construct(
        public readonly int $id,
        public readonly int $pieceId,
        public readonly string $name,
        public readonly string $description,
        public readonly int $position,
        public readonly int $instrumentId = 0,
        public readonly bool $solo = false,
        public readonly int $number = 0,
        public readonly string $note = '',
    ) {
    }

    public function voice(): Voice
    {
        return new Voice($this->instrumentId, $this->solo, $this->number, $this->note);
    }

    public function withVoice(Voice $voice, string $name, int $position): self
    {
        return new self(
            $this->id,
            $this->pieceId,
            $name,
            $this->description,
            $position,
            $voice->instrumentId,
            $voice->solo,
            $voice->number,
            $voice->note,
        );
    }
}
