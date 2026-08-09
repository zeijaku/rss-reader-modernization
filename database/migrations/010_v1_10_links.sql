-- RSS Reader Modernization V1.10
-- Links Widget item storage for existing MySQL / MariaDB databases.
--
-- IMPORTANT:
-- 1. Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- 2. This migration only creates the Links item table; existing Dashboard data is not changed.
-- 3. Re-running is safe because CREATE TABLE IF NOT EXISTS is used.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v110_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v110_link_item_plain = IF(
  @v110_prefix_ok = 1,
  CONCAT(@table_prefix, 'link_item'),
  '__INVALID_TABLE_PREFIX__'
);
SET @v110_link_item = CONCAT('`', @v110_link_item_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v110_link_item_plain AS target_table,
  CASE
    WHEN @v110_prefix_ok = 1 THEN 'OK'
    ELSE 'INVALID: 1-40 chars, start with A-Z/a-z/underscore, then A-Z/a-z/0-9/underscore'
  END AS prefix_check;

SET @v110_sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @v110_link_item, ' (',
  '`link_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`link_date` DATETIME NOT NULL,',
  '`link_updated_at` DATETIME NOT NULL,',
  '`link_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`link_owner` INT UNSIGNED NOT NULL,',
  '`link_widget_id` BIGINT UNSIGNED NOT NULL,',
  '`link_title` VARCHAR(128) NOT NULL,',
  '`link_url` VARCHAR(2048) NOT NULL,',
  '`link_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  'PRIMARY KEY (`link_id`),',
  'KEY `idx_link_item_owner_widget_order` (`link_owner`, `link_widget_id`, `link_flag`, `link_sort_order`, `link_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Links Widget items'''
);
PREPARE v110_links_stmt FROM @v110_sql;
EXECUTE v110_links_stmt;
DEALLOCATE PREPARE v110_links_stmt;
