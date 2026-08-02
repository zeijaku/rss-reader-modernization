-- V1.1-D Dashboard Widget placement foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_content = CONCAT('`', @table_prefix, 'content`');
SET @t_dashboard_widget = CONCAT('`', @table_prefix, 'dashboard_widget`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_dashboard_widget, ' (',
  '`widget_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`widget_owner` INT UNSIGNED NOT NULL,',
  '`widget_location` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`widget_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`widget_reference_id` INT UNSIGNED NULL DEFAULT NULL,',
  '`widget_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  '`widget_width` TINYINT UNSIGNED NOT NULL DEFAULT 1,',
  '`widget_style` VARCHAR(16) NOT NULL DEFAULT ''success'',',
  '`widget_config` TEXT NULL,',
  '`widget_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`widget_created_at` DATETIME NOT NULL,',
  '`widget_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`widget_id`),',
  'UNIQUE KEY `uq_dashboard_widget_owner_type_reference` (`widget_owner`, `widget_type`, `widget_reference_id`),',
  'KEY `idx_dashboard_widget_owner_location_order` (`widget_owner`, `widget_location`, `widget_flag`, `widget_sort_order`, `widget_id`),',
  'KEY `idx_dashboard_widget_owner_type_flag` (`widget_owner`, `widget_type`, `widget_flag`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Dashboard Widget配置'''
);
PREPARE v11d_stmt FROM @sql; EXECUTE v11d_stmt; DEALLOCATE PREPARE v11d_stmt;

-- Existing active Feeds become one Feed Widget each. content_id is used as the
-- initial sort order, preserving the current stable ascending display order.
-- Re-running this migration must not reset a sort order changed in V1.1-E.
SET @sql = CONCAT(
  'INSERT INTO ', @t_dashboard_widget, ' (',
  'widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, ',
  'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at',
  ') SELECT ',
  'c.content_owner, c.content_location, ''feed'', c.content_id, c.content_id, ',
  '1, c.content_style, NULL, 0, c.content_date, c.content_date ',
  'FROM ', @t_content, ' c WHERE c.content_flag = 0 ',
  'ON DUPLICATE KEY UPDATE ',
  'widget_location = VALUES(widget_location), ',
  'widget_style = VALUES(widget_style), ',
  'widget_flag = 0, ',
  'widget_updated_at = VALUES(widget_updated_at)'
);
PREPARE v11d_stmt FROM @sql; EXECUTE v11d_stmt; DEALLOCATE PREPARE v11d_stmt;
