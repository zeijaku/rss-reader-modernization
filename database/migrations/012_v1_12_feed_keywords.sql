-- RSS Reader Modernization V1.12
-- RSS Highlight: user-owned keyword entities.
--
-- IMPORTANT:
-- 1. Set @table_prefix to the SAME value as DB_TABLE_PREFIX in config/local.php.
-- 2. Existing RSS, Stock, Stock Tag and Mail rows are not modified.
-- 3. Re-running is safe because CREATE TABLE IF NOT EXISTS is used.

SET NAMES utf8mb4;

-- ===== Environment-specific setting =====
SET @table_prefix = 'ig_';
-- ========================================

SET @v112_prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @v112_keyword_plain = IF(@v112_prefix_ok = 1, CONCAT(@table_prefix, 'feed_keyword'), '__INVALID_TABLE_PREFIX__');
SET @v112_keyword = CONCAT('`', @v112_keyword_plain, '`');

SELECT
  @table_prefix AS configured_table_prefix,
  @v112_keyword_plain AS keyword_table,
  CASE WHEN @v112_prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX' END AS prefix_check;

SET @v112_sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @v112_keyword, ' (',
  '`keyword_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`keyword_date` DATETIME NOT NULL,',
  '`keyword_updated_at` DATETIME NOT NULL,',
  '`keyword_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:inactive'',',
  '`keyword_owner` INT UNSIGNED NOT NULL,',
  '`keyword_value` VARCHAR(64) NOT NULL,',
  'PRIMARY KEY (`keyword_id`),',
  'UNIQUE KEY `uq_feed_keyword_owner_value` (`keyword_owner`, `keyword_value`),',
  'KEY `idx_feed_keyword_owner_flag_value` (`keyword_owner`, `keyword_flag`, `keyword_value`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''RSS Highlight keywords'''
);
PREPARE v112_keyword_stmt FROM @v112_sql;
EXECUTE v112_keyword_stmt;
DEALLOCATE PREPARE v112_keyword_stmt;
