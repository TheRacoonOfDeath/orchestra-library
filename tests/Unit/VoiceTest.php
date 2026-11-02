<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Part\Instrument;
use Orchestra\Library\Part\Voice;
use PHPUnit\Framework\TestCase;

final class VoiceTest extends TestCase
{
    private const GITARRE = 2;
    private const OKTAVGITARRE = 3;

    public function testLabelLeavesOutEmptyParts(): void
    {
        $gitarre = new Instrument(self::GITARRE, 'Gitarre');

        $this->assertSame('Solo Gitarre 1 (tief)', (new Voice(self::GITARRE, true, 1, 'tief'))->label($gitarre));
        $this->assertSame('Gitarre', (new Voice(self::GITARRE, false, 0, ''))->label($gitarre));
        $this->assertSame('Gitarre 2', (new Voice(self::GITARRE, false, 2, ''))->label($gitarre));
        $this->assertSame('Solo Gitarre', (new Voice(self::GITARRE, true, 0, ''))->label($gitarre));
        $this->assertSame('Gitarre (tief)', (new Voice(self::GITARRE, false, 0, 'tief'))->label($gitarre));
    }

    public function testLabelWithoutInstrumentIsTheFreeText(): void
    {
        $this->assertSame('Percussion', (new Voice(0, false, 0, 'Percussion'))->label(null));
        $this->assertSame('', (new Voice(0, false, 0, ''))->label(null));
    }

    public function testParsesRecognisedLabels(): void
    {
        $this->assertEquals(new Voice(self::GITARRE, false, 1, ''), Voice::parse('Gitarre 1', $this->instruments()));
        $this->assertEquals(
            new Voice(self::GITARRE, true, 3, 'tief'),
            Voice::parse(' solo  gitarre 3 (tief)', $this->instruments())
        );
        $this->assertEquals(new Voice(1, false, 0, 'tief'), Voice::parse('Bass (tief)', $this->instruments()));
    }

    public function testPrefersTheLongestInstrumentName(): void
    {
        $this->assertEquals(
            new Voice(self::OKTAVGITARRE, true, 12, ''),
            Voice::parse('Solo Oktavgitarre 12', $this->instruments())
        );
    }

    public function testKeepsUnrecognisedLabelsAsFreeText(): void
    {
        $this->assertEquals(new Voice(0, false, 0, 'Horn 1'), Voice::parse('Horn 1', $this->instruments()));
        $this->assertEquals(new Voice(0, false, 0, 'Gitarre 21'), Voice::parse('Gitarre 21', $this->instruments()));
        $this->assertEquals(new Voice(0, false, 0, 'Gitarre Solo'), Voice::parse('Gitarre Solo', $this->instruments()));
    }

    /**
     * @return list<Instrument>
     */
    private function instruments(): array
    {
        return [
            new Instrument(1, 'Bass'),
            new Instrument(self::GITARRE, 'Gitarre'),
            new Instrument(self::OKTAVGITARRE, 'Oktavgitarre'),
        ];
    }
}
