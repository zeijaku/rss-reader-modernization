-- V1.1-G Memo storage table
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_memo = CONCAT('`', @table_prefix, 'memo`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_memo, ' (',
  '`memo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`memo_date` DATETIME NOT NULL,',
  '`memo_updated_at` DATETIME NOT NULL,',
  '`memo_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`memo_owner` INT UNSIGNED NOT NULL,',
  '`memo_title` VARCHAR(128) NOT NULL,',
  '`memo_body` TEXT NOT NULL,',
  'PRIMARY KEY (`memo_id`),',
  'KEY `idx_memo_owner_flag_id` (`memo_owner`, `memo_flag`, `memo_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Memo保管'''
);
PREPARE v11g_stmt FROM @sql; EXECUTE v11g_stmt; DEALLOCATE PREPARE v11g_stmt;
