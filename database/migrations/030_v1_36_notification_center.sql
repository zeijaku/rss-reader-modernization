-- V1.36-A Dashboard Notification Center foundation.
-- Apply after 029_v1_35_remember_2fa_trust.sql.
-- IMPORTANT: set @table_prefix to the same value as DB_TABLE_PREFIX before running.
SET @table_prefix = 'rss_';
SET @t_notification = CONCAT(@table_prefix, 'notification');
SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_notification, ' (',
  'notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  'notification_owner INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  'notification_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  'notification_source_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  'notification_source_id VARCHAR(64) NULL DEFAULT NULL,',
  'notification_source_key VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  'notification_title VARCHAR(256) NOT NULL,',
  'notification_body VARCHAR(1024) NOT NULL DEFAULT '''',',
  'notification_target_url VARCHAR(2048) NULL DEFAULT NULL,',
  'notification_due_at DATETIME NOT NULL,',
  'notification_read_at DATETIME NULL DEFAULT NULL,',
  'notification_hidden_at DATETIME NULL DEFAULT NULL,',
  'notification_created_at DATETIME NOT NULL,',
  'notification_updated_at DATETIME NOT NULL,',
  'PRIMARY KEY (notification_id),',
  'UNIQUE KEY uq_notification_owner_source (notification_owner, notification_source_type, notification_source_key, notification_type),',
  'KEY idx_notification_owner_visible_due (notification_owner, notification_hidden_at, notification_due_at, notification_id),',
  'KEY idx_notification_owner_unread_due (notification_owner, notification_read_at, notification_hidden_at, notification_due_at)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Dashboard notifications'''
);
PREPARE v136a_stmt FROM @sql;
EXECUTE v136a_stmt;
DEALLOCATE PREPARE v136a_stmt;
