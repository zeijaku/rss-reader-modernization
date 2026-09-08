-- V1.32-H Authentication Security Audit Log.
-- Additive MySQL / MariaDB migration. Existing application tables are not modified.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- Raw email, Password, TOTP/Recovery Code, TOTP Secret, encryption key, raw Session ID,
-- full User-Agent and raw IP address are deliberately not stored.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @audit_table = CONCAT(@table_prefix, 'auth_audit_log');
SET @quoted_audit_table = CONCAT('`', REPLACE(@audit_table, '`', '``'), '`');
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');

SET @audit_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @audit_table
  ),
  1
);

SET @sql = IF(
  @audit_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_audit_table, ' (',
    '`auth_audit_log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`auth_audit_log_user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''Authenticated owner when known'',',
    '`auth_audit_log_identity_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Existing keyed login identity for associating credential failures; never raw email'',',
    '`auth_audit_log_event` VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`auth_audit_log_result` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`auth_audit_log_method` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,',
    '`auth_audit_log_client_label` VARCHAR(120) NOT NULL COMMENT ''Privacy-bounded Browser/platform label; never full User-Agent'',',
    '`auth_audit_log_ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Keyed digest of REMOTE_ADDR; never raw IP'',',
    '`auth_audit_log_created_at` DATETIME NOT NULL,',
    'PRIMARY KEY (`auth_audit_log_id`),',
    'KEY `idx_auth_audit_user_created` (`auth_audit_log_user_id`, `auth_audit_log_id`),',
    'KEY `idx_auth_audit_identity_created` (`auth_audit_log_identity_hash`, `auth_audit_log_id`),',
    'KEY `idx_auth_audit_event_created` (`auth_audit_log_event`, `auth_audit_log_id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Bounded Authentication Security Activity log'''
  ),
  'SELECT 1'
);
PREPARE v132h_audit_stmt FROM @sql;
EXECUTE v132h_audit_stmt;
DEALLOCATE PREPARE v132h_audit_stmt;
