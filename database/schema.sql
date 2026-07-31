-- RSS Reader Secure Baseline SB-15 / R3
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

-- Foreign keys are intentionally NOT added in SB-13.
-- Legacy orphan data and the user deletion policy must be resolved first.
