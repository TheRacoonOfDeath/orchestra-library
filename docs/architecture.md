# Orchestra Library Plugin
## Architecture

## Purpose

The Orchestra Library plugin manages musical pieces, parts, and sheet music files.

Its primary goal is to provide secure access to rehearsal material for orchestra members.

The plugin owns all music-related data and acts as the authoritative source for:

- Pieces
- Parts
- Sheet music files

The plugin does not manage:

- Members
- Projects
- Assignments
- Participation

---

## Responsibilities

### Piece Management

Conductors can create and maintain musical pieces.

Examples:

- Pirates of the Caribbean
- Lord of the Dance
- El Camino Real

### Part Management

Each piece can contain a custom set of parts.

A part is a voice: an instrument, optionally "Solo", a number (1 to 20) and a free text, shown
as e.g. "Solo Gitarre 1 (tief)". Examples:

- Gitarre 1
- Gitarre 2
- Solo Oktavgitarre
- Bass (tief)

The instruments come from a list that the conductors maintain ("Instruments" on the library page
and in wp-admin). See [data-model.md](data-model.md#voices).

### Sheet Music Management

Each part has exactly one sheet music file; uploading a new file replaces it.
Files concerning the whole piece (full score, notes) are attached to the piece itself.

Example:

Pirates of the Caribbean
→ Horn 1
→ Pirates_Horn1.pdf

---

## Domain Model

Piece
├── Part
│   └── Sheet Music File (exactly one)
├── Part
│   └── Sheet Music File (exactly one)
├── ...
└── Piece Files (score, notes, …; any number)

The full domain model is documented in orchestra-core (`docs/domain-model.md`).

---

## Ownership

This plugin owns:

- Piece metadata
- Part definitions
- Sheet music files

Other plugins may reference pieces but must not modify library data directly.

---

## Consumers

### orchestra-projects

Uses pieces from the library when defining a project program.

Example:

Project
→ Spring Concert 2027

Program
→ Pirates of the Caribbean
→ Lord of the Dance

### orchestra-members

No direct dependency.

---

## Security

See [security.md](security.md).

---

## Integration Points

See [api.md](api.md): the `LibraryService` for looking up pieces, parts and files and building
download links (including one zip per project), and filters for preventing deletions.

---

## Code Structure

| Namespace | Responsibility |
|-----------|----------------|
| `Orchestra\Library` | `Plugin` (wiring), `Capabilities`, `LibraryService` (public API) |
| `…\Piece` | Post type, repository, deletion guard |
| `…\Part` | Part repository, voices and their labels, instruments, name rules, deletion with blockers |
| `…\File` | File metadata, private storage, upload validation |
| `…\Download` | Download endpoint, zip bundles, `Content-Disposition` |
| `…\Admin` | Piece edit screen, list columns, Library → Instruments |
| `…\Frontend` | `[orchestra_library]` shortcode, piece and instruments forms |
| `…\Database` | Table schema and migrations, row helpers |

---

## Architectural Principles

### Piece-Centric Design

All information is organized around pieces.

### Structured Parts

Parts are defined per piece; no global catalogue of parts exists. Each part is built from an
instrument of a shared list, solo, a number and a free text, so that the same voice is always
written the same way.

### Library Owns Content

The plugin owns all music metadata and files.

Other plugins may reference but not duplicate this information.

### Secure By Default

All sheet music is protected from anonymous access.