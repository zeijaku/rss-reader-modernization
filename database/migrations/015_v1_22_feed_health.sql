-- V1.22-B Feed Health
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Ownership is derived from content.content_owner; user_id is not duplicated here.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'feed_health');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @quoted_table, ' (',
  '`health_content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`last_checked_at` DATETIME NULL,',
  '`last_successful_fetch_at` DATETIME NULL,',
  '`latest_article_at` DATETIME NULL,',
  '`last_result` VARCHAR(16) NOT NULL DEFAULT ''unknown'',',
  '`http_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
  '`error_code` VARCHAR(64) NOT NULL DEFAULT '''',',
  '`error_reason` VARCHAR(255) NOT NULL DEFAULT '''',',
  '`consecutive_failure_count` INT UNSIGNED NOT NULL DEFAULT 0,',
  '`redirected` TINYINT(1) NOT NULL DEFAULT 0,',
  '`effective_url` VARCHAR(1024) NOT NULL DEFAULT '''',',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`health_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Feed Health state'''
);
PREPARE v122b_stmt FROM @sql; EXECUTE v122b_stmt; DEALLOCATE PREPARE v122b_stmt;
