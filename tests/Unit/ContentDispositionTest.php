<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\Download\ContentDisposition;
use PHPUnit\Framework\TestCase;

final class ContentDispositionTest extends TestCase
{
    public function testAsciiFileName(): void
    {
        $this->assertSame(
            'attachment; filename="Pirates_Horn1.pdf"; filename*=UTF-8\'\'Pirates_Horn1.pdf',
            ContentDisposition::attachment('Pirates_Horn1.pdf')
        );
    }

    public function testNonAsciiFileNameHasFallbackAndEncodedName(): void
    {
        $this->assertSame(
            'attachment; filename="M__rchen.pdf"; filename*=UTF-8\'\'M%C3%A4rchen.pdf',
            ContentDisposition::attachment('Märchen.pdf')
        );
    }

    public function testCannotInjectHeadersOrQuotes(): void
    {
        $header = ContentDisposition::attachment("evil\"\r\nX-Injected: 1.pdf");

        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        $this->assertStringStartsWith('attachment; filename="evil_X-Injected: 1.pdf";', $header);
    }
}
