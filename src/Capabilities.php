<?php

declare(strict_types=1);

namespace Orchestra\Library;

use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Authorization\Roles;

/**
 * The capabilities owned by orchestra-library. See orchestra-core's docs/capabilities.md.
 */
final class Capabilities
{
    /** View pieces and parts. */
    public const PIECE_READ = 'orchestra_library_piece_read';

    /** Create, edit and archive pieces and their parts. */
    public const PIECE_EDIT = 'orchestra_library_piece_edit';

    /** Delete pieces. Pieces are normally archived instead. */
    public const PIECE_DELETE = 'orchestra_library_piece_delete';

    /** Download sheet music and piece files. */
    public const SHEET_MUSIC_DOWNLOAD = 'orchestra_library_sheet_music_download';

    /** Upload, replace and delete sheet music and piece files. */
    public const SHEET_MUSIC_MANAGE = 'orchestra_library_sheet_music_manage';

    /**
     * @internal Hooked to `orchestra_core_register_capabilities`.
     */
    public static function register(CapabilityRegistry $registry): void
    {
        $everyone = [Roles::MEMBER, Roles::CONDUCTOR, Roles::ORGANIZER];

        $registry
            ->register(self::PIECE_READ, $everyone)
            ->register(self::PIECE_EDIT, [Roles::CONDUCTOR])
            ->register(self::PIECE_DELETE)
            ->register(self::SHEET_MUSIC_DOWNLOAD, $everyone)
            ->register(self::SHEET_MUSIC_MANAGE, [Roles::CONDUCTOR]);
    }
}
