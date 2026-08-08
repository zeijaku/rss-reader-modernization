-- RSS Reader Modernization V1.9-B / R4
-- Preflight for mail_account creation. Read-only.
-- Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v19b_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v19b_mail_account_plain = IF(@v19b_prefix_ok = 1, CONCAT(@table_prefix, 'mail_account'), '__INVALID_TABLE_PREFIX__');
SET @v19b_user_info_plain = IF(@v19b_prefix_ok = 1, CONCAT(@table_prefix, 'user_info'), '__INVALID_TABLE_PREFIX__');
SET @v19b_dashboard_widget_plain = IF(@v19b_prefix_ok = 1, CONCAT(@table_prefix, 'dashboard_widget'), '__INVALID_TABLE_PREFIX__');
SET @v19b_user_info_like = REPLACE(@v19b_user_info_plain, '_', '\\_');
SET @v19b_dashboard_widget_like = REPLACE(@v19b_dashboard_widget_plain, '_', '\\_');
SET @v19b_mail_account_like = REPLACE(@v19b_mail_account_plain, '_', '\\_');

SELECT
  @table_prefix AS configured_table_prefix,
  @v19b_mail_account_plain AS target_table,
  CASE
    WHEN @v19b_prefix_ok = 1 THEN 'OK'
    ELSE 'INVALID: 1-40 chars, start with A-Z/a-z/underscore, then A-Z/a-z/0-9/underscore'
  END AS prefix_check;

-- The first two queries should return one row. mail_account should normally return no row before migration.
SET @v19b_sql = CONCAT('SHOW TABLES LIKE ''', @v19b_user_info_like, '''');
PREPARE v19b_pre_stmt FROM @v19b_sql; EXECUTE v19b_pre_stmt; DEALLOCATE PREPARE v19b_pre_stmt;

SET @v19b_sql = CONCAT('SHOW TABLES LIKE ''', @v19b_dashboard_widget_like, '''');
PREPARE v19b_pre_stmt FROM @v19b_sql; EXECUTE v19b_pre_stmt; DEALLOCATE PREPARE v19b_pre_stmt;

SET @v19b_sql = CONCAT('SHOW TABLES LIKE ''', @v19b_mail_account_like, '''');
PREPARE v19b_pre_stmt FROM @v19b_sql; EXECUTE v19b_pre_stmt; DEALLOCATE PREPARE v19b_pre_stmt;
