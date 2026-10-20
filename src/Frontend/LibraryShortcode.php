<?php

declare(strict_types=1);

namespace Orchestra\Library\Frontend;

use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Library\Capabilities;
use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Piece\Piece;
use Orchestra\Library\Piece\PieceForm;
use Orchestra\Library\Piece\PieceRepository;

/**
 * `[orchestra_library]`: search the library and download sheet music. Conductors also create and
 * edit pieces, parts and files here (`?orchestra_piece=ID` or `new`).
 */
final class LibraryShortcode
{
    public const TAG = 'orchestra_library';
    public const PIECE_PARAMETER = 'orchestra_piece';

    private const QUERY_PARAMETER = 'orchestra_q';
    private const ARCHIVED_PARAMETER = 'orchestra_archived';
    private const MAX_RESULTS = 100;

    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PartRepository $parts,
        private readonly FileRepository $files,
        private readonly PieceForm $form,
    ) {
    }

    public function register(): void
    {
        \add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * @internal Shortcode callback.
     */
    public function render(): string
    {
        if (!\is_user_logged_in()) {
            return Html::loginRequired(\__('Please log in to see the library.', 'orchestra-library'));
        }
        if (!\current_user_can(Capabilities::PIECE_READ)) {
            return Html::notAllowed(\__('You are not allowed to see the library.', 'orchestra-library'));
        }

        $requested = \is_string($_GET[self::PIECE_PARAMETER] ?? null) ? $_GET[self::PIECE_PARAMETER] : '';
        if ($requested !== '' && \current_user_can(Capabilities::PIECE_EDIT)) {
            return Html::page($this->editor($requested === 'new' ? 0 : \absint($requested)));
        }

        return Html::page($this->list());
    }

    private function list(): string
    {
        $query = \is_string($_GET[self::QUERY_PARAMETER] ?? null)
            ? \sanitize_text_field(\wp_unslash($_GET[self::QUERY_PARAMETER]))
            : '';
        $canEdit = \current_user_can(Capabilities::PIECE_EDIT);
        $withArchived = $canEdit && ($_GET[self::ARCHIVED_PARAMETER] ?? '') === '1';
        $pieces = $this->pieces->search($query, $withArchived, self::MAX_RESULTS);

        $pieceIds = \array_map(static fn (Piece $piece): int => $piece->id, $pieces);
        $partsByPiece = $this->parts->forPieces($pieceIds);
        $pieceFiles = $this->files->pieceFilesForPieces($pieceIds);
        $sheetMusic = $this->files->forParts(\array_map(
            static fn (Part $part): int => $part->id,
            \array_merge([], ...\array_values($partsByPiece))
        ));
        $canDownload = \current_user_can(Capabilities::SHEET_MUSIC_DOWNLOAD);
        $base = self::baseUrl();

        $html = '<div class="orchestra-toolbar"><form method="get" class="orchestra-actions" role="search" action="'
            . \esc_url($base) . '">' . self::permalinkQueryFields($base)
            . '<label class="screen-reader-text" for="orchestra-library-query">'
            . \esc_html__('Search pieces', 'orchestra-library') . '</label>'
            . '<input type="search" id="orchestra-library-query" name="' . self::QUERY_PARAMETER . '" value="'
            . \esc_attr($query) . '" placeholder="' . \esc_attr__('Title or composer', 'orchestra-library')
            . '" style="width:auto;min-width:14rem">'
            . ($withArchived ? Form::hidden(self::ARCHIVED_PARAMETER, '1') : '')
            . Form::submit(\__('Search', 'orchestra-library')) . '</form>';
        if ($canEdit) {
            $archivedLabel = $withArchived
                ? \__('Hide archived', 'orchestra-library')
                : \__('Show archived', 'orchestra-library');
            $archivedUrl = \add_query_arg(self::ARCHIVED_PARAMETER, $withArchived ? '0' : '1', $base);
            $newUrl = \add_query_arg(self::PIECE_PARAMETER, 'new', $base);
            $html .= '<div class="orchestra-actions">' . Form::link($archivedUrl, $archivedLabel, 'link')
                . Form::link($newUrl, \__('New piece', 'orchestra-library'), 'primary') . '</div>';
        }
        $html .= '</div>';

        if ($pieces === []) {
            return $html . Html::empty(\__('No pieces found.', 'orchestra-library'));
        }

        $html .= '<div class="orchestra-cards">';
        foreach ($pieces as $piece) {
            // Conductors open the editor by clicking the title.
            $title = \esc_html($piece->title);
            if ($canEdit && \current_user_can('edit_post', $piece->id)) {
                $title = '<a href="' . \esc_url(\add_query_arg(self::PIECE_PARAMETER, $piece->id, $base)) . '">'
                    . $title . '</a>';
            }
            $html .= '<article class="orchestra-card"><div class="orchestra-toolbar"><h3 class="orchestra-card__title">'
                . $title . '</h3>'
                . ($piece->archived ? Html::badge(\__('Archived', 'orchestra-library'), 'warning') : '') . '</div>';
            if ($piece->composer !== '') {
                $html .= '<div class="orchestra-card__meta">' . \esc_html($piece->composer) . '</div>';
            }

            $parts = $partsByPiece[$piece->id] ?? [];
            if ($parts !== []) {
                $html .= '<ul class="orchestra-list">';
                foreach ($parts as $part) {
                    $file = $sheetMusic[$part->id] ?? null;
                    $download = $canDownload && $file !== null
                        ? Form::link(
                            DownloadController::fileUrl($file->id),
                            \__('Download', 'orchestra-library'),
                            'small'
                        )
                        : '';
                    $html .= '<li><span>' . \esc_html($part->name) . '</span>' . $download . '</li>';
                }
                $html .= '</ul>';
            }

            if ($canDownload && ($pieceFiles[$piece->id] ?? []) !== []) {
                $links = \array_map(
                    static fn (LibraryFile $file): string => self::downloadLink($file, $file->originalName),
                    $pieceFiles[$piece->id]
                );
                $html .= '<p class="orchestra-muted">' . \esc_html__('Piece files:', 'orchestra-library') . ' '
                    . \implode(', ', $links) . '</p>';
            }
            $html .= '</article>';
        }
        $html .= '</div>';

        if (\count($pieces) >= self::MAX_RESULTS) {
            $html .= '<p class="orchestra-muted">' . \esc_html__(
                'Only the first results are shown. Refine your search to find more pieces.',
                'orchestra-library'
            ) . '</p>';
        }

        return $html;
    }

    private function editor(int $pieceId): string
    {
        $piece = $pieceId > 0 ? $this->pieces->find($pieceId) : null;
        $base = self::baseUrl();
        $back = '<p class="orchestra-back"><a href="' . \esc_url($base) . '">&larr; '
            . \esc_html__('Library', 'orchestra-library')
            . '</a></p>';
        if ($pieceId > 0 && ($piece === null || !\current_user_can('edit_post', $pieceId))) {
            return $back . Html::empty(\__('The piece does not exist.', 'orchestra-library'));
        }

        $post = $pieceId > 0 ? \get_post($pieceId) : null;
        $html = $back . '<h2>' . \esc_html($piece === null ? \__('New piece', 'orchestra-library') : $piece->title)
            . '</h2>'
            . Form::open(
                PieceController::ACTION,
                PieceController::ACTION,
                ['piece' => $pieceId],
                ['class' => 'orchestra-form orchestra-form--wide'],
                true
            )
            . '<div class="orchestra-card orchestra-stack">'
            . Html::field(
                'orchestra-library-title',
                \__('Title', 'orchestra-library'),
                '<input type="text" id="orchestra-library-title" name="' . PieceController::FIELD_TITLE
                    . '" required value="'
                    . \esc_attr($piece->title ?? '') . '">'
            )
            . $this->form->details($piece)
            . Html::field(
                'orchestra-library-description',
                \__('Description', 'orchestra-library'),
                '<textarea id="orchestra-library-description" name="' . PieceController::FIELD_DESCRIPTION
                    . '" rows="4">'
                    . \esc_textarea($post->post_content ?? '') . '</textarea>'
            )
            . '</div>'
            . '<section class="orchestra-stack"><h3>' . \esc_html__('Parts and sheet music', 'orchestra-library')
            . '</h3>'
            . $this->form->parts($pieceId) . '</section>'
            . '<section class="orchestra-stack"><h3>' . \esc_html__('Piece files', 'orchestra-library') . '</h3>'
            . $this->form->pieceFiles($pieceId) . '</section>'
            . '<div class="orchestra-actions">' . Form::submit(\__('Save', 'orchestra-library'), 'primary')
            . Form::link($base, \__('Cancel', 'orchestra-library')) . '</div></form>';

        return $html;
    }

    private static function baseUrl(): string
    {
        return \remove_query_arg(
            [self::PIECE_PARAMETER, self::QUERY_PARAMETER, self::ARCHIVED_PARAMETER],
            Redirect::currentUrl()
        );
    }

    private static function downloadLink(LibraryFile $file, string $label): string
    {
        return '<a href="' . \esc_url(DownloadController::fileUrl($file->id)) . '">' . \esc_html($label) . '</a>';
    }

    /**
     * Keeps query arguments of plain permalinks (e.g. `?page_id=12`), which a GET form would drop.
     */
    private static function permalinkQueryFields(string $url): string
    {
        $queryString = \wp_parse_url($url, PHP_URL_QUERY);
        if (!\is_string($queryString)) {
            return '';
        }

        \parse_str($queryString, $arguments);
        $html = '';
        foreach ($arguments as $name => $value) {
            if (\is_string($value)) {
                $html .= Form::hidden((string) $name, $value);
            }
        }

        return $html;
    }
}
