<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Database\Row;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class RowTest extends TestCase
{
    public function testReadsTypedValuesFromDatabaseStrings(): void
    {
        $row = ['id' => '12', 'name' => 'Horn 1', 'part_id' => null];

        $this->assertSame(12, Row::int($row, 'id'));
        $this->assertSame('Horn 1', Row::string($row, 'name'));
        $this->assertNull(Row::nullableInt($row, 'part_id'));
    }

    public function testRejectsNonNumericIntegers(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Row::int(['id' => 'abc'], 'id');
    }

    public function testRejectsMissingColumns(): void
    {
        $this->expectException(UnexpectedValueException::class);

        Row::string([], 'name');
    }

    public function testAllKeepsOnlyRows(): void
    {
        $this->assertSame([['id' => '1']], Row::all([['id' => '1'], 'garbage']));
        $this->assertSame([], Row::all(null));
    }

    public function testIdListCastsEveryValue(): void
    {
        $this->assertSame('1,2,3', Row::idList([1, 2, 3]));
    }
}
