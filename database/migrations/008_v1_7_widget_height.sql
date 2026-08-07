-- RSS Reader Modernization V1.7-H / R2
-- Dashboard Widget height extension for existing MySQL / MariaDB databases.
--
-- IMPORTANT:
-- 1. Run database/audit/v1_7_h_preflight.sql first.
-- 2. Run this file only when widget_height is NOT present in dashboard_widget.
-- 3. This revision does NOT query information_schema.
-- 4. Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- 5. ALTER TABLE causes an implicit commit in MySQL / MariaDB.

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

-- Add the V1.7-H column.
-- Existing rows receive DEFAULT 1, preserving the former one-row-height behavior.
SET @v17h_sql = CONCAT(
  'ALTER TABLE ', @v17h_dashboard_widget,
  ' ADD COLUMN `widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1',
  ' COMMENT ''1..2'' AFTER `widget_width`'
);
PREPARE v17h_migrate_stmt FROM @v17h_sql;
EXECUTE v17h_migrate_stmt;
DEALLOCATE PREPARE v17h_migrate_stmt;

-- Defensive normalization. Under the new NOT NULL/DEFAULT definition existing
-- rows should already be 1, but keep this for consistency with the application
-- validation rule (allowed values are 1 and 2 only).
SET @v17h_sql = CONCAT(
  'UPDATE ', @v17h_dashboard_widget,
  ' SET `widget_height` = 1',
  ' WHERE `widget_height` NOT IN (1, 2) OR `widget_height` IS NULL'
);
PREPARE v17h_migrate_stmt FROM @v17h_sql;
EXECUTE v17h_migrate_stmt;
DEALLOCATE PREPARE v17h_migrate_stmt;
