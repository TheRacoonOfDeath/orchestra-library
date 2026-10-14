<?php

declare(strict_types=1);

namespace Orchestra\Library\Download;

use Orchestra\Library\Capabilities;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileService;
use Orchestra\Library\File\LibraryFile;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Piece\PieceRepository;

/**
 * Delivers stored files through `admin-post.php`, after checking the user's permissions.
 *
 * Downloads change nothing, so they need no nonce; links stay valid across sessions.
 */
final class DownloadController
{
    public const FILE_ACTION = 'orchestra_library_download';
    public const BUNDLE_ACTION = 'orchestra_library_download_bundle';

    private const MAX_BUNDLE_PARTS = 200;

    public function __construct(
        private readonly FileRepository $files,
        private readonly FileService $fileService,
        private readonly PartRepository $parts,
        private readonly PieceRepository $pieces,
        private readonly ZipBundler $zipBundler,
    ) {
    }

    public static function fileUrl(int $fileId): string
    {
        return \add_query_arg(['action' => self::FILE_ACTION, 'file' => $fileId], \admin_url('admin-post.php'));
    }

    /**
     * @param list<int> $partIds
     */
    public static function bundleUrl(array $partIds, string $archiveName): string
    {
        return \add_query_arg(
            [
                'action' => self::BUNDLE_ACTION,
                'parts' => \implode(',', $partIds),
                'name' => \rawurlencode($archiveName),
            ],
            \admin_url('admin-post.php')
        );
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::FILE_ACTION, [$this, 'downloadFile']);
        \add_action('admin_post_' . self::BUNDLE_ACTION, [$this, 'downloadBundle']);
        \add_action('admin_post_nopriv_' . self::FILE_ACTION, 'auth_redirect');
        \add_action('admin_post_nopriv_' . self::BUNDLE_ACTION, 'auth_redirect');
    }

    /**
     * @internal Hooked to `admin_post_orchestra_library_download`.
     */
    public function downloadFile(): never
    {
        $fileId = \is_string($_GET['file'] ?? null) ? \absint($_GET['file']) : 0;
        $file = $this->files->find($fileId);
        if ($file === null || !$this->canDownload($file)) {
            self::notFound();
        }

        $path = $this->fileService->path($file);
        if (!\is_readable($path)) {
            self::notFound();
        }

        self::send($path, $file->mimeType, $file->originalName);
    }

    /**
     * @internal Hooked to `admin_post_orchestra_library_download_bundle`.
     */
    public function downloadBundle(): never
    {
        $partIds = \array_slice(
            \array_values(\array_filter(\array_map(
                'absint',
                \explode(',', \is_string($_GET['parts'] ?? null) ? $_GET['parts'] : '')
            ))),
            0,
            self::MAX_BUNDLE_PARTS
        );

        $entries = [];
        $files = $this->files->forParts($partIds);
        foreach ($this->parts->findMany($partIds) as $part) {
            $file = $files[$part->id] ?? null;
            if ($file === null || !$this->canDownload($file)) {
                continue;
            }
            $path = $this->fileService->path($file);
            if (!\is_readable($path)) {
                continue;
            }

            $title = $this->pieces->find($part->pieceId)->title ?? '';
            $extension = $file->extension() === '' ? '' : '.' . $file->extension();
            $entries[] = ['path' => $path, 'name' => "{$title} - {$part->name}{$extension}"];
        }

        if ($entries === []) {
            self::notFound();
        }

        $archiveName = \sanitize_file_name(\is_string($_GET['name'] ?? null) ? \wp_unslash($_GET['name']) : '');
        $archive = $this->zipBundler->bundle($entries, \get_temp_dir());
        \register_shutdown_function(static function () use ($archive): void {
            if (\is_file($archive)) {
                \unlink($archive);
            }
        });

        self::send($archive, 'application/zip', ($archiveName === '' ? 'sheet-music' : $archiveName) . '.zip');
    }

    private function canDownload(LibraryFile $file): bool
    {
        // read_post denies pieces that are not published (drafts) unless the user may edit them.
        return \current_user_can(Capabilities::SHEET_MUSIC_DOWNLOAD)
            && \current_user_can('read_post', $file->pieceId);
    }

    private static function send(string $path, string $mimeType, string $fileName): never
    {
        while (\ob_get_level() > 0) {
            \ob_end_clean();
        }

        \nocache_headers();
        \header('Content-Type: ' . $mimeType);
        \header('Content-Length: ' . (string) \filesize($path));
        \header('Content-Disposition: ' . ContentDisposition::attachment($fileName));
        \header('X-Content-Type-Options: nosniff');
        \header('Cache-Control: private, no-store, max-age=0');

        \readfile($path);
        exit;
    }

    private static function notFound(): never
    {
        \wp_die(
            \esc_html__('The file does not exist or you are not allowed to download it.', 'orchestra-library'),
            \esc_html__('File not found', 'orchestra-library'),
            ['response' => 404, 'back_link' => true]
        );
    }
}
