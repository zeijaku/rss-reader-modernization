-- V1.27-D Secure File Upload / user-owned file metadata foundation.
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Uploaded binary data is stored outside public/; this table stores metadata only.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'user_file');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @table_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
);

SET @sql = IF(
  @table_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_table, ' (',
    '`file_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`file_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`file_original_name` VARCHAR(255) NOT NULL,',
    '`file_stored_name` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`file_mime_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`file_extension` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`file_size` BIGINT UNSIGNED NOT NULL,',
    '`file_created_at` DATETIME NOT NULL,',
    '`file_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:deleted'',',
    'PRIMARY KEY (`file_id`),',
    'UNIQUE KEY `uq_user_file_stored_name` (`file_stored_name`),',
    'KEY `idx_user_file_owner_flag_id` (`file_owner`, `file_flag`, `file_id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User-owned file metadata'''
  ),
  'SELECT 1'
);
PREPARE v127d_user_file_stmt FROM @sql; EXECUTE v127d_user_file_stmt; DEALLOCATE PREPARE v127d_user_file_stmt;
