# orchestra-library
Library for storing and assigning sheet music.

Part of the Orchestra plugin suite. Requires the [orchestra-core](https://github.com/TheRacoonOfDeath/orchestra-core) plugin.

## Features

- Pieces with composer, description and archived status (**Library** menu in wp-admin)
- Parts per piece, each with exactly one sheet music file
- Additional files for the whole piece, e.g. the full score
- Private file storage and permission-checked downloads, including zip downloads of several parts
- A library page for members: add the shortcode `[orchestra_library]` to any page
- An API for the other Orchestra plugins

## Installation

1. Install and activate orchestra-core.
2. Upload `orchestra-library-{VERSION}.zip` under **Plugins → Add New → Upload Plugin** and activate it.
3. **On nginx**, protect the file storage, see [docs/security.md](docs/security.md).
4. Create a page with `[orchestra_library]` for members.

Updates are installed from the GitHub releases of this repository.

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
3. Run `./build-plugin.sh`; it creates `build/orchestra-library-{VERSION}.zip` with production
   dependencies only.
4. Create a GitHub release for the version and attach the zip.
