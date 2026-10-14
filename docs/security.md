# Security

## Capabilities

Registered with orchestra-core (see its `docs/capabilities.md`).

| Capability | Granted to | Used for |
|-----------|------------|----------|
| `orchestra_library_piece_read` | members, conductors, organizers | library page |
| `orchestra_library_piece_edit` | conductors | creating, editing and archiving pieces and parts |
| `orchestra_library_piece_delete` | administrators | deleting pieces |
| `orchestra_library_sheet_music_download` | members, conductors, organizers | downloads |
| `orchestra_library_sheet_music_manage` | conductors | uploading, replacing and deleting files |

The post type maps WordPress's post capabilities onto these, so `current_user_can('edit_post', $pieceId)`
works as usual.

## File Storage

Files are never stored in the media library, because attachment URLs are public.

- Directory: `ORCHESTRA_LIBRARY_STORAGE_DIR` if defined in `wp-config.php`, otherwise
  `wp-content/uploads/orchestra-library-<random>/`.
- File names are UUIDs without extension, so they cannot be guessed or executed.
- The directory contains `.htaccess` (`Require all denied`) and an empty `index.php`.

**nginx ignores `.htaccess`.** On nginx, either define `ORCHESTRA_LIBRARY_STORAGE_DIR` outside the
web root (recommended):

```php
define('ORCHESTRA_LIBRARY_STORAGE_DIR', '/var/www/orchestra-private/sheet-music');
```

or deny the directory in the server configuration:

```nginx
location ~ ^/wp-content/uploads/orchestra-library- {
    deny all;
}
```

Changing the directory later requires moving the existing files.

## Uploads

- Only users with `orchestra_library_sheet_music_manage`, on the piece edit screen, with a valid nonce.
- The type is detected from the content (`wp_check_filetype_and_ext()`); only allowed types are
  accepted (see `orchestra_library_allowed_file_types` in [api.md](api.md)).
- The original name is sanitized with `sanitize_file_name()`.

## Downloads

Downloads go through `wp-admin/admin-post.php?action=orchestra_library_download&file=<id>` and
`…?action=orchestra_library_download_bundle&parts=<ids>&name=<name>`.

- Logged-out users are redirected to the login page.
- The user needs `orchestra_library_sheet_music_download` and must be allowed to read the piece,
  so sheet music of draft pieces is only available to conductors and administrators.
- Unknown files and missing permissions both answer 404, so file IDs cannot be probed.
- Responses are sent with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`
  as attachments.
- Downloads change nothing and therefore use no nonce.
