-- Add one optional mark per owner and date.
-- Set @table_prefix to the same DB_TABLE_PREFIX as config/local.php.
SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_day_mark = CONCAT('`', @table_prefix, 'calendar_day_mark`');
SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_calendar_day_mark, ' (',
  '`mark_owner` INT UNSIGNED NOT NULL,',
  '`mark_date` DATE NOT NULL,',
  '`mark_label` VARCHAR(12) NOT NULL,',
  '`mark_note` VARCHAR(120) NOT NULL,',
  'PRIMARY KEY (`mark_owner`, `mark_date`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
PREPARE v153_day_mark_stmt FROM @sql;
EXECUTE v153_day_mark_stmt;
DEALLOCATE PREPARE v153_day_mark_stmt;
