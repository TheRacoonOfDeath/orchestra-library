<?php

declare(strict_types=1);

namespace Orchestra\Library\Frontend;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Library\Capabilities;
use Orchestra\Library\Piece\PieceFormHandler;
use Orchestra\Library\Piece\PiecePostType;
use WP_Post;

/**
 * Creates and saves pieces from the library page (admin-post.php?action=orchestra_library_save_piece_page).
 */
final class PieceController
{
    public const ACTION = 'orchestra_library_save_piece_page';

    public const FIELD_TITLE = 'orchestra_library_title';
    public const FIELD_DESCRIPTION = 'orchestra_library_description';

    public function __construct(private readonly PieceFormHandler $handler)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @internal Hooked to `admin_post_orchestra_library_save_piece_page`.
     */
    public function handle(): never
    {
        \check_admin_referer(self::ACTION);
        $pieceId = \absint(\is_string($_POST['piece'] ?? null) ? $_POST['piece'] : 0);
        $post = $pieceId > 0 ? \get_post($pieceId) : null;
        $allowed = $pieceId === 0
            ? \current_user_can(Capabilities::PIECE_EDIT)
            : $post instanceof WP_Post
                && $post->post_type === PiecePostType::NAME
                && \current_user_can('edit_post', $pieceId);
        if (!$allowed) {
            \wp_die(\esc_html__('You are not allowed to edit this piece.', 'orchestra-library'), 403);
        }

        /** @var array<mixed> $input */
        $input = \wp_unslash($_POST);
        $title = \sanitize_text_field(\is_string($input[self::FIELD_TITLE] ?? null) ? $input[self::FIELD_TITLE] : '');
        if ($title === '') {
            Notices::error(\__('Please enter a title.', 'orchestra-library'));
            Redirect::back();
        }
        $description = \is_string($input[self::FIELD_DESCRIPTION] ?? null) ? $input[self::FIELD_DESCRIPTION] : '';

        $data = ['post_title' => $title, 'post_content' => \wp_kses_post($description)];
        $result = $pieceId === 0
            ? \wp_insert_post($data + ['post_type' => PiecePostType::NAME, 'post_status' => 'publish'], true)
            : \wp_update_post($data + ['ID' => $pieceId], true);
        if (\is_wp_error($result)) {
            Notices::error($result->get_error_message());
            Redirect::back();
        }

        $errors = $this->handler->save($result, $input, $_FILES);
        Notices::errors($errors);
        if ($errors === []) {
            Notices::success(\__('The piece has been saved.', 'orchestra-library'));
        }

        // A new piece continues in its own editor.
        Redirect::back($pieceId === 0 ? [LibraryShortcode::PIECE_PARAMETER => $result] : []);
    }
}
