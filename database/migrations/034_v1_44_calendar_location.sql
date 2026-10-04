-- V1.44-B Calendar event location + Google Maps search link support.
-- Existing DB migration for MySQL / MariaDB. Safe to run repeatedly.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_calendar_event_exception = CONCAT('`', @table_prefix, 'calendar_event_exception`');

SELECT COUNT(*) INTO @v144b_has_event_location
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = CONCAT(@table_prefix, 'calendar_event')
  AND COLUMN_NAME = 'calendar_event_location';

SET @sql = IF(
  @v144b_has_event_location = 0,
  CONCAT('ALTER TABLE ', @t_calendar_event,
    ' ADD COLUMN `calendar_event_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_url`'),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql; EXECUTE v144b_stmt; DEALLOCATE PREPARE v144b_stmt;

SELECT COUNT(*) INTO @v144b_has_exception_location
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = CONCAT(@table_prefix, 'calendar_event_exception')
  AND COLUMN_NAME = 'calendar_event_exception_location';

SET @sql = IF(
  @v144b_has_exception_location = 0,
  CONCAT('ALTER TABLE ', @t_calendar_event_exception,
    ' ADD COLUMN `calendar_event_exception_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_exception_url`'),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql; EXECUTE v144b_stmt; DEALLOCATE PREPARE v144b_stmt;
