-- V1.1-H Task storage table
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_task = CONCAT('`', @table_prefix, 'task`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_task, ' (',
  '`task_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`task_date` DATETIME NOT NULL,',
  '`task_updated_at` DATETIME NOT NULL,',
  '`task_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`task_owner` INT UNSIGNED NOT NULL,',
  '`task_widget_id` BIGINT UNSIGNED NOT NULL,',
  '`task_title` VARCHAR(256) NOT NULL,',
  '`task_due_date` DATE NULL DEFAULT NULL,',
  '`task_priority` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''normal'',',
  '`task_completed` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`task_completed_at` DATETIME NULL DEFAULT NULL,',
  '`task_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  'PRIMARY KEY (`task_id`),',
  'KEY `idx_task_owner_widget_flag_order` (`task_owner`, `task_widget_id`, `task_flag`, `task_sort_order`, `task_id`),',
  'KEY `idx_task_owner_due` (`task_owner`, `task_flag`, `task_completed`, `task_due_date`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Task保管'''
);
PREPARE v11h_stmt FROM @sql; EXECUTE v11h_stmt; DEALLOCATE PREPARE v11h_stmt;
