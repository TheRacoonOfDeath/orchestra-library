# Data Model

The business model is described in orchestra-core's `docs/domain-model.md`.
This document describes how orchestra-library stores it.

## Pieces

Custom post type `orchestra_piece` (not public, no REST API, classic editor).

| Field | Storage |
|-------|---------|
| Title | `post_title` |
| Description | `post_content` |
| Composer | post meta `orchestra_library_composer` |
| Archived | post meta `orchestra_library_archived` = `1`, absent when not archived |
| Status | `post_status`; only `publish` pieces are visible to members |

## Parts

Table `{prefix}orchestra_library_parts`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | primary key |
| `piece_id` | bigint unsigned | ID of the piece post |
| `name` | varchar(191) | unique per piece, case-insensitive |
| `description` | text | |
| `position` | int | display order within the piece |

## Files

Table `{prefix}orchestra_library_files` holds the metadata of part sheet music and piece files.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | primary key |
| `piece_id` | bigint unsigned | |
| `part_id` | bigint unsigned, nullable | `NULL` for piece files; unique, so a part has at most one file |
| `storage_name` | char(36) | UUID; the file name in the storage directory |
| `original_name` | varchar(255) | sanitized upload name, used for downloads |
| `mime_type` | varchar(100) | detected from the content |
| `size` | bigint unsigned | bytes |
| `uploaded_by` | bigint unsigned | user ID |
| `uploaded_at` | datetime | UTC |

The files themselves are stored in a private directory, see [security.md](security.md).

## Options

| Option | Purpose |
|--------|---------|
| `orchestra_library_schema_version` | Installed table version. Tables are migrated with `dbDelta()` when `Schema::VERSION` is higher. |
| `orchestra_library_storage_suffix` | Random suffix of the default storage directory |

## Lifecycle

- **Deleting a piece** (administrators only) deletes its parts, file metadata and stored files.
  It is refused while other plugins report blockers (see [api.md](api.md)).
- **Deleting a part** deletes its sheet music. It is refused while other plugins report blockers.
  In the piece editor, deleting works with buttons that act right away after a confirmation, and
  the other changes of the form are saved with them: a part (`orchestra_delete_part` = part ID),
  the sheet music of a part (`orchestra_remove_part_file` = part ID) and a piece file
  (`orchestra_delete_piece_file` = file ID).
- **Deactivating** the plugin keeps all data.
- **Deleting** the plugin (`uninstall.php`) removes the pieces, both tables, all stored files, the
  storage directory and the options.
