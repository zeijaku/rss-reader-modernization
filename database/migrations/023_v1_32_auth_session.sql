-- V1.32-G Session Management registry.
-- Existing PHP session contents remain filesystem-backed under var/session.
-- This table stores only a hash of a separate random registry token, a bounded
-- client label, timestamps, and an optional Remember selector (never validator/cookie value).
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @session_table = CONCAT(@table_prefix, 'auth_session');
SET @quoted_session_table = CONCAT('`', REPLACE(@session_table, '`', '``'), '`');
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');

SET @session_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @session_table
  ),
  1
);

SET @sql = IF(
  @session_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_session_table, ' (',
    '`auth_session_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`auth_session_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`auth_session_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''SHA-256 of user-bound random Session Registry token'',',
    '`auth_session_remember_selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Remember selector only; validator/cookie value is never stored here'',',
    '`auth_session_client_label` VARCHAR(120) NOT NULL COMMENT ''Privacy-bounded Browser/platform label; no IP address'',',
    '`auth_session_created_at` DATETIME NOT NULL,',
    '`auth_session_last_seen_at` DATETIME NOT NULL,',
    '`auth_session_expires_at` DATETIME NOT NULL,',
    '`auth_session_revoked_at` DATETIME NULL DEFAULT NULL,',
    'PRIMARY KEY (`auth_session_id`),',
    'UNIQUE KEY `uq_auth_session_token_hash` (`auth_session_token_hash`),',
    'KEY `idx_auth_session_user_active` (`auth_session_user_id`, `auth_session_revoked_at`, `auth_session_expires_at`),',
    'KEY `idx_auth_session_remember_selector` (`auth_session_remember_selector`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Authenticated PHP Session Registry for Session Management'''
  ),
  'SELECT 1'
);
PREPARE v132g_session_stmt FROM @sql;
EXECUTE v132g_session_stmt;
DEALLOCATE PREPARE v132g_session_stmt;
