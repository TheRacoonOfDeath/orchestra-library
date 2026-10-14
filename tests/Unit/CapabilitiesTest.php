<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Authorization\Roles;
use Orchestra\Library\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase
{
    public function testRegistersTheDocumentedGrants(): void
    {
        $registry = new CapabilityRegistry();

        Capabilities::register($registry);

        $byRole = $registry->capabilitiesByRole();
        $this->assertSame(
            [Capabilities::PIECE_READ, Capabilities::SHEET_MUSIC_DOWNLOAD],
            $byRole[Roles::MEMBER]
        );
        $this->assertSame($byRole[Roles::MEMBER], $byRole[Roles::ORGANIZER]);
        $this->assertSame([
            Capabilities::PIECE_EDIT,
            Capabilities::PIECE_READ,
            Capabilities::SHEET_MUSIC_DOWNLOAD,
            Capabilities::SHEET_MUSIC_MANAGE,
        ], $byRole[Roles::CONDUCTOR]);
        $this->assertContains(Capabilities::PIECE_DELETE, $byRole[Roles::ADMINISTRATOR]);
        $this->assertNotContains(Capabilities::PIECE_DELETE, $byRole[Roles::CONDUCTOR]);
    }
}
