-- RSS Reader Modernization V1.11
-- Stock Phase 2: user-owned tags and Stock <-> Tag relation.
--
-- IMPORTANT:
-- 1. Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- 2. Existing content_stock rows are not modified.
-- 3. Re-running is safe because CREATE TABLE IF NOT EXISTS is used.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v111_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v111_tag_plain = IF(@v111_prefix_ok = 1, CONCAT(@table_prefix, 'stock_tag'), '__INVALID_TABLE_PREFIX__');
SET @v111_map_plain = IF(@v111_prefix_ok = 1, CONCAT(@table_prefix, 'stock_tag_map'), '__INVALID_TABLE_PREFIX__');
SET @v111_tag = CONCAT('`', @v111_tag_plain, '`');
SET @v111_map = CONCAT('`', @v111_map_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v111_tag_plain AS tag_table,
  @v111_map_plain AS map_table,
  CASE WHEN @v111_prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX' END AS prefix_check;

SET @v111_sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @v111_tag, ' (',
  '`tag_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`tag_date` DATETIME NOT NULL,',
  '`tag_updated_at` DATETIME NOT NULL,',
  '`tag_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`tag_owner` INT UNSIGNED NOT NULL,',
  '`tag_name` VARCHAR(40) NOT NULL,',
  'PRIMARY KEY (`tag_id`),',
  'UNIQUE KEY `uq_stock_tag_owner_name` (`tag_owner`, `tag_name`),',
  'KEY `idx_stock_tag_owner_flag_name` (`tag_owner`, `tag_flag`, `tag_name`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Stock tags'''
);
PREPARE v111_tag_stmt FROM @v111_sql;
EXECUTE v111_tag_stmt;
DEALLOCATE PREPARE v111_tag_stmt;

SET @v111_sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @v111_map, ' (',
  '`map_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`map_date` DATETIME NOT NULL,',
  '`map_owner` INT UNSIGNED NOT NULL,',
  '`map_stock_id` INT NOT NULL,',
  '`map_tag_id` BIGINT UNSIGNED NOT NULL,',
  'PRIMARY KEY (`map_id`),',
  'UNIQUE KEY `uq_stock_tag_map_owner_stock_tag` (`map_owner`, `map_stock_id`, `map_tag_id`),',
  'KEY `idx_stock_tag_map_owner_tag_stock` (`map_owner`, `map_tag_id`, `map_stock_id`),',
  'KEY `idx_stock_tag_map_owner_stock` (`map_owner`, `map_stock_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Stock tag relations'''
);
PREPARE v111_map_stmt FROM @v111_sql;
EXECUTE v111_map_stmt;
DEALLOCATE PREPARE v111_map_stmt;
