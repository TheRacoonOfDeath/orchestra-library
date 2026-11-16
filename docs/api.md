# API for Other Plugins

Other plugins must not read the library's tables, post type or meta directly. They use the
`LibraryService` and the hooks below.

## LibraryService

```php
use Orchestra\Library\Plugin;

$library = Plugin::instance()->library();
```

Only call it from `plugins_loaded` or later, and only if orchestra-library is active
(declare `Requires Plugins: orchestra-library`).

| Method | Returns |
|--------|---------|
| `piece(int $pieceId)` | `?Piece`: `id`, `title`, `composer`, `description`, `archived`, `published` |
| `searchPieces(string $query = '', bool $includeArchived = false, int $limit = 100)` | `list<Piece>`: published pieces matching title, description or composer, ordered by title |
| `part(int $partId)` | `?Part`: `id`, `pieceId`, `name` (the label of the voice, e.g. "Solo Gitarre 1 (tief)"), `description`, `position`, and since 0.5 `instrumentId` (0 for none), `solo`, `number` (0 for none), `note` |
| `parts(int $pieceId)` | `list<Part>` ordered by position |
| `partsByIds(list<int> $partIds)` | `list<Part>` in the given order |
| `instruments()` | `list<Instrument>` (`id`, `name`, and since 0.11 `shortName`, `''` for none) sorted by name (since 0.5) |
| `shortName(Part $part)` | since 0.11: the label with the short name of the instrument, e.g. "Solo Git 1 (tief)"; the name if the instrument has no short name or the part has no instrument |
| `sheetMusic(int $partId)` | `?LibraryFile` of the part |
| `pieceFiles(int $pieceId)` | `list<LibraryFile>` of the piece itself (score, notes, …) |
| `downloadUrl(LibraryFile $file)` | URL that downloads the file |
| `bundleDownloadUrl(list<int> $partIds, string $archiveName)` | URL that downloads the sheet music of the parts as one zip |

Download URLs check the permissions of the user who opens them, so they can be shown to anyone.
Piece files have a `displayName` (since 0.9) to show instead of `originalName`.

Example: the sheet music of a member's parts in orchestra-projects, one link per part and one zip
for all of them:

```php
$file = $library->sheetMusic($partId);
$url = $file === null ? null : $library->downloadUrl($file);
$zipUrl = $library->bundleDownloadUrl($assignedPartIds, $project->title);
```

Archived pieces must not be added to new programs; check `$piece->archived`.

## Filters

### `orchestra_library_piece_delete_blockers`

Prevents deleting or trashing a piece that is still referenced.

```php
add_filter('orchestra_library_piece_delete_blockers', function (array $blockers, int $pieceId): array {
    foreach ($programs->projectsContainingPiece($pieceId) as $project) {
        $blockers[] = sprintf('Used in the program of %s.', $project->name);
    }
    return $blockers;
}, 10, 2);
```

### `orchestra_library_part_delete_blockers`

Same for parts, e.g. while members are assigned to the part. Blockers of a piece's parts also block
deleting the piece.

### `orchestra_library_allowed_file_types`

Extensions and MIME types that may be uploaded, in the format of WordPress's `upload_mimes`.
Default: `['pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']`.
The type is detected from the file content, so the extension and content must match.

### `orchestra_library_allowed_piece_file_types`

Since 0.11: the types allowed for the files of a whole piece. Default: the types of
`orchestra_library_allowed_file_types`, plus `'zip' => 'application/zip'` and
`'mid|midi' => 'audio/midi'`. Sheet music of a part stays limited to the first filter. WordPress
must allow the types as well (`upload_mimes`), which it does for ZIP and MIDI by default.
