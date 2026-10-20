<?php

declare(strict_types=1);

namespace Orchestra\Library;

use LogicException;
use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Menu;
use Orchestra\Core\Ui\MenuItem;
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
use Orchestra\Library\Frontend\PieceController;
use Orchestra\Library\Part\PartRepository;
use Orchestra\Library\Part\PartService;
use Orchestra\Library\Piece\DeletionGuard;
use Orchestra\Library\Piece\PieceForm;
use Orchestra\Library\Piece\PieceFormHandler;
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
        \add_action('init', [$this, 'loadTextDomain'], 0);
        \add_action('plugins_loaded', [$this, 'registerFeatures']);
    }

    /**
     * The language is chosen in orchestra-core (`Orchestra\Core\Ui\Language`).
     *
     * @internal Hooked to `init`, before anything is translated.
     */
    public function loadTextDomain(): void
    {
        $directory = \dirname(\plugin_basename($this->pluginFile)) . '/languages';
        \load_plugin_textdomain('orchestra-library', false, $directory);
    }

    /**
     * @internal Hooked to `plugins_loaded`, when orchestra-core is guaranteed to be loaded if active.
     */
    public function registerFeatures(): void
    {
        // The shared UI exists since orchestra-core 0.4.
        if (!\class_exists(CapabilityRegistry::class) || !\class_exists(Html::class)) {
            \add_action('admin_notices', [$this, 'renderMissingCoreNotice']);
            return;
        }

        $this->schema->migrateIfOutdated();

        \add_action('orchestra_core_register_capabilities', [Capabilities::class, 'register']);
        \add_action('init', [PiecePostType::class, 'register']);

        (new DeletionGuard($this->partService))->register();
        (new DownloadController($this->files, $this->fileService, $this->parts, $this->pieces, new ZipBundler()))
            ->register();
        $form = new PieceForm($this->parts, $this->files);
        $handler = new PieceFormHandler(
            $this->pieces,
            $this->parts,
            $this->partService,
            $this->files,
            $this->fileService,
            new UploadValidator()
        );
        (new LibraryShortcode($this->pieces, $this->parts, $this->files, $form))->register();
        (new PieceController($handler))->register();
        \add_action(Menu::REGISTER_ACTION, [self::class, 'registerMenuItem']);

        if (\is_admin()) {
            (new PieceEditor($this->pieces, $form, $handler))->register();
            (new PieceListColumns($this->pieces, $this->parts))->register();
        }
    }

    /**
     * @internal Hooked to `orchestra_core_register_menu_items`.
     */
    public static function registerMenuItem(Menu $menu): void
    {
        $menu->add(new MenuItem(
            LibraryShortcode::TAG,
            \__('Library', 'orchestra-library'),
            // The menu is built for the current user.
            \current_user_can(Capabilities::PIECE_EDIT)
                ? \__('Pieces and sheet music; add and edit pieces.', 'orchestra-library')
                : \__('Pieces and sheet music.', 'orchestra-library'),
            static fn (int $userId): bool => \user_can($userId, Capabilities::PIECE_READ),
            30
        ));
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function renderMissingCoreNotice(): void
    {
        echo '<div class="notice notice-error"><p>'
            . \esc_html__('Orchestra Library requires Orchestra Core 0.4 or later to be active.', 'orchestra-library')
            . '</p></div>';
    }

    private function registerUpdateChecker(): void
    {
        PucFactory::buildUpdateChecker(self::UPDATE_METADATA_URL, $this->pluginFile, 'orchestra-library');
    }
}
