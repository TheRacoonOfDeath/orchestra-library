<?php

declare(strict_types=1);

namespace Orchestra\Library\Privacy;

use Orchestra\Library\Piece\PiecePostType;
use wpdb;

/**
 * The library holds no personal data of members: downloads are not logged and files do not keep
 * who uploaded them. The only reference to a person is the author WordPress gives every piece,
 * which is removed when the account is deleted (pieces are kept, other plugins refer to them).
 * Suggests a text for the privacy policy. See the GDPR documentation of orchestra-core.
 */
final class Privacy
{
    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function register(): void
    {
        \add_action('deleted_user', [$this, 'forgetAuthor']);
        \add_action('admin_init', [self::class, 'addPolicyText']);
    }

    /**
     * WordPress deletes the posts of a deleted user only for post types with authors; pieces stay
     * with the deleted user's ID unless the posts were given to another user.
     *
     * @internal Hooked to `deleted_user`.
     */
    public function forgetAuthor(int $userId): void
    {
        $ids = $this->wpdb->get_col((string) $this->wpdb->prepare(
            'SELECT ID FROM %i WHERE post_author = %d AND post_type = %s',
            $this->wpdb->posts,
            $userId,
            PiecePostType::NAME
        ));
        foreach ($ids as $id) {
            $this->wpdb->update($this->wpdb->posts, ['post_author' => 0], ['ID' => (int) $id], ['%d'], ['%d']);
            \clean_post_cache((int) $id);
        }
    }

    /**
     * @internal Hooked to `admin_init`.
     */
    public static function addPolicyText(): void
    {
        if (!\function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $text = \__(
            'The sheet music library can only be opened by members after logging in. Downloads are not logged. '
                . 'The files cannot be reached through a direct link.',
            'orchestra-library'
        );

        \wp_add_privacy_policy_content(
            \__('Orchestra Library', 'orchestra-library'),
            \wp_kses_post('<p>' . \esc_html($text) . '</p>')
        );
    }
}
