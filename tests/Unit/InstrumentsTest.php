<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Part\Instrument;
use Orchestra\Library\Part\Instruments;
use PHPUnit\Framework\TestCase;

final class InstrumentsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['orchestra_test_options'] = [];
    }

    public function testDefaultsHaveShortNames(): void
    {
        $this->assertEquals(
            [
                new Instrument(1, 'Bass'),
                new Instrument(2, 'Gitarre', 'Git'),
                new Instrument(3, 'Oktavgitarre', 'Okt'),
                new Instrument(4, 'Quartgitarre', 'Quart'),
            ],
            (new Instruments())->all()
        );
    }

    public function testNamesSavedBeforeShortNamesGetTheDefaultShortName(): void
    {
        $GLOBALS['orchestra_test_options'][Instruments::OPTION] = [2 => 'Gitarre', 5 => 'Klarinette'];

        $this->assertEquals(
            [new Instrument(2, 'Gitarre', 'Git'), new Instrument(5, 'Klarinette')],
            (new Instruments())->all()
        );
    }

    public function testKeepsSavedShortNames(): void
    {
        $instruments = new Instruments();
        $instruments->save([new Instrument(2, 'Gitarre'), new Instrument(5, 'Klarinette', 'Klar')]);

        $this->assertEquals(
            [new Instrument(2, 'Gitarre'), new Instrument(5, 'Klarinette', 'Klar')],
            $instruments->all()
        );
    }
}
