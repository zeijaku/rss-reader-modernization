-- V1.55 D1: add a format discriminator; never rewrite existing Memo bodies.
-- Set @table_prefix to DB_TABLE_PREFIX. Fresh installations use schema.sql only.
SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_memo = CONCAT('`', @table_prefix, 'memo`');
-- INFORMATION_SCHEMA exposes the columns of tables the current DB user can access.
SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = CONCAT(@table_prefix,'memo') AND COLUMN_NAME = 'memo_format');
SET @sql = IF(@present = 0, CONCAT('ALTER TABLE ', @t_memo,
 ' ADD COLUMN memo_format VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''text'''), 'SELECT 1');
PREPARE v155_stmt FROM @sql;
EXECUTE v155_stmt;
DEALLOCATE PREPARE v155_stmt;
