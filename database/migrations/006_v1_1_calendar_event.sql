-- V1.1-I Calendar event storage table
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_calendar_event, ' (',
  '`calendar_event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_event_date` DATETIME NOT NULL,',
  '`calendar_event_updated_at` DATETIME NOT NULL,',
  '`calendar_event_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_event_owner` INT UNSIGNED NOT NULL,',
  '`calendar_event_title` VARCHAR(256) NOT NULL,',
  '`calendar_event_start_date` DATE NOT NULL,',
  '`calendar_event_end_date` DATE NOT NULL,',
  '`calendar_event_note` TEXT NOT NULL,',
  'PRIMARY KEY (`calendar_event_id`),',
  'KEY `idx_calendar_event_owner_range` (`calendar_event_owner`, `calendar_event_flag`, `calendar_event_start_date`, `calendar_event_end_date`, `calendar_event_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar予定保管'''
);
PREPARE v11i_stmt FROM @sql; EXECUTE v11i_stmt; DEALLOCATE PREPARE v11i_stmt;
