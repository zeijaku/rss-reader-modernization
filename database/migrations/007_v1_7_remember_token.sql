-- V1.7-E persistent-login remember token table
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_remember_token = CONCAT('`', @table_prefix, 'remember_token`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_remember_token, ' (',
  '`remember_token_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`remember_token_user_id` INT UNSIGNED NOT NULL,',
  '`remember_token_selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remember_token_validator_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remember_token_created_at` DATETIME NOT NULL,',
  '`remember_token_expires_at` DATETIME NOT NULL,',
  '`remember_token_last_used_at` DATETIME NULL DEFAULT NULL,',
  'PRIMARY KEY (`remember_token_id`),',
  'UNIQUE KEY `uq_remember_token_selector` (`remember_token_selector`),',
  'KEY `idx_remember_token_user_expiry` (`remember_token_user_id`, `remember_token_expires_at`),',
  'KEY `idx_remember_token_expiry` (`remember_token_expires_at`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Persistent login token'''
);
PREPARE v17e_stmt FROM @sql; EXECUTE v17e_stmt; DEALLOCATE PREPARE v17e_stmt;
