-- V1.32-B Authentication / TOTP foundation.
-- Existing DB migration for MySQL / MariaDB.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- TOTP secrets are stored only as authenticated ciphertext. The encryption key is never stored in the database.
-- Recovery Code rows are reserved here for V1.32-D; plaintext recovery codes must never be stored.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @totp_table = CONCAT(@table_prefix, 'auth_totp');
SET @recovery_table = CONCAT(@table_prefix, 'auth_recovery_code');
SET @quoted_totp_table = CONCAT('`', REPLACE(@totp_table, '`', '``'), '`');
SET @quoted_recovery_table = CONCAT('`', REPLACE(@recovery_table, '`', '``'), '`');
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');

SET @totp_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @totp_table
  ),
  1
);

SET @sql = IF(
  @totp_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_totp_table, ' (',
    '`auth_totp_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`auth_totp_secret` MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted TOTP secret envelope'',',
    '`auth_totp_created_at` DATETIME NOT NULL,',
    '`auth_totp_updated_at` DATETIME NOT NULL,',
    '`auth_totp_enabled_at` DATETIME NULL DEFAULT NULL,',
    '`auth_totp_last_used_step` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''Replay prevention for accepted TOTP time-step'',',
    'PRIMARY KEY (`auth_totp_user_id`),',
    'KEY `idx_auth_totp_enabled_user` (`auth_totp_enabled_at`, `auth_totp_user_id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User TOTP second-factor settings'''
  ),
  'SELECT 1'
);
PREPARE v132b_totp_stmt FROM @sql;
EXECUTE v132b_totp_stmt;
DEALLOCATE PREPARE v132b_totp_stmt;

SET @recovery_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @recovery_table
  ),
  1
);

SET @sql = IF(
  @recovery_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_recovery_table, ' (',
    '`auth_recovery_code_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`auth_recovery_code_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`auth_recovery_code_hash` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''One-way recovery code hash only'',',
    '`auth_recovery_code_created_at` DATETIME NOT NULL,',
    '`auth_recovery_code_used_at` DATETIME NULL DEFAULT NULL,',
    'PRIMARY KEY (`auth_recovery_code_id`),',
    'UNIQUE KEY `uq_auth_recovery_user_hash` (`auth_recovery_code_user_id`, `auth_recovery_code_hash`),',
    'KEY `idx_auth_recovery_user_used_id` (`auth_recovery_code_user_id`, `auth_recovery_code_used_at`, `auth_recovery_code_id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''One-time authentication recovery code hashes'''
  ),
  'SELECT 1'
);
PREPARE v132b_recovery_stmt FROM @sql;
EXECUTE v132b_recovery_stmt;
DEALLOCATE PREPARE v132b_recovery_stmt;
