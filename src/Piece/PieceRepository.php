<?php

declare(strict_types=1);

namespace Orchestra\Library\Piece;

use WP_Post;

final class PieceRepository
{
    public function find(int $pieceId): ?Piece
    {
        $post = \get_post($pieceId);
        if (!$post instanceof WP_Post || $post->post_type !== PiecePostType::NAME) {
            return null;
        }

        return $this->hydrate($post);
    }

    /**
     * Published pieces whose title, description or composer contain the query, ordered by title.
     *
     * @return list<Piece>
     */
    public function search(string $query, bool $includeArchived = false, int $limit = 100): array
    {
        $archivedFilter = $includeArchived
            ? []
            : [['key' => PiecePostType::META_ARCHIVED, 'compare' => 'NOT EXISTS']];

        $query = \trim($query);
        if ($query === '') {
            $ids = $this->ids('', $archivedFilter, $limit);
        } else {
            // WP_Query cannot OR a full-text search with a meta query, so search both separately.
            $composerFilter = ['key' => PiecePostType::META_COMPOSER, 'value' => $query, 'compare' => 'LIKE'];
            $ids = \array_values(\array_unique([
                ...$this->ids($query, $archivedFilter, $limit),
                ...$this->ids('', [...$archivedFilter, $composerFilter], $limit),
            ]));
        }

        if ($ids === []) {
            return [];
        }

        $posts = \get_posts([
            'post_type' => PiecePostType::NAME,
            'post_status' => 'publish',
            'post__in' => $ids,
            'posts_per_page' => $limit,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        return \array_values(\array_map($this->hydrate(...), $posts));
    }

    public function setComposer(int $pieceId, string $composer): void
    {
        \update_post_meta($pieceId, PiecePostType::META_COMPOSER, \sanitize_text_field($composer));
    }

    public function setArchived(int $pieceId, bool $archived): void
    {
        if ($archived) {
            \update_post_meta($pieceId, PiecePostType::META_ARCHIVED, '1');
        } else {
            \delete_post_meta($pieceId, PiecePostType::META_ARCHIVED);
        }
    }

    /**
     * @param array<array<string, string>> $metaQuery
     * @return list<int>
     */
    private function ids(string $search, array $metaQuery, int $limit): array
    {
        $ids = \get_posts([
            'post_type' => PiecePostType::NAME,
            'post_status' => 'publish',
            's' => $search,
            'meta_query' => $metaQuery,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        return \array_values(\array_filter($ids, \is_int(...)));
    }

    private function hydrate(WP_Post $post): Piece
    {
        $composer = \get_post_meta($post->ID, PiecePostType::META_COMPOSER, true);

        return new Piece(
            $post->ID,
            $post->post_title,
            \is_string($composer) ? $composer : '',
            $post->post_content,
            \get_post_meta($post->ID, PiecePostType::META_ARCHIVED, true) === '1',
            $post->post_status === 'publish',
        );
    }
}
