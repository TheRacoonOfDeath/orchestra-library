<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

use RuntimeException;

/**
 * Turns the free-text part names of versions before 0.5 into voices: names like "Solo Gitarre 1"
 * get their instrument, solo and number; all others keep their name as free text.
 */
final class VoiceMigration
{
    public function __construct(private readonly PartRepository $parts, private readonly Instruments $instruments)
    {
    }

    public function run(): void
    {
        $instruments = $this->instruments->all();
        $byId = [];
        foreach ($instruments as $instrument) {
            $byId[$instrument->id] = $instrument;
        }

        foreach ($this->parts->all() as $part) {
            if ($part->instrumentId > 0 || $part->note !== '') {
                continue;
            }

            $voice = Voice::parse($part->name, $instruments);
            $label = $voice->label($byId[$voice->instrumentId] ?? null);
            try {
                $this->parts->update($part->withVoice($voice, $label, $part->position));
            } catch (RuntimeException) {
                // The recognised label collides with another part: keep the name as free text.
                $freeText = new Voice(0, false, 0, $part->name);
                $this->parts->update($part->withVoice($freeText, $part->name, $part->position));
            }
        }
    }
}
