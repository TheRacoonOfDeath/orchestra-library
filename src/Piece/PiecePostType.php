<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use Orchestra\Library\Capabilities;

/**
 * Pieces are stored as the private custom post type `orchestra_piece`:
 * title = piece title, content = description, meta = composer and archived flag.
 */
final class PiecePostType
{
    public const NAME = 'orchestra_piece';

    public const META_COMPOSER = 'orchestra_library_composer';

    /** Present (value "1") only while the piece is archived. */
    public const META_ARCHIVED = 'orchestra_library_archived';

    /**
     * @internal Hooked to `init`.
     */
    public static function register(): void
    {
        \register_post_type(self::NAME, [
            'labels' => [
                'name' => \__('Library', 'orchestra-library'),
                'singular_name' => \__('Piece', 'orchestra-library'),
                'menu_name' => \__('Library', 'orchestra-library'),
                'all_items' => \__('All Pieces', 'orchestra-library'),
                'add_new' => \__('Add Piece', 'orchestra-library'),
                'add_new_item' => \__('Add New Piece', 'orchestra-library'),
                'edit_item' => \__('Edit Piece', 'orchestra-library'),
                'new_item' => \__('New Piece', 'orchestra-library'),
                'search_items' => \__('Search Pieces', 'orchestra-library'),
                'not_found' => \__('No pieces found.', 'orchestra-library'),
                'not_found_in_trash' => \__('No pieces found in Trash.', 'orchestra-library'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-format-audio',
            'supports' => ['title', 'editor', 'revisions'],
            'rewrite' => false,
            'query_var' => false,
            'map_meta_cap' => true,
            'capability_type' => ['orchestra_piece', 'orchestra_pieces'],
            'capabilities' => [
                'read' => Capabilities::PIECE_READ,
                'create_posts' => Capabilities::PIECE_EDIT,
                'edit_posts' => Capabilities::PIECE_EDIT,
                'edit_others_posts' => Capabilities::PIECE_EDIT,
                'edit_published_posts' => Capabilities::PIECE_EDIT,
                'edit_private_posts' => Capabilities::PIECE_EDIT,
                'publish_posts' => Capabilities::PIECE_EDIT,
                'read_private_posts' => Capabilities::PIECE_EDIT,
                'delete_posts' => Capabilities::PIECE_DELETE,
                'delete_others_posts' => Capabilities::PIECE_DELETE,
                'delete_published_posts' => Capabilities::PIECE_DELETE,
                'delete_private_posts' => Capabilities::PIECE_DELETE,
            ],
        ]);

        \register_post_meta(self::NAME, self::META_COMPOSER, [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => static fn (): bool => \current_user_can(Capabilities::PIECE_EDIT),
        ]);
    }
}
