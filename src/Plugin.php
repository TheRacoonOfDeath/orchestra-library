<?php

declare(strict_types=1);

namespace Orchestra\Library;

use LogicException;
use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Library\Admin\Notices;
use Orchestra\Library\Admin\PieceEditor;
use Orchestra\Library\Admin\PieceListColumns;
use Orchestra\Library\Database\Schema;
use Orchestra\Library\Download\DownloadController;
use Orchestra\Library\Download\ZipBundler;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileService;
use Orchestra\Library\File\FileStorage;
use Orchestra\Library\File\UploadValidator;
use Orchestra\Library\Frontend\LibraryShortcode;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Part\PartService;
use Orchestra\Library\Piece\DeletionGuard;
use Orchestra\Library\Piece\PiecePostType;
use Orchestra\Library\Piece\PieceRepository;
use wpdb;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

final class Plugin
{
    /**
     * Update metadata, attached to every GitHub release by build-plugin.sh. This download link
     * always points to the latest release and, unlike the GitHub API, has no rate limit for
     * requests without a token.
     */
    private const UPDATE_METADATA_URL =
        'https://github.com/TheRacoonOfDeath/orchestra-library/releases/latest/download/update.json';

    private static ?self $instance = null;

    private readonly Schema $schema;
    private readonly PieceRepository $pieces;
    private readonly PartRepository $parts;
    private readonly FileRepository $files;
    private readonly FileService $fileService;
    private readonly PartService $partService;
    private readonly LibraryService $library;

    private function __construct(private readonly string $pluginFile, wpdb $wpdb)
    {
        $this->schema = new Schema($wpdb);
        $this->pieces = new PieceRepository();
        $this->parts = new PartRepository($wpdb, $this->schema);
        $this->files = new FileRepository($wpdb, $this->schema);
        $this->fileService = new FileService($this->files, FileStorage::fromWordPress());
        $this->partService = new PartService($this->parts, $this->fileService);
        $this->library = new LibraryService($this->pieces, $this->parts, $this->files);
    }

    public static function boot(string $pluginFile): self
    {
        if (self::$instance === null) {
            $wpdb = $GLOBALS['wpdb'] ?? null;
            if (!$wpdb instanceof wpdb) {
                throw new LogicException('orchestra-library requires a WordPress database connection.');
            }

            self::$instance = new self($pluginFile, $wpdb);
            self::$instance->register();
        }

        return self::$instance;
    }

    /**
     * The running plugin. Other plugins use it to access {@see LibraryService}.
     */
    public static function instance(): self
    {
        return self::$instance ?? throw new LogicException('orchestra-library is not loaded.');
    }

    public function library(): LibraryService
    {
        return $this->library;
    }

    private function register(): void
    {
        $this->registerUpdateChecker();
        \add_action('plugins_loaded', [$this, 'registerFeatures']);
    }

    /**
     * @internal Hooked to `plugins_loaded`, when orchestra-core is guaranteed to be loaded if active.
     */
    public function registerFeatures(): void
    {
        if (!\class_exists(CapabilityRegistry::class)) {
            \add_action('admin_notices', [$this, 'renderMissingCoreNotice']);
            return;
        }

        $this->schema->migrateIfOutdated();

        \add_action('orchestra_core_register_capabilities', [Capabilities::class, 'register']);
        \add_action('init', [PiecePostType::class, 'register']);

        (new DeletionGuard($this->partService))->register();
        (new DownloadController($this->files, $this->fileService, $this->parts, $this->pieces, new ZipBundler()))
            ->register();
        (new LibraryShortcode($this->pieces, $this->parts, $this->files))->register();

        if (\is_admin()) {
            $notices = new Notices();
            $notices->register();
            (new PieceEditor(
                $this->pieces,
                $this->parts,
                $this->partService,
                $this->files,
                $this->fileService,
                new UploadValidator(),
                $notices,
            ))->register();
            (new PieceListColumns($this->pieces, $this->parts))->register();
        }
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function renderMissingCoreNotice(): void
    {
        echo '<div class="notice notice-error"><p>'
            . \esc_html__('Orchestra Library requires the Orchestra Core plugin to be active.', 'orchestra-library')
            . '</p></div>';
    }

    private function registerUpdateChecker(): void
    {
        PucFactory::buildUpdateChecker(self::UPDATE_METADATA_URL, $this->pluginFile, 'orchestra-library');
    }
}
