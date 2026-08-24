-- V1.20.1-C Calendar event fixed color (red / blue / green)
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Existing events are preserved and default to blue.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'calendar_event');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'calendar_event_color'
);

SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `calendar_event_color` VARCHAR(8) NOT NULL DEFAULT ''blue'' AFTER `calendar_event_note`'
  ),
  'SELECT 1'
);
PREPARE v1201c_stmt FROM @sql; EXECUTE v1201c_stmt; DEALLOCATE PREPARE v1201c_stmt;

SET @sql = CONCAT(
  'UPDATE ', @quoted_table,
  ' SET `calendar_event_color` = ''blue''',
  ' WHERE `calendar_event_color` NOT IN (''red'',''blue'',''green'')'
);
PREPARE v1201c_normalize_stmt FROM @sql; EXECUTE v1201c_normalize_stmt; DEALLOCATE PREPARE v1201c_normalize_stmt;
