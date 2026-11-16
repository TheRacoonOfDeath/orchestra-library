<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Part\Instrument;
use Orchestra\Library\Part\InstrumentsInput;
use PHPUnit\Framework\TestCase;

final class InstrumentsInputTest extends TestCase
{
    public function testAddsAndSortsAlphabetically(): void
    {
        $input = InstrumentsInput::fromForm(
            [InstrumentsInput::FIELD_NEW_INSTRUMENTS => [['name' => ' mandoline '], ['name' => '']]],
            $this->current(),
            []
        );

        $this->assertSame([], $input->errors);
        $this->assertSame(['Bass', 'Gitarre', 'mandoline'], $this->names($input->instruments));
        $this->assertSame(3, $input->instruments[2]->id);
    }

    public function testReportsRenamedInstruments(): void
    {
        $input = InstrumentsInput::fromForm(
            [InstrumentsInput::FIELD_INSTRUMENTS => [1 => 'Kontrabass', 2 => 'Gitarre']],
            $this->current(),
            []
        );

        $this->assertSame([], $input->errors);
        $this->assertEquals([new Instrument(1, 'Kontrabass')], $input->renamed);
        $this->assertSame(['Gitarre', 'Kontrabass'], $this->names($input->instruments));
    }

    public function testDeletesOnlyUnusedInstruments(): void
    {
        $unused = InstrumentsInput::fromForm([InstrumentsInput::FIELD_DELETE => '1'], $this->current(), [2 => 4]);
        $this->assertSame(['Gitarre'], $this->names($unused->instruments));

        $used = InstrumentsInput::fromForm([InstrumentsInput::FIELD_DELETE => '2'], $this->current(), [2 => 4]);
        $this->assertSame(['The instrument "Gitarre" cannot be deleted: 4 voices use it.'], $used->errors);
    }

    public function testKeepsShortNames(): void
    {
        $input = InstrumentsInput::fromForm(
            [
                InstrumentsInput::FIELD_SHORT_NAMES => [1 => '', 2 => ' Git '],
                InstrumentsInput::FIELD_NEW_INSTRUMENTS => [['name' => 'Mandoline', 'short' => 'Mand']],
            ],
            $this->current(),
            []
        );

        $this->assertSame([], $input->errors);
        $this->assertEquals(
            [new Instrument(1, 'Bass'), new Instrument(2, 'Gitarre', 'Git'), new Instrument(3, 'Mandoline', 'Mand')],
            $input->instruments
        );
        $this->assertSame([], $input->renamed);
    }

    public function testRejectsLongShortNames(): void
    {
        $input = InstrumentsInput::fromForm(
            [InstrumentsInput::FIELD_SHORT_NAMES => [1 => \str_repeat('b', Instrument::SHORT_NAME_MAX_LENGTH + 1)]],
            $this->current(),
            []
        );

        $this->assertCount(1, $input->errors);
    }

    public function testRejectsEmptyAndDuplicateNames(): void
    {
        $input = InstrumentsInput::fromForm(
            [
                InstrumentsInput::FIELD_INSTRUMENTS => [1 => ' '],
                InstrumentsInput::FIELD_NEW_INSTRUMENTS => [['name' => 'GITARRE']],
            ],
            $this->current(),
            []
        );

        $this->assertSame(
            ['Instrument names must not be empty.', 'The instrument "GITARRE" exists more than once.'],
            $input->errors
        );
    }

    /**
     * @return list<Instrument>
     */
    private function current(): array
    {
        return [new Instrument(1, 'Bass'), new Instrument(2, 'Gitarre')];
    }

    /**
     * @param list<Instrument> $instruments
     * @return list<string>
     */
    private function names(array $instruments): array
    {
        return \array_map(static fn (Instrument $instrument): string => $instrument->name, $instruments);
    }
}
