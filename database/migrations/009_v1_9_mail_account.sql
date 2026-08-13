-- RSS Reader Modernization V1.9-B / R4
-- Mail Account foundation for existing MySQL / MariaDB databases.
--
-- IMPORTANT:
-- 1. Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- 2. Existing DB upgrades require a verified backup before running this migration.
-- 3. Run this file only when mail_account does NOT already exist.
-- 4. Fresh installs apply this after database/schema.sql; see docs/installation.md.
-- 5. This migration creates a metadata/credential table only. Mail messages are not stored.
-- 6. The credential column stores authenticated ciphertext, never plaintext.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v19b_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v19b_mail_account_plain = IF(
  @v19b_prefix_ok = 1,
  CONCAT(@table_prefix, 'mail_account'),
  '__INVALID_TABLE_PREFIX__'
);
SET @v19b_mail_account = CONCAT('`', @v19b_mail_account_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v19b_mail_account_plain AS target_table,
  CASE
    WHEN @v19b_prefix_ok = 1 THEN 'OK'
    ELSE 'INVALID: 1-40 chars, start with A-Z/a-z/underscore, then A-Z/a-z/0-9/underscore'
  END AS prefix_check;

SET @v19b_sql = CONCAT(
  'CREATE TABLE ', @v19b_mail_account, ' (',
  '`mail_account_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`mail_account_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`mail_account_display_name` VARCHAR(128) NOT NULL,',
  '`mail_account_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`mail_account_port` SMALLINT UNSIGNED NOT NULL,',
  '`mail_account_encryption` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`mail_account_username` VARCHAR(320) NOT NULL,',
  '`mail_account_secret` TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted credential envelope'',',
  '`mail_account_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''0:disabled/1:enabled'',',
  '`mail_account_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:deleted'',',
  '`mail_account_created_at` DATETIME NOT NULL,',
  '`mail_account_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`mail_account_id`),',
  'KEY `idx_mail_account_owner_flag_id` (`mail_account_owner`, `mail_account_flag`, `mail_account_id`),',
  'KEY `idx_mail_account_owner_enabled_flag` (`mail_account_owner`, `mail_account_enabled`, `mail_account_flag`, `mail_account_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Mail account settings'''
);
PREPARE v19b_migrate_stmt FROM @v19b_sql;
EXECUTE v19b_migrate_stmt;
DEALLOCATE PREPARE v19b_migrate_stmt;
