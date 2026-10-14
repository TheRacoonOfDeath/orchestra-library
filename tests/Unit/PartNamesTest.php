<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Part\PartNames;
use PHPUnit\Framework\TestCase;

final class PartNamesTest extends TestCase
{
    public function testNormalizesWhitespace(): void
    {
        $this->assertSame('Horn 1', PartNames::normalize("  Horn \t 1 "));
    }

    public function testAcceptsDistinctNames(): void
    {
        $this->assertSame([], PartNames::validate(['Horn 1', 'Horn 2', 'Percussion']));
    }

    public function testRejectsEmptyNames(): void
    {
        $this->assertSame(['Part names must not be empty.'], PartNames::validate(['Horn 1', '']));
    }

    public function testRejectsDuplicatesIgnoringCase(): void
    {
        $this->assertSame(
            ['The part name "horn 1" is used more than once.'],
            PartNames::validate(['Horn 1', 'horn 1'])
        );
    }

    public function testReportsEachDuplicateOnce(): void
    {
        $this->assertCount(1, PartNames::validate(['Flute', 'Flute', 'Flute']));
    }

    public function testRejectsTooLongNames(): void
    {
        $this->assertCount(1, PartNames::validate([\str_repeat('ä', PartNames::MAX_LENGTH + 1)]));
        $this->assertSame([], PartNames::validate([\str_repeat('ä', PartNames::MAX_LENGTH)]));
    }
}
