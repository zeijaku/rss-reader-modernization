-- V1.36-A Dashboard Notification Center foundation.
-- Apply after 029_v1_35_remember_2fa_trust.sql.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- Rollback (only after rolling application code back): DROP TABLE <DB_TABLE_PREFIX>notification;

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');
SET @notification_table = CONCAT(@table_prefix, 'notification');
SET @quoted_notification_table = CONCAT('`', REPLACE(@notification_table, '`', '``'), '`');
SET @table_exists = IF(
  @prefix_ok = 1,
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @notification_table),
  1
);
SET @sql = IF(@table_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_notification_table, ' (',
    '`notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
    '`notification_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
    '`notification_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`notification_source_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`notification_source_id` VARCHAR(64) NULL DEFAULT NULL,',
    '`notification_source_key` VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
    '`notification_title` VARCHAR(256) NOT NULL,',
    '`notification_body` VARCHAR(1024) NOT NULL DEFAULT '''',',
    '`notification_target_url` VARCHAR(2048) NULL DEFAULT NULL,',
    '`notification_due_at` DATETIME NOT NULL,',
    '`notification_read_at` DATETIME NULL DEFAULT NULL,',
    '`notification_hidden_at` DATETIME NULL DEFAULT NULL,',
    '`notification_created_at` DATETIME NOT NULL,',
    '`notification_updated_at` DATETIME NOT NULL,',
    'PRIMARY KEY (`notification_id`),',
    'UNIQUE KEY `uq_notification_owner_source` (`notification_owner`, `notification_source_type`, `notification_source_key`, `notification_type`),',
    'KEY `idx_notification_owner_visible_due` (`notification_owner`, `notification_hidden_at`, `notification_due_at`, `notification_id`),',
    'KEY `idx_notification_owner_unread_due` (`notification_owner`, `notification_read_at`, `notification_hidden_at`, `notification_due_at`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Dashboard notifications'''
  ),
  'SELECT 1'
);
PREPARE v136a_stmt FROM @sql; EXECUTE v136a_stmt; DEALLOCATE PREPARE v136a_stmt;

SELECT
  @table_prefix AS configured_table_prefix,
  @notification_table AS target_table,
  CASE WHEN @prefix_ok = 1 THEN 'OK' ELSE 'INVALID TABLE PREFIX - no changes applied' END AS prefix_check;
