-- V1.29-B Remote File Manager connection foundation.
-- Existing DB migration for MySQL / MariaDB.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- Credentials are stored only as authenticated ciphertext. The encryption key is never stored in the database.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'remote_connection');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @table_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
  ),
  1
);

SET @sql = IF(
  @table_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_table, ' (',
    '`remote_connection_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`remote_connection_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`remote_connection_name` VARCHAR(128) NOT NULL,',
    '`remote_connection_protocol` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`remote_connection_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`remote_connection_port` SMALLINT UNSIGNED NOT NULL,',
    '`remote_connection_username` VARCHAR(320) NOT NULL,',
    '`remote_connection_auth_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`remote_connection_secret` MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted credential envelope'',',
    '`remote_connection_base_path` VARCHAR(2048) NOT NULL DEFAULT ''/'',',
    '`remote_connection_allow_private` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:public policy/1:admin private CIDR policy'',',
    '`remote_connection_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''0:disabled/1:enabled'',',
    '`remote_connection_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:deleted'',',
    '`remote_connection_created_at` DATETIME NOT NULL,',
    '`remote_connection_updated_at` DATETIME NOT NULL,',
    'PRIMARY KEY (`remote_connection_id`),',
    'KEY `idx_remote_connection_owner_flag_id` (`remote_connection_owner`, `remote_connection_flag`, `remote_connection_id`),',
    'KEY `idx_remote_connection_owner_enabled_flag` (`remote_connection_owner`, `remote_connection_enabled`, `remote_connection_flag`, `remote_connection_id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User-owned remote file connections'''
  ),
  'SELECT 1'
);
PREPARE v129b_remote_connection_stmt FROM @sql;
EXECUTE v129b_remote_connection_stmt;
DEALLOCATE PREPARE v129b_remote_connection_stmt;
