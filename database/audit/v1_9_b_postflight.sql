-- RSS Reader Modernization V1.9-B / R4
-- Postflight for mail_account creation. Read-only.
-- Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v19b_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v19b_mail_account_plain = IF(@v19b_prefix_ok = 1, CONCAT(@table_prefix, 'mail_account'), '__INVALID_TABLE_PREFIX__');
SET @v19b_mail_account = CONCAT('`', @v19b_mail_account_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v19b_mail_account_plain AS target_table,
  CASE WHEN @v19b_prefix_ok = 1 THEN 'OK' ELSE 'INVALID' END AS prefix_check;

SET @v19b_sql = CONCAT('SHOW CREATE TABLE ', @v19b_mail_account);
PREPARE v19b_post_stmt FROM @v19b_sql;
EXECUTE v19b_post_stmt;
DEALLOCATE PREPARE v19b_post_stmt;

SET @v19b_sql = CONCAT('SHOW COLUMNS FROM ', @v19b_mail_account);
PREPARE v19b_post_stmt FROM @v19b_sql;
EXECUTE v19b_post_stmt;
DEALLOCATE PREPARE v19b_post_stmt;

SET @v19b_sql = CONCAT(
  'SELECT COUNT(*) AS mail_account_rows, ',
  'COALESCE(SUM(CASE WHEN `mail_account_flag` NOT IN (0,1) THEN 1 ELSE 0 END), 0) AS invalid_flag_rows, ',
  'COALESCE(SUM(CASE WHEN `mail_account_enabled` NOT IN (0,1) THEN 1 ELSE 0 END), 0) AS invalid_enabled_rows ',
  'FROM ', @v19b_mail_account
);
PREPARE v19b_post_stmt FROM @v19b_sql;
EXECUTE v19b_post_stmt;
DEALLOCATE PREPARE v19b_post_stmt;
