-- V1.33-D Calendar occurrence overrides and cancellations.
-- Additive MySQL / MariaDB migration. Existing Calendar rows are not modified.
-- Set @table_prefix to the same value as DB_TABLE_PREFIX before execution.
-- No foreign key is added so Legacy Calendar data and logical deletion remain compatible.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @exception_table = CONCAT(@table_prefix, 'calendar_event_exception');
SET @quoted_exception_table = CONCAT('`', REPLACE(@exception_table, '`', '``'), '`');
SET @prefix_ok = (@table_prefix REGEXP '^[A-Za-z_][A-Za-z0-9_]{0,39}$');

SET @exception_exists = IF(
  @prefix_ok = 1,
  (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @exception_table
  ),
  1
);

SET @sql = IF(
  @exception_exists = 0,
  CONCAT(
    'CREATE TABLE ', @quoted_exception_table, ' (',
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
    '`calendar_event_exception_url` VARCHAR(2048) NULL DEFAULT NULL,',
    '`calendar_event_exception_created_at` DATETIME NOT NULL,',
    '`calendar_event_exception_updated_at` DATETIME NOT NULL,',
    'PRIMARY KEY (`calendar_event_exception_id`),',
    'UNIQUE KEY `uq_cal_exception_owner_event_original` (`calendar_event_exception_owner`, `calendar_event_exception_event_id`, `calendar_event_exception_original_start_date`),',
    'KEY `idx_cal_exception_owner_original` (`calendar_event_exception_owner`, `calendar_event_exception_original_start_date`, `calendar_event_exception_event_id`),',
    'KEY `idx_cal_exception_owner_effective` (`calendar_event_exception_owner`, `calendar_event_exception_flag`, `calendar_event_exception_start_date`, `calendar_event_exception_end_date`, `calendar_event_exception_id`),',
    'KEY `idx_cal_exception_owner_event` (`calendar_event_exception_owner`, `calendar_event_exception_event_id`, `calendar_event_exception_flag`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Calendar occurrence override and cancellation'''
  ),
  'SELECT 1'
);
PREPARE v133d_exception_stmt FROM @sql;
EXECUTE v133d_exception_stmt;
DEALLOCATE PREPARE v133d_exception_stmt;
