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

Examples:

- Horn 1
- Horn 2
- Solo Flute
- Percussion

Part names are not restricted to predefined values.

### Sheet Music Management

Sheet music files can be uploaded for individual parts.

Example:

Pirates of the Caribbean
→ Horn 1
→ Pirates_Horn1.pdf

---

## Domain Model

Piece
├── Part
│   └── Sheet Music File
├── Part
│   └── Sheet Music File
└── ...

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

### Access Control

Only authenticated users may download sheet music.

### File Storage

Sheet music files must not be publicly accessible through direct URLs.

Files are delivered through a protected download endpoint.

### Upload Permissions

Only conductors and administrators may upload or modify sheet music.

---

## Integration Points

### Piece Lookup

Provides services for:

- Fetching pieces
- Fetching parts
- Searching the library

### File Download

Provides secure download functionality for other plugins.

---

## Architectural Principles

### Piece-Centric Design

All information is organized around pieces.

### Flexible Parts

Part names are defined per piece.

No global catalogue of parts exists.

### Library Owns Content

The plugin owns all music metadata and files.

Other plugins may reference but not duplicate this information.

### Secure By Default

All sheet music is protected from anonymous access.