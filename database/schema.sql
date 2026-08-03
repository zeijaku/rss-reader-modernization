-- RSS Reader Modernization 1.1.0 new-install schema
-- Sanitized schema only. Contains NO production rows or credentials.
-- Target: MySQL / MariaDB, InnoDB, utf8mb4.
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
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_dashboard_widget = CONCAT('`', @table_prefix, 'dashboard_widget`');

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
  'PRIMARY KEY (`stock_id`),',
  'KEY `idx_stock_owner_flag_id` (`stock_owner`, `stock_flag`, `stock_id`)',
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


SET @sql = CONCAT(
  'CREATE TABLE ', @t_calendar_event, ' (',
  '`calendar_event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`calendar_event_date` DATETIME NOT NULL,',
  '`calendar_event_updated_at` DATETIME NOT NULL,',
  '`calendar_event_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  '`calendar_event_owner` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`calendar_event_title` VARCHAR(256) NOT NULL,',
  '`calendar_event_start_date` DATE NOT NULL,',
  '`calendar_event_end_date` DATE NOT NULL,',
  '`calendar_event_note` TEXT NOT NULL,',
  'PRIMARY KEY (`calendar_event_id`),',
  'KEY `idx_calendar_event_owner_range` (`calendar_event_owner`, `calendar_event_flag`, `calendar_event_start_date`, `calendar_event_end_date`, `calendar_event_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar予定保管'''
);
PREPARE v11i_stmt FROM @sql; EXECUTE v11i_stmt; DEALLOCATE PREPARE v11i_stmt;


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

-- Foreign keys are intentionally NOT added in SB-13.
-- Legacy orphan data and the user deletion policy must be resolved first.
