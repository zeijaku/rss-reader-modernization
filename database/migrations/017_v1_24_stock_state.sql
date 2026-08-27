-- V1.24-C Stock state foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- stock_flag remains Stock解除; the three new columns are independent states.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @table_name = CONCAT(@table_prefix, 'content_stock');
SET @quoted_table = CONCAT('`', REPLACE(@table_name, '`', '``'), '`');

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_processed'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `stock_processed` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `stock_title`'
  ),
  'SELECT 1'
);
PREPARE v124c_processed_stmt FROM @sql; EXECUTE v124c_processed_stmt; DEALLOCATE PREPARE v124c_processed_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_important'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `stock_important` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `stock_processed`'
  ),
  'SELECT 1'
);
PREPARE v124c_important_stmt FROM @sql; EXECUTE v124c_important_stmt; DEALLOCATE PREPARE v124c_important_stmt;

SET @column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_archived'
);
SET @sql = IF(
  @column_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD COLUMN `stock_archived` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `stock_important`'
  ),
  'SELECT 1'
);
PREPARE v124c_archived_stmt FROM @sql; EXECUTE v124c_archived_stmt; DEALLOCATE PREPARE v124c_archived_stmt;

SET @index_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND INDEX_NAME = 'idx_stock_owner_flag_archived_id'
);
SET @sql = IF(
  @index_exists = 0,
  CONCAT(
    'ALTER TABLE ', @quoted_table,
    ' ADD INDEX `idx_stock_owner_flag_archived_id` (`stock_owner`,`stock_flag`,`stock_archived`,`stock_id`)'
  ),
  'SELECT 1'
);
PREPARE v124c_index_stmt FROM @sql; EXECUTE v124c_index_stmt; DEALLOCATE PREPARE v124c_index_stmt;
