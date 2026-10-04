-- V1.44-A Multiple Calendar / Source foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_source = CONCAT('`', @table_prefix, 'calendar_source`');
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_calendar_source, ' (',
  '`calendar_source_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_source_date` DATETIME NOT NULL,',
  '`calendar_source_updated_at` DATETIME NOT NULL,',
  '`calendar_source_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_source_owner` INT UNSIGNED NOT NULL,',
  '`calendar_source_name` VARCHAR(40) NOT NULL,',
  '`calendar_source_color` VARCHAR(16) NOT NULL DEFAULT ''blue'',',
  '`calendar_source_default` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_source_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  'PRIMARY KEY (`calendar_source_id`),',
  'KEY `idx_calendar_source_owner` (`calendar_source_owner`, `calendar_source_flag`, `calendar_source_default`, `calendar_source_sort_order`, `calendar_source_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar source'''
);
PREPARE v144a_stmt FROM @sql;
EXECUTE v144a_stmt;
DEALLOCATE PREPARE v144a_stmt;

SELECT COUNT(*) INTO @v144a_has_event_source
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = CONCAT(@table_prefix, 'calendar_event')
  AND COLUMN_NAME = 'calendar_event_source_id';

SET @sql = IF(
  @v144a_has_event_source = 0,
  CONCAT(
    'ALTER TABLE ', @t_calendar_event,
    ' ADD COLUMN `calendar_event_source_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `calendar_event_owner`,',
    ' ADD KEY `idx_calendar_event_source` (`calendar_event_owner`, `calendar_event_source_id`, `calendar_event_flag`, `calendar_event_start_date`)'
  ),
  'SELECT 1'
);
PREPARE v144a_stmt FROM @sql;
EXECUTE v144a_stmt;
DEALLOCATE PREPARE v144a_stmt;

-- Create one default Calendar for every owner that already has Calendar events.
SET @sql = CONCAT(
  'INSERT INTO ', @t_calendar_source, ' ',
  '(`calendar_source_date`, `calendar_source_updated_at`, `calendar_source_flag`, `calendar_source_owner`, ',
  '`calendar_source_name`, `calendar_source_color`, `calendar_source_default`, `calendar_source_sort_order`) ',
  'SELECT NOW(), NOW(), 0, e.`calendar_event_owner`, ''既定Calendar'', ''blue'', 1, 0 ',
  'FROM ', @t_calendar_event, ' e ',
  'LEFT JOIN ', @t_calendar_source, ' s ',
  'ON s.`calendar_source_owner` = e.`calendar_event_owner` ',
  'AND s.`calendar_source_flag` = 0 AND s.`calendar_source_default` = 1 ',
  'WHERE s.`calendar_source_id` IS NULL ',
  'GROUP BY e.`calendar_event_owner`'
);
PREPARE v144a_stmt FROM @sql;
EXECUTE v144a_stmt;
DEALLOCATE PREPARE v144a_stmt;

-- Preserve all existing events by assigning them to the owner's default Calendar.
SET @sql = CONCAT(
  'UPDATE ', @t_calendar_event, ' e ',
  'INNER JOIN ', @t_calendar_source, ' s ',
  'ON s.`calendar_source_owner` = e.`calendar_event_owner` ',
  'AND s.`calendar_source_flag` = 0 AND s.`calendar_source_default` = 1 ',
  'SET e.`calendar_event_source_id` = s.`calendar_source_id` ',
  'WHERE e.`calendar_event_source_id` IS NULL'
);
PREPARE v144a_stmt FROM @sql;
EXECUTE v144a_stmt;
DEALLOCATE PREPARE v144a_stmt;
