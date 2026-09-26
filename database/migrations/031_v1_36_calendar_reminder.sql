-- V1.36-B Calendar reminder setting for the Dashboard Notification Center.
-- Apply after 030_v1_36_notification_center.sql.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- Rollback (only after rolling application code back):
-- ALTER TABLE <DB_TABLE_PREFIX>calendar_event DROP COLUMN calendar_event_reminder;

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @calendar_event_table = CONCAT(@table_prefix, 'calendar_event');
SET @quoted_calendar_event_table = CONCAT('`', REPLACE(@calendar_event_table, '`', '``'), '`');

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @calendar_event_table
     AND COLUMN_NAME = 'calendar_event_reminder'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_calendar_event_table,
    ' ADD COLUMN `calendar_event_reminder` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''none'' AFTER `calendar_event_repeat_until`'),
  'SELECT 1');
PREPARE v136b_stmt FROM @sql; EXECUTE v136b_stmt; DEALLOCATE PREPARE v136b_stmt;

SELECT
  @table_prefix AS configured_table_prefix,
  @calendar_event_table AS target_table,
  CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
