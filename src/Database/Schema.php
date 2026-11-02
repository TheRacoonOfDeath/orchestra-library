<?php

declare(strict_types=1);

namespace Orchestra\Library\Database;

use wpdb;

/**
 * Creates and migrates the custom tables of orchestra-library.
 */
final class Schema
{
    /** Increase whenever a table definition changes; dbDelta() applies the difference. */
    public const VERSION = 2;

    public const OPTION_VERSION = 'orchestra_library_schema_version';

    public function __construct(private readonly wpdb $wpdb)
    {
    }

    public function partsTable(): string
    {
        return $this->wpdb->prefix . 'orchestra_library_parts';
    }

    public function filesTable(): string
    {
        return $this->wpdb->prefix . 'orchestra_library_files';
    }

    public function filesTableExists(): bool
    {
        $table = $this->filesTable();

        return $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * @return int|null The version the tables had before, or null if they were up to date
     */
    public function migrateIfOutdated(): ?int
    {
        $installed = \get_option(self::OPTION_VERSION, 0);
        $installed = \is_numeric($installed) ? (int) $installed : 0;
        if ($installed >= self::VERSION) {
            return null;
        }

        $this->migrate();

        return $installed;
    }

    /**
     * Drops all tables. Only for uninstalling.
     */
    public function drop(): void
    {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->filesTable()}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->partsTable()}");
        \delete_option(self::OPTION_VERSION);
    }

    private function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $this->wpdb->get_charset_collate();

        // dbDelta() is picky: one column per line, two spaces after PRIMARY KEY, KEY instead of INDEX.
        \dbDelta("CREATE TABLE {$this->partsTable()} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  piece_id bigint(20) unsigned NOT NULL,
  name varchar(191) NOT NULL,
  description text NOT NULL,
  position int(11) NOT NULL DEFAULT 0,
  instrument_id int(10) unsigned NOT NULL DEFAULT 0,
  solo tinyint(1) unsigned NOT NULL DEFAULT 0,
  number tinyint(3) unsigned NOT NULL DEFAULT 0,
  note varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY piece_name (piece_id,name),
  KEY piece_id (piece_id),
  KEY instrument_id (instrument_id)
) {$charsetCollate};");

        // part_id is NULL for piece files; a part has at most one file.
        \dbDelta("CREATE TABLE {$this->filesTable()} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  piece_id bigint(20) unsigned NOT NULL,
  part_id bigint(20) unsigned DEFAULT NULL,
  storage_name char(36) NOT NULL,
  original_name varchar(255) NOT NULL,
  mime_type varchar(100) NOT NULL,
  size bigint(20) unsigned NOT NULL,
  uploaded_by bigint(20) unsigned NOT NULL,
  uploaded_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY part_id (part_id),
  KEY piece_id (piece_id)
) {$charsetCollate};");

        \update_option(self::OPTION_VERSION, self::VERSION, true);
    }
}
