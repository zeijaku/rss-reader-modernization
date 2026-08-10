-- RSS Reader Modernization V1.7-H / R2
-- Preflight for widget_height migration.
--
-- This revision does NOT query information_schema.
-- Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- Allowed prefix characters: ASCII letters, digits, underscore (max 40 chars).
-- This file does not modify application data or table definitions.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v17h_prefix_ok = (@table_prefix REGEXP '^[A-Za-z0-9_]{0,40}$');
SET @v17h_dashboard_widget_plain = IF(
  @v17h_prefix_ok = 1,
  CONCAT(@table_prefix, 'dashboard_widget'),
  '__INVALID_TABLE_PREFIX__'
);
SET @v17h_dashboard_widget = CONCAT('`', @v17h_dashboard_widget_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v17h_dashboard_widget_plain AS target_table,
  CASE
    WHEN @v17h_prefix_ok = 1 THEN 'OK'
    ELSE 'INVALID: use only A-Z, a-z, 0-9 and underscore; max 40 chars'
  END AS prefix_check;

-- Confirm that the target table exists and inspect its exact definition.
SET @v17h_sql = CONCAT('SHOW CREATE TABLE ', @v17h_dashboard_widget);
PREPARE v17h_pre_stmt FROM @v17h_sql;
EXECUTE v17h_pre_stmt;
DEALLOCATE PREPARE v17h_pre_stmt;

-- Inspect all columns. Before migration, widget_height should normally be absent.
-- If widget_height is already present because a manual ALTER was run, do NOT run
-- 008_v1_7_widget_height.sql again; run the postflight instead.
SET @v17h_sql = CONCAT('SHOW COLUMNS FROM ', @v17h_dashboard_widget);
PREPARE v17h_pre_stmt FROM @v17h_sql;
EXECUTE v17h_pre_stmt;
DEALLOCATE PREPARE v17h_pre_stmt;

-- Read-only row count, to confirm that the correct table is being inspected.
SET @v17h_sql = CONCAT(
  'SELECT COUNT(*) AS dashboard_widget_rows FROM ',
  @v17h_dashboard_widget
);
PREPARE v17h_pre_stmt FROM @v17h_sql;
EXECUTE v17h_pre_stmt;
DEALLOCATE PREPARE v17h_pre_stmt;
