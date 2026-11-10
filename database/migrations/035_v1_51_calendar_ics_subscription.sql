-- Dev1: owner-scoped ICS subscription metadata and private raw ICS cache.
-- MySQL/MariaDB upgrade, after 034_v1_44_calendar_location.sql.
-- Change @table_prefix to the same DB_TABLE_PREFIX as config/local.php.
-- Additive: existing calendar_source and calendar_event remain untouched.
SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_ics = CONCAT('`', @table_prefix, 'calendar_ics_subscription`');
SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_calendar_ics, ' (',
  '`ics_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`ics_owner` INT UNSIGNED NOT NULL,',
  '`ics_name` VARCHAR(40) NOT NULL,',
  '`ics_color` VARCHAR(16) NOT NULL DEFAULT ''blue'',',
  '`ics_url` VARCHAR(2048) NOT NULL,',
  '`ics_raw` MEDIUMTEXT NULL,',
  '`ics_status` VARCHAR(12) NOT NULL DEFAULT ''pending'',',
  '`ics_error_code` VARCHAR(40) NOT NULL DEFAULT '''',',
  '`ics_last_checked_at` DATETIME NULL DEFAULT NULL,',
  '`ics_last_success_at` DATETIME NULL DEFAULT NULL,',
  '`ics_created_at` DATETIME NOT NULL,',
  '`ics_updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`ics_id`),',
  'KEY `idx_ics_owner` (`ics_owner`,`ics_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ',
  'COMMENT=''Private ICS subscriptions (read-only)'''
);
PREPARE v151_ics_stmt FROM @sql;
EXECUTE v151_ics_stmt;
DEALLOCATE PREPARE v151_ics_stmt;
