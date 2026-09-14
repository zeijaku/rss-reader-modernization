-- V1.35 bounded trusted-browser marker for Remember Me + 2FA.
-- Existing tokens remain untrusted and will require 2FA once before receiving this marker.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @remember_token_table = CONCAT(@table_prefix, 'remember_token');
SET @quoted_remember_token_table = CONCAT('`', REPLACE(@remember_token_table, '`', '``'), '`');

SET @column_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @remember_token_table
     AND COLUMN_NAME = 'remember_token_second_factor_verified_at'),
  1
);
SET @sql = IF(@column_exists = 0,
  CONCAT('ALTER TABLE ', @quoted_remember_token_table,
    ' ADD COLUMN `remember_token_second_factor_verified_at` DATETIME NULL DEFAULT NULL AFTER `remember_token_last_used_at`'),
  'SELECT 1');
PREPARE v135_remember_stmt FROM @sql; EXECUTE v135_remember_stmt; DEALLOCATE PREPARE v135_remember_stmt;

SELECT
  @table_prefix AS configured_table_prefix,
  @remember_token_table AS target_table,
  CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
