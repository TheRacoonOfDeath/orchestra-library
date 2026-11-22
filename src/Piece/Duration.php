<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use InvalidArgumentException;

/**
 * How long a piece takes, in whole seconds.
 */
final class Duration
{
    public const MAX_MINUTES = 999;

    /**
     * Reads the minutes and seconds fields of the piece form.
     *
     * @return int|null The duration in seconds, or null if both fields are empty or zero
     * @throws InvalidArgumentException if a field is not a whole number in range
     */
    public static function fromMinutesAndSeconds(string $minutes, string $seconds): ?int
    {
        $minutes = \trim($minutes);
        $seconds = \trim($seconds);
        foreach ([$minutes, $seconds] as $value) {
            if ($value !== '' && \preg_match('/^\d{1,4}$/', $value) !== 1) {
                throw new InvalidArgumentException('The duration must be whole minutes and seconds.');
            }
        }

        $minuteCount = (int) $minutes;
        $secondCount = (int) $seconds;
        if ($minuteCount > self::MAX_MINUTES || $secondCount > 59) {
            throw new InvalidArgumentException('The duration is out of range.');
        }

        $total = $minuteCount * 60 + $secondCount;

        return $total === 0 ? null : $total;
    }

    /**
     * "4:05", or "1:02:30" from one hour on.
     */
    public static function format(int $seconds): string
    {
        $seconds = \max(0, $seconds);
        $hours = \intdiv($seconds, 3600);
        $minutes = \intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? \sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : \sprintf('%d:%02d', $minutes, $rest);
    }
}
