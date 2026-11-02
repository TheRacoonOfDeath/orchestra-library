<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * What a part is: an instrument, whether it is a solo, a number and a free text. Its label, e.g.
 * "Solo Gitarre 1 (tief)", is stored as the part's name, so other plugins only read the name.
 */
final class Voice
{
    public const MAX_NUMBER = 20;
    public const SOLO = 'Solo';

    /**
     * @param int $instrumentId 0 for none (parts from before 0.5 that could not be recognised)
     * @param int $number 0 for none, otherwise 1 to {@see MAX_NUMBER}
     */
    public function __construct(
        public readonly int $instrumentId,
        public readonly bool $solo,
        public readonly int $number,
        public readonly string $note,
    ) {
    }

    /**
     * "Solo Gitarre 1 (tief)"; empty parts are left out. Without anything but the free text, the
     * label is the free text itself.
     */
    public function label(?Instrument $instrument): string
    {
        $head = \implode(' ', \array_filter([
            $this->solo ? self::SOLO : '',
            $instrument->name ?? '',
            $this->number > 0 ? (string) $this->number : '',
        ], static fn (string $part): bool => $part !== ''));

        if ($this->note === '') {
            return $head;
        }

        return $head === '' ? $this->note : $head . ' (' . $this->note . ')';
    }

    /**
     * Recognises a label such as "Solo Gitarre 1 (tief)"; anything else becomes a voice without
     * an instrument that keeps the text as its free text.
     *
     * @param list<Instrument> $instruments
     */
    public static function parse(string $label, array $instruments): self
    {
        $label = PartNames::normalize($label);
        // Longer names first, so that "Oktavgitarre" is not read as "Gitarre".
        \usort(
            $instruments,
            static fn (Instrument $a, Instrument $b): int => \mb_strlen($b->name) <=> \mb_strlen($a->name)
        );

        foreach ($instruments as $instrument) {
            $pattern = '/^(?:(solo)\s+)?' . \preg_quote($instrument->name, '/')
                . '(?:\s+(\d{1,2}))?(?:\s+\((.+)\))?$/iu';
            if (\preg_match($pattern, $label, $matches) !== 1) {
                continue;
            }
            $number = (int) ($matches[2] ?? 0);
            if (($matches[2] ?? '') !== '' && ($number < 1 || $number > self::MAX_NUMBER)) {
                continue;
            }

            return new self($instrument->id, ($matches[1] ?? '') !== '', $number, \trim($matches[3] ?? ''));
        }

        return new self(0, false, 0, $label);
    }
}
