<?php

/**
 * Removes all pieces, parts, files and options when the plugin is deleted.
 *
 * Deactivating the plugin intentionally keeps all data.
 */

declare(strict_types=1);

use Orchestra\Library\Database\Schema;
use Orchestra\Library\File\FileRepository;
use Orchestra\Library\File\FileStorage;
use Orchestra\Library\Piece\PiecePostType;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

$orchestraLibraryDatabase = $GLOBALS['wpdb'];
if (! $orchestraLibraryDatabase instanceof wpdb) {
    exit;
}
$orchestraLibrarySchema = new Schema($orchestraLibraryDatabase);
$orchestraLibraryStorage = FileStorage::fromWordPress();

if ($orchestraLibrarySchema->filesTableExists()) {
    $orchestraLibraryFiles = new FileRepository($orchestraLibraryDatabase, $orchestraLibrarySchema);
    foreach ($orchestraLibraryFiles->all() as $orchestraLibraryFile) {
        $orchestraLibraryStorage->delete($orchestraLibraryFile->storageName);
    }
}
$orchestraLibraryStorage->removeDirectory();
$orchestraLibrarySchema->drop();

$orchestraLibraryPieceIds = get_posts([
    'post_type' => PiecePostType::NAME,
    'post_status' => array_keys(get_post_stati()),
    'numberposts' => -1,
    'fields' => 'ids',
]);
foreach ($orchestraLibraryPieceIds as $orchestraLibraryPieceId) {
    wp_delete_post((int) $orchestraLibraryPieceId, true);
}

delete_option(FileStorage::OPTION_DIRECTORY_SUFFIX);
