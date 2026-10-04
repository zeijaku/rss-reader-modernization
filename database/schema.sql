-- RSS Reader Modernization current fresh-install schema.
-- Sanitized schema only. Contains NO production rows or credentials.
-- Target: MySQL / MariaDB, InnoDB, utf8mb4.
-- Fresh installs use this file only; historical migrations remain upgrade-only for existing databases.
-- The schema includes all current tables and columns introduced through migrations 001-034.
-- See docs/installation.md and docs/update-history.md.
--
-- IMPORTANT: Set @table_prefix to the SAME value as DB_TABLE_PREFIX in
-- config/local.php. Allowed characters: ASCII letters, digits, underscore;
-- maximum 40 characters. The recommended new-install prefix is rss_.

SET NAMES utf8mb4;
SET @table_prefix = 'rss_';

SET @t_user_info = CONCAT('`', @table_prefix, 'user_info`');
SET @t_user_conf = CONCAT('`', @table_prefix, 'user_conf`');
SET @t_content = CONCAT('`', @table_prefix, 'content`');
SET @t_content_stock = CONCAT('`', @table_prefix, 'content_stock`');
SET @t_feed_item_state = CONCAT('`', @table_prefix, 'feed_item_state`');
SET @t_memo = CONCAT('`', @table_prefix, 'memo`');
SET @t_task = CONCAT('`', @table_prefix, 'task`');
SET @t_calendar_source = CONCAT('`', @table_prefix, 'calendar_source`');
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_calendar_event_exception = CONCAT('`', @table_prefix, 'calendar_event_exception`');
SET @t_dashboard_widget = CONCAT('`', @table_prefix, 'dashboard_widget`');
SET @t_notification = CONCAT('`', @table_prefix, 'notification`');
SET @t_remember_token = CONCAT('`', @table_prefix, 'remember_token`');
SET @t_user_file = CONCAT('`', @table_prefix, 'user_file`');
SET @t_remote_connection = CONCAT('`', @table_prefix, 'remote_connection`');
SET @t_auth_totp = CONCAT('`', @table_prefix, 'auth_totp`');
SET @t_auth_recovery_code = CONCAT('`', @table_prefix, 'auth_recovery_code`');
SET @t_auth_session = CONCAT('`', @table_prefix, 'auth_session`');
SET @t_auth_audit_log = CONCAT('`', @table_prefix, 'auth_audit_log`');
SET @t_mail_account = CONCAT('`', @table_prefix, 'mail_account`');
SET @t_link_item = CONCAT('`', @table_prefix, 'link_item`');
SET @t_stock_tag = CONCAT('`', @table_prefix, 'stock_tag`');
SET @t_stock_tag_map = CONCAT('`', @table_prefix, 'stock_tag_map`');
SET @t_feed_keyword = CONCAT('`', @table_prefix, 'feed_keyword`');
SET @t_feed_metadata = CONCAT('`', @table_prefix, 'feed_metadata`');
SET @t_feed_health = CONCAT('`', @table_prefix, 'feed_health`');
SET @t_rss_rule = CONCAT('`', @table_prefix, 'rss_rule`');
SET @t_rss_rule_condition = CONCAT('`', @table_prefix, 'rss_rule_condition`');

SET @sql = CONCAT(
  'CREATE TABLE ', @t_user_info, ' (',
  '`user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`user_date` DATETIME NOT NULL,',
  '`user_flag` INT NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`user_email` TEXT NOT NULL COMMENT ''ログインIdentity'',',
  '`user_password` TEXT NOT NULL COMMENT ''password_hash() value'',',
  'PRIMARY KEY (`user_id`),',
  'KEY `idx_user_identity_flag_id` (`user_email`(64), `user_flag`, `user_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''ユーザーテーブル'''
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_user_conf, ' (',
  '`conf_id` INT NOT NULL AUTO_INCREMENT,',
  '`conf_date` DATETIME NOT NULL,',
  '`user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`conf_style` TEXT NOT NULL COMMENT ''全体デザイン'',',
  '`conf_style_nav` TEXT NOT NULL COMMENT ''Navbarデザイン'',',
  '`conf_style_navlink_icon1` VARCHAR(16) NOT NULL DEFAULT ''map-marker-alt'',',
  '`conf_style_navlink1` VARCHAR(512) NOT NULL DEFAULT ''https://map.google.com/'',',
  '`conf_style_navlink_view1` VARCHAR(8) NOT NULL DEFAULT ''Map'',',
  '`conf_style_navlink_icon2` VARCHAR(16) NOT NULL DEFAULT ''mail-bulk'',',
  '`conf_style_navlink2` VARCHAR(512) NOT NULL DEFAULT ''https://mail.google.com/'',',
  '`conf_style_navlink_view2` VARCHAR(8) NOT NULL DEFAULT ''Mail'',',
  '`conf_style_navlink_icon3` VARCHAR(16) NOT NULL DEFAULT ''search'',',
  '`conf_style_navlink3` VARCHAR(512) NOT NULL DEFAULT ''https://www.google.com/'',',
  '`conf_style_navlink_view3` VARCHAR(8) NOT NULL DEFAULT ''Search'',',
  '`conf_style_navlink_icon4` VARCHAR(16) NOT NULL DEFAULT ''images'',',
  '`conf_style_navlink4` VARCHAR(512) NOT NULL DEFAULT ''https://www.google.com/imghp'',',
  '`conf_style_navlink_view4` VARCHAR(8) NOT NULL DEFAULT ''Image'',',
  '`conf_style_tabname1` VARCHAR(16) NOT NULL DEFAULT ''Base'',',
  '`conf_style_tabname2` VARCHAR(16) NOT NULL DEFAULT ''Maint'',',
  '`conf_style_tabname3` VARCHAR(16) NOT NULL DEFAULT ''IT'',',
  '`conf_style_tabname4` VARCHAR(16) NOT NULL DEFAULT ''Observe'',',
  'PRIMARY KEY (`conf_id`),',
  'UNIQUE KEY `uq_user_conf_user_id` (`user_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''ユーザー固有の設定'''
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_remember_token, ' (',
  '`remember_token_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`remember_token_user_id` INT UNSIGNED NOT NULL,',
  '`remember_token_selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remember_token_validator_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remember_token_created_at` DATETIME NOT NULL,',
  '`remember_token_expires_at` DATETIME NOT NULL,',
  '`remember_token_last_used_at` DATETIME NULL DEFAULT NULL,',
  '`remember_token_second_factor_verified_at` DATETIME NULL DEFAULT NULL,',
  'PRIMARY KEY (`remember_token_id`),',
  'UNIQUE KEY `uq_remember_token_selector` (`remember_token_selector`),',
  'KEY `idx_remember_token_user_expiry` (`remember_token_user_id`, `remember_token_expires_at`),',
  'KEY `idx_remember_token_expiry` (`remember_token_expires_at`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Persistent login token'''
);
PREPARE v17e_stmt FROM @sql; EXECUTE v17e_stmt; DEALLOCATE PREPARE v17e_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_content, ' (',
  '`content_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`content_date` DATETIME NOT NULL,',
  '`content_flag` INT NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`content_owner` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''所有者ID[user_info.user_id]'',',
  '`content_location` INT NOT NULL DEFAULT 0 COMMENT ''表示位置[0..3]'',',
  '`content_style` VARCHAR(16) NOT NULL DEFAULT ''success'' COMMENT ''デザイン種類'',',
  '`content_value` VARCHAR(1024) NOT NULL COMMENT ''Feed URL'',',
  'PRIMARY KEY (`content_id`),',
  'KEY `idx_content_owner_location_flag_id` (`content_owner`, `content_location`, `content_flag`, `content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''コンテンツ保管'''
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_content_stock, ' (',
  '`stock_id` INT NOT NULL AUTO_INCREMENT,',
  '`stock_date` DATETIME NOT NULL,',
  '`stock_flag` INT NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`stock_owner` INT UNSIGNED NOT NULL COMMENT ''データオーナー'',',
  '`stock_data` VARCHAR(512) NOT NULL COMMENT ''ストックしたURL'',',
  '`stock_title` VARCHAR(128) NOT NULL DEFAULT ''Not Title...'' COMMENT ''ストック時の記事タイトル'',',
  '`stock_processed` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:未処理/1:処理済み'',',
  '`stock_important` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:通常/1:重要'',',
  '`stock_archived` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:通常/1:Archive'',',
  'PRIMARY KEY (`stock_id`),',
  'KEY `idx_stock_owner_flag_id` (`stock_owner`, `stock_flag`, `stock_id`),',
  'KEY `idx_stock_owner_flag_archived_id` (`stock_owner`, `stock_flag`, `stock_archived`, `stock_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''URLストック一覧'''
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_feed_item_state, ' (',
  '`state_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`owner_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`item_identity` CHAR(71) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`first_seen_at` DATETIME NOT NULL,',
  '`last_seen_at` DATETIME NOT NULL,',
  '`seen_at` DATETIME NULL DEFAULT NULL,',
  '`state_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  'PRIMARY KEY (`state_id`),',
  'UNIQUE KEY `uq_feed_item_state_owner_content_identity` (`owner_id`, `content_id`, `item_identity`),',
  'KEY `idx_feed_item_state_owner_content_seen` (`owner_id`, `content_id`, `seen_at`, `state_flag`),',
  'KEY `idx_feed_item_state_last_seen` (`last_seen_at`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Feed記事NEW状態'''
);
PREPARE v11c_stmt FROM @sql; EXECUTE v11c_stmt; DEALLOCATE PREPARE v11c_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_memo, ' (',
  '`memo_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`memo_date` DATETIME NOT NULL,',
  '`memo_updated_at` DATETIME NOT NULL,',
  '`memo_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`memo_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`memo_title` VARCHAR(128) NOT NULL,',
  '`memo_body` TEXT NOT NULL,',
  'PRIMARY KEY (`memo_id`),',
  'KEY `idx_memo_owner_flag_id` (`memo_owner`, `memo_flag`, `memo_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Memo保管'''
);
PREPARE v11g_stmt FROM @sql; EXECUTE v11g_stmt; DEALLOCATE PREPARE v11g_stmt;

-- V1.44-A Multiple Calendar / Source (033).
SET @sql = CONCAT(
  'CREATE TABLE ', @t_calendar_source, ' (',
  '`calendar_source_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_source_date` DATETIME NOT NULL,',
  '`calendar_source_updated_at` DATETIME NOT NULL,',
  '`calendar_source_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_source_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`calendar_source_name` VARCHAR(40) NOT NULL,',
  '`calendar_source_color` VARCHAR(16) NOT NULL DEFAULT ''blue'',',
  '`calendar_source_default` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_source_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  'PRIMARY KEY (`calendar_source_id`),',
  'KEY `idx_calendar_source_owner` (`calendar_source_owner`, `calendar_source_flag`, `calendar_source_default`, `calendar_source_sort_order`, `calendar_source_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar source'''
);
PREPARE v144a_schema_stmt FROM @sql; EXECUTE v144a_schema_stmt; DEALLOCATE PREPARE v144a_schema_stmt;

-- V1.25 Calendar recurrence (019) is integrated in the fresh-install calendar_event table.
SET @sql = CONCAT(
  'CREATE TABLE ', @t_calendar_event, ' (',
  '`calendar_event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_event_date` DATETIME NOT NULL,',
  '`calendar_event_updated_at` DATETIME NOT NULL,',
  '`calendar_event_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`calendar_event_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`calendar_event_source_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''calendar_source.calendar_source_id'',',
  '`calendar_event_title` VARCHAR(256) NOT NULL,',
  '`calendar_event_start_date` DATE NOT NULL,',
  '`calendar_event_end_date` DATE NOT NULL,',
  '`calendar_event_note` TEXT NOT NULL,',
  '`calendar_event_color` VARCHAR(8) NOT NULL DEFAULT ''blue'',',
  '`calendar_event_all_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,',
  '`calendar_event_start_time` TIME NULL DEFAULT NULL,',
  '`calendar_event_end_time` TIME NULL DEFAULT NULL,',
  '`calendar_event_url` VARCHAR(2048) NULL DEFAULT NULL,',
  '`calendar_event_location` VARCHAR(255) NULL DEFAULT NULL,',
  '`calendar_event_repeat_type` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''none'',',
  '`calendar_event_repeat_until` DATE NULL DEFAULT NULL,',
  '`calendar_event_deadline_highlight` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`calendar_event_reminder` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''none'',',
  'PRIMARY KEY (`calendar_event_id`),',
  'KEY `idx_calendar_event_owner_range` (`calendar_event_owner`, `calendar_event_flag`, `calendar_event_start_date`, `calendar_event_end_date`, `calendar_event_id`),',
  'KEY `idx_calendar_event_source` (`calendar_event_owner`, `calendar_event_source_id`, `calendar_event_flag`, `calendar_event_start_date`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar予定保管'''
);
PREPARE v11i_stmt FROM @sql; EXECUTE v11i_stmt; DEALLOCATE PREPARE v11i_stmt;

-- V1.33-D Calendar occurrence overrides and cancellations (025).
SET @sql = CONCAT(
  'CREATE TABLE ', @t_calendar_event_exception, ' (',
  '`calendar_event_exception_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_event_exception_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`calendar_event_exception_event_id` BIGINT UNSIGNED NOT NULL COMMENT ''calendar_event.calendar_event_id'',',
  '`calendar_event_exception_original_start_date` DATE NOT NULL,',
  '`calendar_event_exception_kind` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`calendar_event_exception_revision` BIGINT UNSIGNED NOT NULL DEFAULT 1,',
  '`calendar_event_exception_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:復元済み'',',
  '`calendar_event_exception_start_date` DATE NULL DEFAULT NULL,',
  '`calendar_event_exception_end_date` DATE NULL DEFAULT NULL,',
  '`calendar_event_exception_title` VARCHAR(256) NULL DEFAULT NULL,',
  '`calendar_event_exception_note` TEXT NULL,',
  '`calendar_event_exception_color` VARCHAR(8) NULL DEFAULT NULL,',
  '`calendar_event_exception_all_day` TINYINT UNSIGNED NULL DEFAULT NULL,',
  '`calendar_event_exception_start_time` TIME NULL DEFAULT NULL,',
  '`calendar_event_exception_end_time` TIME NULL DEFAULT NULL,',
  '`calendar_event_exception_deadline_highlight` TINYINT UNSIGNED NULL DEFAULT NULL,',
  '`calendar_event_exception_url` VARCHAR(2048) NULL DEFAULT NULL,',
  '`calendar_event_exception_location` VARCHAR(255) NULL DEFAULT NULL,',
  '`calendar_event_exception_created_at` DATETIME NOT NULL,',
  '`calendar_event_exception_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`calendar_event_exception_id`),',
  'UNIQUE KEY `uq_cal_exception_owner_event_original` (`calendar_event_exception_owner`, `calendar_event_exception_event_id`, `calendar_event_exception_original_start_date`),',
  'KEY `idx_cal_exception_owner_original` (`calendar_event_exception_owner`, `calendar_event_exception_original_start_date`, `calendar_event_exception_event_id`),',
  'KEY `idx_cal_exception_owner_effective` (`calendar_event_exception_owner`, `calendar_event_exception_flag`, `calendar_event_exception_start_date`, `calendar_event_exception_end_date`, `calendar_event_exception_id`),',
  'KEY `idx_cal_exception_owner_event` (`calendar_event_exception_owner`, `calendar_event_exception_event_id`, `calendar_event_exception_flag`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar occurrence override and cancellation'''
);
PREPARE v133d_stmt FROM @sql; EXECUTE v133d_stmt; DEALLOCATE PREPARE v133d_stmt;

-- V1.36-A Dashboard Notification Center.
SET @sql = CONCAT(
  'CREATE TABLE ', @t_notification, ' (',
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
);
PREPARE v136a_schema_stmt FROM @sql; EXECUTE v136a_schema_stmt; DEALLOCATE PREPARE v136a_schema_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_task, ' (',
  '`task_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`task_date` DATETIME NOT NULL,',
  '`task_updated_at` DATETIME NOT NULL,',
  '`task_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`task_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`task_widget_id` BIGINT UNSIGNED NOT NULL COMMENT ''dashboard_widget.widget_id'',',
  '`task_title` VARCHAR(256) NOT NULL,',
  '`task_due_date` DATE NULL DEFAULT NULL,',
  '`task_priority` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''normal'',',
  '`task_completed` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`task_completed_at` DATETIME NULL DEFAULT NULL,',
  '`task_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  'PRIMARY KEY (`task_id`),',
  'KEY `idx_task_owner_widget_flag_order` (`task_owner`, `task_widget_id`, `task_flag`, `task_sort_order`, `task_id`),',
  'KEY `idx_task_owner_due` (`task_owner`, `task_flag`, `task_completed`, `task_due_date`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Task保管'''
);
PREPARE v11h_stmt FROM @sql; EXECUTE v11h_stmt; DEALLOCATE PREPARE v11h_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_dashboard_widget, ' (',
  '`widget_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`widget_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`widget_location` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''表示位置[0..3]'',',
  '`widget_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`widget_reference_id` INT UNSIGNED NULL DEFAULT NULL,',
  '`widget_sort_order` INT UNSIGNED NOT NULL DEFAULT 0,',
  '`widget_width` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''1..4'',',
  '`widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''1..2'',',
  '`widget_style` VARCHAR(16) NOT NULL DEFAULT ''success'',',
  '`widget_config` TEXT NULL,',
  '`widget_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`widget_created_at` DATETIME NOT NULL,',
  '`widget_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`widget_id`),',
  'UNIQUE KEY `uq_dashboard_widget_owner_type_reference` (`widget_owner`, `widget_type`, `widget_reference_id`),',
  'KEY `idx_dashboard_widget_owner_location_order` (`widget_owner`, `widget_location`, `widget_flag`, `widget_sort_order`, `widget_id`),',
  'KEY `idx_dashboard_widget_owner_type_flag` (`widget_owner`, `widget_type`, `widget_flag`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Dashboard Widget配置'''
);
PREPARE v11d_stmt FROM @sql; EXECUTE v11d_stmt; DEALLOCATE PREPARE v11d_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_user_file, ' (',
  '`file_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`file_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`file_original_name` VARCHAR(255) NOT NULL,',
  '`file_stored_name` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`file_mime_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`file_extension` VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`file_size` BIGINT UNSIGNED NOT NULL,',
  '`file_created_at` DATETIME NOT NULL,',
  '`file_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:deleted'',',
  'PRIMARY KEY (`file_id`),',
  'UNIQUE KEY `uq_user_file_stored_name` (`file_stored_name`),',
  'KEY `idx_user_file_owner_flag_id` (`file_owner`, `file_flag`, `file_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User-owned file metadata'''
);
PREPARE v127d_user_file_stmt FROM @sql; EXECUTE v127d_user_file_stmt; DEALLOCATE PREPARE v127d_user_file_stmt;


SET @sql = CONCAT(
  'CREATE TABLE ', @t_remote_connection, ' (',
  '`remote_connection_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`remote_connection_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`remote_connection_name` VARCHAR(128) NOT NULL,',
  '`remote_connection_protocol` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remote_connection_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remote_connection_port` SMALLINT UNSIGNED NOT NULL,',
  '`remote_connection_username` VARCHAR(320) NOT NULL,',
  '`remote_connection_auth_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`remote_connection_secret` MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted credential envelope'',',
  '`remote_connection_base_path` VARCHAR(2048) NOT NULL DEFAULT ''/'',',
  '`remote_connection_allow_private` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`remote_connection_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,',
  '`remote_connection_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`remote_connection_created_at` DATETIME NOT NULL,',
  '`remote_connection_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`remote_connection_id`),',
  'KEY `idx_remote_connection_owner_flag_id` (`remote_connection_owner`, `remote_connection_flag`, `remote_connection_id`),',
  'KEY `idx_remote_connection_owner_enabled_flag` (`remote_connection_owner`, `remote_connection_enabled`, `remote_connection_flag`, `remote_connection_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User-owned remote file connections'''
);
PREPARE v129b_remote_connection_stmt FROM @sql; EXECUTE v129b_remote_connection_stmt; DEALLOCATE PREPARE v129b_remote_connection_stmt;


-- V1.32 Account Security: TOTP second factor and one-time Recovery Code hashes.
SET @sql = CONCAT(
  'CREATE TABLE ', @t_auth_totp, ' (',
  '`auth_totp_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`auth_totp_secret` MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted TOTP secret envelope'',',
  '`auth_totp_created_at` DATETIME NOT NULL,',
  '`auth_totp_updated_at` DATETIME NOT NULL,',
  '`auth_totp_enabled_at` DATETIME NULL DEFAULT NULL,',
  '`auth_totp_last_used_step` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''Replay prevention for accepted TOTP time-step'',',
  'PRIMARY KEY (`auth_totp_user_id`),',
  'KEY `idx_auth_totp_enabled_user` (`auth_totp_enabled_at`, `auth_totp_user_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''User TOTP second-factor settings'''
);
PREPARE v132_totp_stmt FROM @sql; EXECUTE v132_totp_stmt; DEALLOCATE PREPARE v132_totp_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_auth_recovery_code, ' (',
  '`auth_recovery_code_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`auth_recovery_code_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`auth_recovery_code_hash` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''One-way recovery code hash only'',',
  '`auth_recovery_code_created_at` DATETIME NOT NULL,',
  '`auth_recovery_code_used_at` DATETIME NULL DEFAULT NULL,',
  'PRIMARY KEY (`auth_recovery_code_id`),',
  'UNIQUE KEY `uq_auth_recovery_user_hash` (`auth_recovery_code_user_id`, `auth_recovery_code_hash`),',
  'KEY `idx_auth_recovery_user_used_id` (`auth_recovery_code_user_id`, `auth_recovery_code_used_at`, `auth_recovery_code_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''One-time authentication recovery code hashes'''
);
PREPARE v132_recovery_stmt FROM @sql; EXECUTE v132_recovery_stmt; DEALLOCATE PREPARE v132_recovery_stmt;

-- V1.32 Session Management: PHP session contents remain filesystem-backed.
SET @sql = CONCAT(
  'CREATE TABLE ', @t_auth_session, ' (',
  '`auth_session_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`auth_session_user_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`auth_session_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''SHA-256 of user-bound random Session Registry token'',',
  '`auth_session_remember_selector` CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Remember selector only; validator/cookie value is never stored here'',',
  '`auth_session_client_label` VARCHAR(120) NOT NULL COMMENT ''Privacy-bounded Browser/platform label; no IP address'',',
  '`auth_session_created_at` DATETIME NOT NULL,',
  '`auth_session_last_seen_at` DATETIME NOT NULL,',
  '`auth_session_expires_at` DATETIME NOT NULL,',
  '`auth_session_revoked_at` DATETIME NULL DEFAULT NULL,',
  'PRIMARY KEY (`auth_session_id`),',
  'UNIQUE KEY `uq_auth_session_token_hash` (`auth_session_token_hash`),',
  'KEY `idx_auth_session_user_active` (`auth_session_user_id`, `auth_session_revoked_at`, `auth_session_expires_at`),',
  'KEY `idx_auth_session_remember_selector` (`auth_session_remember_selector`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Authenticated PHP Session Registry for Session Management'''
);
PREPARE v132_session_stmt FROM @sql; EXECUTE v132_session_stmt; DEALLOCATE PREPARE v132_session_stmt;

-- V1.32 Authentication Security Activity. No raw Password/TOTP/Recovery Secret,
-- full User-Agent, raw IP address, or raw PHP Session identifier is stored.
SET @sql = CONCAT(
  'CREATE TABLE ', @t_auth_audit_log, ' (',
  '`auth_audit_log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`auth_audit_log_user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''Authenticated owner when known'',',
  '`auth_audit_log_identity_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Existing keyed login identity for associating credential failures; never raw email'',',
  '`auth_audit_log_event` VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`auth_audit_log_result` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`auth_audit_log_method` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,',
  '`auth_audit_log_client_label` VARCHAR(120) NOT NULL COMMENT ''Privacy-bounded Browser/platform label; never full User-Agent'',',
  '`auth_audit_log_ip_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''Keyed digest of REMOTE_ADDR; never raw IP'',',
  '`auth_audit_log_created_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`auth_audit_log_id`),',
  'KEY `idx_auth_audit_user_created` (`auth_audit_log_user_id`, `auth_audit_log_id`),',
  'KEY `idx_auth_audit_identity_created` (`auth_audit_log_identity_hash`, `auth_audit_log_id`),',
  'KEY `idx_auth_audit_event_created` (`auth_audit_log_event`, `auth_audit_log_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Bounded Authentication Security Activity log'''
);
PREPARE v132_audit_stmt FROM @sql; EXECUTE v132_audit_stmt; DEALLOCATE PREPARE v132_audit_stmt;

-- Current Fresh Install: Mail account final schema (migrations 009, 026-028 integrated).
SET @sql = CONCAT(
  'CREATE TABLE ', @t_mail_account, ' (',
  '`mail_account_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`mail_account_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`mail_account_display_name` VARCHAR(128) NOT NULL,',
  '`mail_account_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`mail_account_port` SMALLINT UNSIGNED NOT NULL,',
  '`mail_account_encryption` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`mail_account_username` VARCHAR(320) NOT NULL,',
  '`mail_account_auth_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''password'',',
  '`mail_account_secret` TEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''AEAD encrypted credential envelope'',',
  '`mail_account_smtp_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,',
  '`mail_account_smtp_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,',
  '`mail_account_smtp_port` SMALLINT UNSIGNED NULL DEFAULT NULL,',
  '`mail_account_smtp_encryption` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,',
  '`mail_account_smtp_use_imap_credentials` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,',
  '`mail_account_smtp_username` VARCHAR(320) NULL DEFAULT NULL,',
  '`mail_account_smtp_secret` TEXT CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT ''AEAD encrypted SMTP credential envelope; never plaintext'',',
  '`mail_account_from_address` VARCHAR(320) NULL DEFAULT NULL,',
  '`mail_account_from_name` VARCHAR(128) NULL DEFAULT NULL,',
  '`mail_account_sent_save_mode` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''auto'',',
  '`mail_account_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''0:disabled/1:enabled'',',
  '`mail_account_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:active/1:deleted'',',
  '`mail_account_created_at` DATETIME NOT NULL,',
  '`mail_account_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`mail_account_id`),',
  'KEY `idx_mail_account_owner_flag_id` (`mail_account_owner`, `mail_account_flag`, `mail_account_id`),',
  'KEY `idx_mail_account_owner_enabled_flag` (`mail_account_owner`, `mail_account_enabled`, `mail_account_flag`, `mail_account_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Mail account settings'''
);
PREPARE current_mail_stmt FROM @sql; EXECUTE current_mail_stmt; DEALLOCATE PREPARE current_mail_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_link_item, ' (',
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
PREPARE current_link_stmt FROM @sql; EXECUTE current_link_stmt; DEALLOCATE PREPARE current_link_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_stock_tag, ' (',
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
PREPARE current_stock_tag_stmt FROM @sql; EXECUTE current_stock_tag_stmt; DEALLOCATE PREPARE current_stock_tag_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_stock_tag_map, ' (',
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
PREPARE current_stock_tag_map_stmt FROM @sql; EXECUTE current_stock_tag_map_stmt; DEALLOCATE PREPARE current_stock_tag_map_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_feed_keyword, ' (',
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
PREPARE current_feed_keyword_stmt FROM @sql; EXECUTE current_feed_keyword_stmt; DEALLOCATE PREPARE current_feed_keyword_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_feed_metadata, ' (',
  '`metadata_content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`feed_title` VARCHAR(255) NOT NULL DEFAULT '''',',
  '`site_url` VARCHAR(1024) NOT NULL DEFAULT '''',',
  '`category_path` VARCHAR(512) NOT NULL DEFAULT '''',',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`metadata_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''OPML / Feed metadata'''
);
PREPARE current_feed_metadata_stmt FROM @sql; EXECUTE current_feed_metadata_stmt; DEALLOCATE PREPARE current_feed_metadata_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_feed_health, ' (',
  '`health_content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`last_checked_at` DATETIME NULL,',
  '`last_successful_fetch_at` DATETIME NULL,',
  '`latest_article_at` DATETIME NULL,',
  '`last_result` VARCHAR(16) NOT NULL DEFAULT ''unknown'',',
  '`http_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
  '`error_code` VARCHAR(64) NOT NULL DEFAULT '''',',
  '`error_reason` VARCHAR(255) NOT NULL DEFAULT '''',',
  '`consecutive_failure_count` INT UNSIGNED NOT NULL DEFAULT 0,',
  '`redirected` TINYINT(1) NOT NULL DEFAULT 0,',
  '`effective_url` VARCHAR(1024) NOT NULL DEFAULT '''',',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`health_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Feed Health state'''
);
PREPARE current_feed_health_stmt FROM @sql; EXECUTE current_feed_health_stmt; DEALLOCATE PREPARE current_feed_health_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_rss_rule, ' (',
  '`rule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`rule_owner` INT UNSIGNED NOT NULL,',
  '`rule_name` VARCHAR(100) NOT NULL,',
  '`rule_enabled` TINYINT(1) NOT NULL DEFAULT 1,',
  '`scope_content_id` INT UNSIGNED NULL,',
  '`match_mode` VARCHAR(8) NOT NULL DEFAULT ''all'',',
  '`rule_action` VARCHAR(32) NOT NULL,',
  '`rule_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`rule_id`),',
  'KEY `idx_rss_rule_owner_active` (`rule_owner`,`rule_flag`,`rule_id`),',
  'KEY `idx_rss_rule_scope` (`scope_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''RSS Rules foundation'''
);
PREPARE current_rss_rule_stmt FROM @sql; EXECUTE current_rss_rule_stmt; DEALLOCATE PREPARE current_rss_rule_stmt;

SET @sql = CONCAT(
  'CREATE TABLE ', @t_rss_rule_condition, ' (',
  '`condition_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`condition_rule_id` INT UNSIGNED NOT NULL,',
  '`condition_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
  '`condition_field` VARCHAR(16) NOT NULL,',
  '`condition_operator` VARCHAR(24) NOT NULL,',
  '`condition_value` VARCHAR(255) NOT NULL,',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`condition_id`),',
  'KEY `idx_rss_rule_condition_rule` (`condition_rule_id`,`condition_order`,`condition_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''RSS Rule conditions'''
);
PREPARE current_rss_rule_condition_stmt FROM @sql; EXECUTE current_rss_rule_condition_stmt; DEALLOCATE PREPARE current_rss_rule_condition_stmt;

-- Foreign keys are intentionally NOT added in SB-13.
-- Legacy orphan data and the user deletion policy must be resolved first.
