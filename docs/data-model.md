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
| `name` | varchar(191) | the label of the voice (see below); unique per piece, case-insensitive |
| `description` | text | no longer edited (removed from the piece editor in 0.4.5); stored descriptions are kept, new parts have none |
| `position` | int | display order within the piece |
| `instrument_id` | int unsigned | ID of the instrument (see below); `0` for none |
| `solo` | tinyint(1) | `1` for a solo |
| `number` | tinyint unsigned | `0` for none, otherwise 1 to 20 |
| `note` | varchar(191) | optional free text |

### Voices

Since 0.5, a part is a voice built from an instrument, "Solo", a number and a free text. Every
voice needs an instrument or a free text. Its label, e.g. `Solo Gitarre 1 (tief)`, leaves out the
empty parts (`Gitarre`, `Solo Bass`, `Gitarre 2`); without an instrument, the free text alone is
the label. The label is stored in `name`, so other plugins and the uniqueness check only use the
name. Renaming an instrument renames the labels of its voices.

The migration to schema version 2 converted the free-text names of earlier versions: names such
as `Gitarre 1` or `Solo Bass 2 (tief)` got their instrument, solo and number; all others have no
instrument and keep their name as free text, so their label does not change.

## Instruments

The option `orchestra_library_instruments` holds the instruments as `ID => name`. They are listed
alphabetically. Until a conductor saves them, the defaults apply: Bass (1), Gitarre (2),
Oktavgitarre (3) and Quartgitarre (4); they are data and not translated. A new instrument gets the
highest ID plus one. Names are required, unique ignoring case and at most 60 characters. An
instrument used by a voice cannot be deleted.

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
| `orchestra_library_instruments` | The instruments (see above) |

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
  storage directory and the options, including the instruments.
