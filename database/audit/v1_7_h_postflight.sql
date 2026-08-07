-- RSS Reader Modernization V1.7-H / R2
-- Postflight for widget_height migration.
--
-- This revision does NOT query information_schema.
-- Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- This file is read-only.

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

-- Confirm the exact widget_height definition without information_schema.
SET @v17h_sql = CONCAT(
  'SHOW COLUMNS FROM ', @v17h_dashboard_widget,
  ' LIKE ''widget_height'''
);
PREPARE v17h_post_stmt FROM @v17h_sql;
EXECUTE v17h_post_stmt;
DEALLOCATE PREPARE v17h_post_stmt;

-- Expected values are 1 (standard) and, after users change settings, 2 (two rows).
SET @v17h_sql = CONCAT(
  'SELECT `widget_height`, COUNT(*) AS widget_count FROM ',
  @v17h_dashboard_widget,
  ' GROUP BY `widget_height` ORDER BY `widget_height`'
);
PREPARE v17h_post_stmt FROM @v17h_sql;
EXECUTE v17h_post_stmt;
DEALLOCATE PREPARE v17h_post_stmt;

-- This must return 0.
SET @v17h_sql = CONCAT(
  'SELECT COUNT(*) AS invalid_widget_height_rows FROM ',
  @v17h_dashboard_widget,
  ' WHERE `widget_height` NOT IN (1, 2) OR `widget_height` IS NULL'
);
PREPARE v17h_post_stmt FROM @v17h_sql;
EXECUTE v17h_post_stmt;
DEALLOCATE PREPARE v17h_post_stmt;

-- Final table definition for manual comparison / audit trail.
SET @v17h_sql = CONCAT('SHOW CREATE TABLE ', @v17h_dashboard_widget);
PREPARE v17h_post_stmt FROM @v17h_sql;
EXECUTE v17h_post_stmt;
DEALLOCATE PREPARE v17h_post_stmt;
