<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use InvalidArgumentException;
use Orchestra\Library\Piece\Duration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    public function testReadsMinutesAndSeconds(): void
    {
        $this->assertSame(245, Duration::fromMinutesAndSeconds('4', '5'));
        $this->assertSame(240, Duration::fromMinutesAndSeconds('4', ''));
        $this->assertSame(30, Duration::fromMinutesAndSeconds('', '30'));
        $this->assertSame(999 * 60 + 59, Duration::fromMinutesAndSeconds(' 999 ', '59'));
    }

    public function testEmptyOrZeroMeansNoDuration(): void
    {
        $this->assertNull(Duration::fromMinutesAndSeconds('', ''));
        $this->assertNull(Duration::fromMinutesAndSeconds('0', '0'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'seconds over 59' => ['4', '60'],
            'too many minutes' => ['1000', '0'],
            'negative' => ['-1', '0'],
            'decimal' => ['4.5', '0'],
            'text' => ['four', '0'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(string $minutes, string $seconds): void
    {
        $this->expectException(InvalidArgumentException::class);
        Duration::fromMinutesAndSeconds($minutes, $seconds);
    }

    public function testFormatsMinutesAndHours(): void
    {
        $this->assertSame('0:00', Duration::format(0));
        $this->assertSame('0:45', Duration::format(45));
        $this->assertSame('4:05', Duration::format(245));
        $this->assertSame('59:59', Duration::format(3599));
        $this->assertSame('1:00:00', Duration::format(3600));
        $this->assertSame('1:02:30', Duration::format(3750));
    }
}
