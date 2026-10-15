<?php

declare(strict_types=1);

namespace Orchestra\Library\Admin;

use Orchestra\Core\Ui\Notices;
use Orchestra\Library\Piece\PieceForm;
use Orchestra\Library\Piece\PieceFormHandler;
use Orchestra\Library\Piece\PiecePostType;
use Orchestra\Library\Piece\PieceRepository;
use WP_Post;

/**
 * The meta boxes on the piece edit screen in wp-admin: details, parts with their sheet music, and
 * piece files. They show the same {@see PieceForm} as the library page and are saved together with
 * the piece by {@see PieceFormHandler}.
 */
final class PieceEditor
{
    private const NONCE_ACTION = 'orchestra_library_save_piece';
    private const NONCE_FIELD = 'orchestra_library_piece_nonce';

    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PieceForm $form,
        private readonly PieceFormHandler $handler,
    ) {
    }

    public function register(): void
    {
        \add_action('add_meta_boxes_' . PiecePostType::NAME, [$this, 'addMetaBoxes']);
        \add_action('post_edit_form_tag', [$this, 'addMultipartEncoding']);
        \add_action('save_post_' . PiecePostType::NAME, [$this, 'save'], 10, 2);
    }

    /**
     * @internal Hooked to `post_edit_form_tag`. File inputs need a multipart form.
     */
    public function addMultipartEncoding(WP_Post $post): void
    {
        if ($post->post_type === PiecePostType::NAME) {
            echo ' enctype="multipart/form-data"';
        }
    }

    /**
     * @internal Hooked to `add_meta_boxes_orchestra_piece`.
     */
    public function addMetaBoxes(): void
    {
        $type = PiecePostType::NAME;
        $details = \__('Details', 'orchestra-library');
        $parts = \__('Parts and Sheet Music', 'orchestra-library');
        $files = \__('Piece Files', 'orchestra-library');
        \add_meta_box('orchestra-library-details', $details, [$this, 'renderDetails'], $type, 'side', 'high');
        \add_meta_box('orchestra-library-parts', $parts, [$this, 'renderParts'], $type, 'normal', 'high');
        \add_meta_box('orchestra-library-piece-files', $files, [$this, 'renderPieceFiles'], $type, 'normal');
    }

    /**
     * @internal Meta box callback.
     */
    public function renderDetails(WP_Post $post): void
    {
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo self::wrap($this->form->details($this->pieces->find($post->ID))); // escaped by the form
    }

    /**
     * @internal Meta box callback.
     */
    public function renderParts(WP_Post $post): void
    {
        echo self::wrap($this->form->parts($post->ID)); // escaped by the form
    }

    /**
     * @internal Meta box callback.
     */
    public function renderPieceFiles(WP_Post $post): void
    {
        echo self::wrap($this->form->pieceFiles($post->ID)); // escaped by the form
    }

    /**
     * @internal Hooked to `save_post_orchestra_piece`.
     */
    public function save(int $pieceId, WP_Post $post): void
    {
        $nonce = $_POST[self::NONCE_FIELD] ?? null;
        if (
            (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || \wp_is_post_revision($pieceId) !== false
            || !\is_string($nonce)
            || \wp_verify_nonce(\wp_unslash($nonce), self::NONCE_ACTION) === false
            || !\current_user_can('edit_post', $pieceId)
        ) {
            return;
        }

        /** @var array<mixed> $input */
        $input = \wp_unslash($_POST);
        Notices::errors($this->handler->save($pieceId, $input, $_FILES));
    }

    private static function wrap(string $html): string
    {
        return '<div class="orchestra orchestra--admin">' . $html . '</div>';
    }
}
