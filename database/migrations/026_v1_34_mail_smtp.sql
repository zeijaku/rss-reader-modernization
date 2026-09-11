-- V1.34-B Mail SMTP foundation.
-- Additive MySQL / MariaDB migration. Existing IMAP settings/ciphertexts are not modified.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- SMTP is disabled by default for every existing account.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @mail_account_table = CONCAT(@table_prefix, 'mail_account');
SET @quoted_mail_account_table = CONCAT('`', REPLACE(@mail_account_table, '`', '``'), '`');

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_enabled'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `mail_account_secret`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_host'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER `mail_account_smtp_enabled`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_port'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_port` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `mail_account_smtp_host`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_encryption'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_encryption` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER `mail_account_smtp_port`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_use_imap_credentials'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_use_imap_credentials` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `mail_account_smtp_encryption`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_username'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_username` VARCHAR(320) NULL DEFAULT NULL AFTER `mail_account_smtp_use_imap_credentials`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_smtp_secret'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_smtp_secret` TEXT CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''AEAD encrypted SMTP credential envelope; never plaintext'' AFTER `mail_account_smtp_username`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_from_address'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_from_address` VARCHAR(320) NULL DEFAULT NULL AFTER `mail_account_smtp_secret`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @mail_account_table AND COLUMN_NAME = 'mail_account_from_name'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_mail_account_table,
    ' ADD COLUMN `mail_account_from_name` VARCHAR(128) NULL DEFAULT NULL AFTER `mail_account_from_address`'),
  'SELECT 1');
PREPARE v134b_stmt FROM @sql; EXECUTE v134b_stmt; DEALLOCATE PREPARE v134b_stmt;


SELECT
  @table_prefix AS configured_table_prefix,
  @mail_account_table AS target_table,
  CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
