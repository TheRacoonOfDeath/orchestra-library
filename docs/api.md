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
| `part(int $partId)` | `?Part`: `id`, `pieceId`, `name`, `description`, `position` |
| `parts(int $pieceId)` | `list<Part>` ordered by position |
| `partsByIds(list<int> $partIds)` | `list<Part>` in the given order |
| `sheetMusic(int $partId)` | `?LibraryFile` of the part |
| `pieceFiles(int $pieceId)` | `list<LibraryFile>` of the piece itself (score, notes, …) |
| `downloadUrl(LibraryFile $file)` | URL that downloads the file |
| `bundleDownloadUrl(list<int> $partIds, string $archiveName)` | URL that downloads the sheet music of the parts as one zip |

Download URLs check the permissions of the user who opens them, so they can be shown to anyone.

Example: "Download my sheet music" for a project in orchestra-projects:

```php
$url = $library->bundleDownloadUrl($assignedPartIds, $project->name);
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
