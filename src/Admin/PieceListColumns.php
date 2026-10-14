<?php

declare(strict_types=1);

namespace Orchestra\Library\Admin;

use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Piece\PiecePostType;
use Orchestra\Library\Piece\PieceRepository;

/**
 * Adds composer, number of parts and archived status to the piece list in wp-admin.
 */
final class PieceListColumns
{
    public function __construct(private readonly PieceRepository $pieces, private readonly PartRepository $parts)
    {
    }

    public function register(): void
    {
        \add_filter('manage_' . PiecePostType::NAME . '_posts_columns', [$this, 'columns']);
        \add_action('manage_' . PiecePostType::NAME . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
    }

    /**
     * @internal
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['orchestra_composer'] = \__('Composer', 'orchestra-library');
                $result['orchestra_parts'] = \__('Parts', 'orchestra-library');
                $result['orchestra_archived'] = \__('Archived', 'orchestra-library');
            }
        }

        return $result;
    }

    /**
     * @internal
     */
    public function renderColumn(string $column, int $pieceId): void
    {
        switch ($column) {
            case 'orchestra_composer':
                echo \esc_html($this->pieces->find($pieceId)->composer ?? '');
                break;
            case 'orchestra_parts':
                echo \esc_html((string) $this->parts->countForPiece($pieceId));
                break;
            case 'orchestra_archived':
                if ($this->pieces->find($pieceId)->archived ?? false) {
                    \esc_html_e('Yes', 'orchestra-library');
                }
                break;
        }
    }
}
