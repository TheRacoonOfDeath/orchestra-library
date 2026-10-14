<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use Orchestra\Library\Part\PartService;
use WP_Post;

/**
 * Prevents trashing and deleting pieces that other plugins still refer to, and removes the parts
 * and files of deleted pieces.
 */
final class DeletionGuard
{
    public function __construct(private readonly PartService $partService)
    {
    }

    public function register(): void
    {
        \add_filter('pre_trash_post', [$this, 'guard'], 10, 2);
        \add_filter('pre_delete_post', [$this, 'guard'], 10, 2);
        \add_action('before_delete_post', [$this, 'cleanUp'], 10, 2);
    }

    /**
     * @internal Hooked to `pre_trash_post` and `pre_delete_post`. Returning false cancels the operation.
     */
    public function guard(mixed $check, WP_Post $post): mixed
    {
        if ($check !== null || $post->post_type !== PiecePostType::NAME) {
            return $check;
        }

        $blockers = $this->partService->pieceDeleteBlockers($post->ID);
        if ($blockers === []) {
            return $check;
        }

        if (\is_admin() && !\wp_doing_ajax()) {
            /* translators: %s: piece title */
            $title = \sprintf(\__('"%s" cannot be deleted', 'orchestra-library'), $post->post_title);
            $items = \implode('', \array_map(
                static fn (string $blocker): string => '<li>' . \esc_html($blocker) . '</li>',
                $blockers
            ));
            \wp_die(
                '<h1>' . \esc_html($title) . '</h1><ul>' . $items . '</ul><p>'
                . \esc_html__('Archive the piece instead.', 'orchestra-library') . '</p>',
                \esc_html($title),
                ['response' => 409, 'back_link' => true]
            );
        }

        return false;
    }

    /**
     * @internal Hooked to `before_delete_post`.
     */
    public function cleanUp(int $postId, WP_Post $post): void
    {
        if ($post->post_type === PiecePostType::NAME) {
            $this->partService->deleteAllForPiece($postId);
        }
    }
}
