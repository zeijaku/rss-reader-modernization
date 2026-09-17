-- V1.35 Gmail OAuth2 authentication mode.
-- Additive MySQL / MariaDB migration. Existing encrypted passwords are untouched.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @mail_account_table = CONCAT(@table_prefix, 'mail_account');
SET @quoted_mail_account_table = CONCAT('`', REPLACE(@mail_account_table, '`', '``'), '`');

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_auth_type'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_auth_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''password'' AFTER `mail_account_username`'),
  'SELECT 1');
PREPARE v135_oauth_stmt FROM @sql; EXECUTE v135_oauth_stmt; DEALLOCATE PREPARE v135_oauth_stmt;

SELECT
  @table_prefix AS configured_table_prefix,
  @mail_account_table AS target_table,
  CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
