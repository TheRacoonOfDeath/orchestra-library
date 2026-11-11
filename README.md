# orchestra-library
Library for storing and assigning sheet music.

Part of the Orchestra plugin suite. Requires the [orchestra-core](https://github.com/TheRacoonOfDeath/orchestra-core) plugin.

## Features

- Pieces with composer, description and archived status (**Library** menu in wp-admin)
- Parts per piece, each with exactly one sheet music file, ordered by dragging
- Each part is a voice: an instrument, solo, a number and a free text, e.g. "Solo Gitarre 1 (tief)"
- Conductors and librarians maintain the instruments (alphabetical list; Bass, Gitarre, Oktavgitarre and
  Quartgitarre to start with) under **Instruments** on the library page or in wp-admin
- Additional files for the whole piece, e.g. the full score, each with a name that is shown in the
  library instead of the file name
- Private file storage and permission-checked downloads, including zip downloads of several parts
- The library page `[orchestra_library]`: members search and download; conductors and librarians create and edit
  pieces, parts and files there as well (or in **Library** in wp-admin). Every part and every file
  of a piece has its own "Download" button; parts without a file say "No sheet music"
- Privacy (GDPR): no download logs and no record of who uploaded a file; a suggested text for the privacy policy in **Settings → Privacy**
- An API for the other Orchestra plugins

## Installation

1. Install and activate orchestra-core 0.6 or later (earlier versions cannot add or drag parts).
2. Upload `orchestra-library-{VERSION}.zip` under **Plugins → Add New → Upload Plugin** and activate it.
3. **On nginx**, protect the file storage, see [docs/security.md](docs/security.md).
4. Create the Orchestra pages (**Create Orchestra pages** in wp-admin), or put `[orchestra_library]` on a page.

Updates are installed from the GitHub releases of this repository. The plugin reads
`update.json` from the latest release, so the GitHub API and its rate limit are not involved and
no token is needed.

## Documentation

- [Architecture](docs/architecture.md)
- [Data model](docs/data-model.md)
- [API for other plugins](docs/api.md)
- [Security](docs/security.md)

The domain model, use cases and capabilities of the whole suite are documented in orchestra-core.

## Development

```bash
composer install
composer check   # coding standard, static analysis and unit tests
composer e2e     # end-to-end tests in a temporary WordPress (see tests/e2e/run.sh)
```

The end-to-end tests install WordPress with SQLite, the latest orchestra-core release and a fresh
build of this plugin, run them on PHP's built-in web server and test them over HTTP as each role.
They need PHP with `pdo_sqlite`, Python 3, curl, unzip and network access.

## Releasing

1. Run `composer check` and `composer e2e`.
2. Bump `Version:` in `orchestra-library.php`.
3. Commit and push, then push an annotated tag with short user-facing notes:
   `git tag -a v{VERSION} -m "notes" && git push origin v{VERSION}`. GitHub Actions runs
   `composer check`, builds the zip and `update.json` with `./build-plugin.sh`, signs their build
   provenance and creates the release
   ([orchestra-workflows](https://github.com/TheRacoonOfDeath/orchestra-workflows#releases)).
   `./build-plugin.sh` stays available for local test builds.
