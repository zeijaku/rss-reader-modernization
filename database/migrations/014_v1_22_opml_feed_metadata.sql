-- V1.22-A OPML feed metadata
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Metadata ownership is derived from content.content_owner; user_id is not duplicated here.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'feed_metadata');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @quoted_table, ' (',
  '`metadata_content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`feed_title` VARCHAR(255) NOT NULL DEFAULT '''',',
  '`site_url` VARCHAR(1024) NOT NULL DEFAULT '''',',
  '`category_path` VARCHAR(512) NOT NULL DEFAULT '''',',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`metadata_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''OPML / Feed metadata'''
);
PREPARE v122a_stmt FROM @sql; EXECUTE v122a_stmt; DEALLOCATE PREPARE v122a_stmt;
