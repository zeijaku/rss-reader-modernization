-- V1.41: opt-in visual deadlines; independent of notification reminders.
-- Change ig_ to the configured DB_TABLE_PREFIX. Safe to run repeatedly.
SET @table_prefix = 'ig_';
SET @prefix_ok = IF(@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,31}$', 1, 0);

SET @target_table = CONCAT(@table_prefix, 'calendar_event');
SET @quoted_table = CONCAT('`', REPLACE(@target_table, '`', '``'), '`');
SET @column_exists = IF(@prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = @target_table AND COLUMN_NAME = 'calendar_event_deadline_highlight'), 1);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_table, ' ADD COLUMN `calendar_event_deadline_highlight` TINYINT UNSIGNED NOT NULL DEFAULT 0'), 'SELECT 1');
PREPARE v141_stmt FROM @sql;
EXECUTE v141_stmt;
DEALLOCATE PREPARE v141_stmt;

SET @target_table = CONCAT(@table_prefix, 'calendar_event_exception');
SET @quoted_table = CONCAT('`', REPLACE(@target_table, '`', '``'), '`');
SET @column_exists = IF(@prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = @target_table AND COLUMN_NAME = 'calendar_event_exception_deadline_highlight'), 1);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_table, ' ADD COLUMN `calendar_event_exception_deadline_highlight` TINYINT UNSIGNED NULL DEFAULT NULL'), 'SELECT 1');
PREPARE v141_stmt FROM @sql;
EXECUTE v141_stmt;
DEALLOCATE PREPARE v141_stmt;

SELECT @table_prefix AS configured_table_prefix, CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
