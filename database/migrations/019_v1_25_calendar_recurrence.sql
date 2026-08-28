-- V1.25-D Calendar recurring-event foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Existing events remain non-recurring.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'calendar_event');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_repeat_type'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_repeat_type` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''none'' AFTER `calendar_event_url`'
  ),
  'SELECT 1'
);
PREPARE v125d_repeat_type_stmt FROM @sql; EXECUTE v125d_repeat_type_stmt; DEALLOCATE PREPARE v125d_repeat_type_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_repeat_until'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_repeat_until` DATE NULL DEFAULT NULL AFTER `calendar_event_repeat_type`'
  ),
  'SELECT 1'
);
PREPARE v125d_repeat_until_stmt FROM @sql; EXECUTE v125d_repeat_until_stmt; DEALLOCATE PREPARE v125d_repeat_until_stmt;
