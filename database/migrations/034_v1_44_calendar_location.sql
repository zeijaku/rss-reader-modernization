-- V1.44-B Calendar event location + Google Maps search link support.
-- Existing DB migration for MySQL / MariaDB.
-- Shared-host compatible: does not read information_schema.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
--
-- Idempotence is tracked in the app-owned <prefix>schema_migration table because
-- some shared-hosting MySQL accounts cannot read information_schema.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_calendar_event_exception = CONCAT('`', @table_prefix, 'calendar_event_exception`');
SET @t_schema_migration = CONCAT('`', @table_prefix, 'schema_migration`');

-- Create the migration-state table here as well so V1.44-B remains self-contained.
SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_schema_migration, ' (',
  '`migration_key` VARCHAR(100) NOT NULL,',
  '`applied_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`migration_key`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Application schema migration state'''
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

-- Parent event location.
SET @sql = CONCAT(
  'SELECT COUNT(*) INTO @v144b_event_location_done FROM ', @t_schema_migration,
  ' WHERE `migration_key` = ''034:v1.44-b:event-location'''
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_event_location_done = 0,
  CONCAT(
    'ALTER TABLE ', @t_calendar_event,
    ' ADD COLUMN `calendar_event_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_url`'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @v144b_event_location_verified = 0;
SET @sql = CONCAT(
  'SELECT (COUNT(`calendar_event_location`) >= 0) INTO @v144b_event_location_verified FROM ',
  @t_calendar_event
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_event_location_verified = 1,
  CONCAT(
    'INSERT IGNORE INTO ', @t_schema_migration,
    ' (`migration_key`, `applied_at`) VALUES (''034:v1.44-b:event-location'', NOW())'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

-- Recurrence occurrence-override location.
SET @sql = CONCAT(
  'SELECT COUNT(*) INTO @v144b_exception_location_done FROM ', @t_schema_migration,
  ' WHERE `migration_key` = ''034:v1.44-b:exception-location'''
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_exception_location_done = 0,
  CONCAT(
    'ALTER TABLE ', @t_calendar_event_exception,
    ' ADD COLUMN `calendar_event_exception_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_exception_url`'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @v144b_exception_location_verified = 0;
SET @sql = CONCAT(
  'SELECT (COUNT(`calendar_event_exception_location`) >= 0) INTO @v144b_exception_location_verified FROM ',
  @t_calendar_event_exception
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_exception_location_verified = 1,
  CONCAT(
    'INSERT IGNORE INTO ', @t_schema_migration,
    ' (`migration_key`, `applied_at`) VALUES (''034:v1.44-b:exception-location'', NOW())'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;
