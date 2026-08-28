-- V1.25-B Calendar event time / all-day / URL foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Existing events are preserved as all-day events; time / URL stay NULL.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'calendar_event');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_all_day'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_all_day` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `calendar_event_color`'
  ),
  'SELECT 1'
);
PREPARE v125b_all_day_stmt FROM @sql; EXECUTE v125b_all_day_stmt; DEALLOCATE PREPARE v125b_all_day_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_start_time'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_start_time` TIME NULL DEFAULT NULL AFTER `calendar_event_all_day`'
  ),
  'SELECT 1'
);
PREPARE v125b_start_time_stmt FROM @sql; EXECUTE v125b_start_time_stmt; DEALLOCATE PREPARE v125b_start_time_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_end_time'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_end_time` TIME NULL DEFAULT NULL AFTER `calendar_event_start_time`'
  ),
  'SELECT 1'
);
PREPARE v125b_end_time_stmt FROM @sql; EXECUTE v125b_end_time_stmt; DEALLOCATE PREPARE v125b_end_time_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_url'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_url` VARCHAR(2048) NULL DEFAULT NULL AFTER `calendar_event_end_time`'
  ),
  'SELECT 1'
);
PREPARE v125b_url_stmt FROM @sql; EXECUTE v125b_url_stmt; DEALLOCATE PREPARE v125b_url_stmt;
