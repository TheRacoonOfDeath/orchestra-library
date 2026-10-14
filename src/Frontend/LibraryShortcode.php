<?php

declare(strict_types=1);

namespace Orchestra\Library\Frontend;

use Orchestra\Library\Capabilities;
use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\Part\Part;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Piece\PieceRepository;

/**
 * `[orchestra_library]`: lets members search the library and download sheet music.
 */
final class LibraryShortcode
{
    public const TAG = 'orchestra_library';

    private const QUERY_PARAMETER = 'orchestra_q';
    private const MAX_RESULTS = 100;

    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly PartRepository $parts,
        private readonly FileRepository $files,
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
            return \sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                \esc_html__('Please log in to see the library.', 'orchestra-library'),
                \esc_url(\wp_login_url((string) \get_permalink())),
                \esc_html__('Log in', 'orchestra-library')
            );
        }
        if (!\current_user_can(Capabilities::PIECE_READ)) {
            return '<p>' . \esc_html__('You are not allowed to see the library.', 'orchestra-library') . '</p>';
        }

        $query = \is_string($_GET[self::QUERY_PARAMETER] ?? null)
            ? \sanitize_text_field(\wp_unslash($_GET[self::QUERY_PARAMETER]))
            : '';
        $pieces = $this->pieces->search($query, false, self::MAX_RESULTS);
        $pieceIds = \array_map(static fn ($piece): int => $piece->id, $pieces);
        $partsByPiece = $this->parts->forPieces($pieceIds);
        $pieceFiles = $this->files->pieceFilesForPieces($pieceIds);
        $sheetMusic = $this->files->forParts(\array_map(
            static fn (Part $part): int => $part->id,
            \array_merge([], ...\array_values($partsByPiece))
        ));
        $canDownload = \current_user_can(Capabilities::SHEET_MUSIC_DOWNLOAD);
        $truncatedMessage = \__(
            'Only the first results are shown. Refine your search to find more pieces.',
            'orchestra-library'
        );

        \ob_start();
        ?>
        <div class="orchestra-library">
            <form method="get" class="orchestra-library-search"
                  action="<?php echo \esc_url((string) \get_permalink()); ?>">
                <?php self::renderPermalinkQueryFields(); ?>
                <label for="orchestra-library-query"><?php \esc_html_e('Search pieces', 'orchestra-library'); ?></label>
                <input type="search" id="orchestra-library-query" name="<?php echo \esc_attr(self::QUERY_PARAMETER); ?>"
                       value="<?php echo \esc_attr($query); ?>"
                       placeholder="<?php \esc_attr_e('Title or composer', 'orchestra-library'); ?>">
                <button type="submit"><?php \esc_html_e('Search', 'orchestra-library'); ?></button>
            </form>

            <?php if ($pieces === []) : ?>
                <p><?php \esc_html_e('No pieces found.', 'orchestra-library'); ?></p>
            <?php endif; ?>

            <?php foreach ($pieces as $piece) : ?>
                <section class="orchestra-library-piece">
                    <h3><?php echo \esc_html($piece->title); ?></h3>
                    <?php if ($piece->composer !== '') : ?>
                        <p class="orchestra-library-composer"><?php echo \esc_html($piece->composer); ?></p>
                    <?php endif; ?>

                    <?php if (($partsByPiece[$piece->id] ?? []) !== []) : ?>
                        <ul class="orchestra-library-parts">
                            <?php foreach ($partsByPiece[$piece->id] as $part) : ?>
                                <li>
                                    <?php echo \esc_html($part->name); ?>
                                    <?php if ($canDownload && isset($sheetMusic[$part->id])) : ?>
                                        – <?php self::renderDownloadLink($sheetMusic[$part->id]); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($canDownload && ($pieceFiles[$piece->id] ?? []) !== []) : ?>
                        <p class="orchestra-library-piece-files">
                            <?php \esc_html_e('Piece files:', 'orchestra-library'); ?>
                            <?php foreach ($pieceFiles[$piece->id] as $index => $file) : ?>
                                <?php echo $index > 0 ? ', ' : ''; ?>
                                <?php self::renderDownloadLink($file, $file->originalName); ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <?php if (\count($pieces) >= self::MAX_RESULTS) : ?>
                <p><?php echo \esc_html($truncatedMessage); ?></p>
            <?php endif; ?>
        </div>
        <?php

        return (string) \ob_get_clean();
    }

    private static function renderDownloadLink(LibraryFile $file, ?string $label = null): void
    {
        \printf(
            '<a href="%s">%s</a>',
            \esc_url(DownloadController::fileUrl($file->id)),
            \esc_html($label ?? \__('Download', 'orchestra-library'))
        );
    }

    /**
     * Keeps query arguments of plain permalinks (e.g. `?page_id=12`), which a GET form would drop.
     */
    private static function renderPermalinkQueryFields(): void
    {
        $queryString = \wp_parse_url((string) \get_permalink(), PHP_URL_QUERY);
        if (!\is_string($queryString)) {
            return;
        }

        \parse_str($queryString, $arguments);
        foreach ($arguments as $name => $value) {
            if (\is_string($value)) {
                \printf('<input type="hidden" name="%s" value="%s">', \esc_attr((string) $name), \esc_attr($value));
            }
        }
    }
}
