-- V1.44-B Calendar event location + Google Maps search link support.
-- Existing DB migration for MySQL / MariaDB.
-- Shared-host compatible: does not read information_schema.
-- Run after 033_v1_44_calendar_source.sql and set the prefix to DB_TABLE_PREFIX.
--
-- Hidden owner=0 / flag=255 Calendar-source rows are used as DDL completion
-- markers so the migration can be re-run without metadata-schema access.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_source = CONCAT('`', @table_prefix, 'calendar_source`');
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_calendar_event_exception = CONCAT('`', @table_prefix, 'calendar_event_exception`');
SET @v144b_event_marker_name = '__migration_034_event_location__';
SET @v144b_exception_marker_name = '__migration_034_exception_location__';

-- Parent event location.
SET @sql = CONCAT(
  'SELECT COUNT(*) INTO @v144b_event_location_done FROM ', @t_calendar_source,
  ' WHERE `calendar_source_owner` = 0',
  ' AND `calendar_source_flag` = 255',
  ' AND `calendar_source_name` = ', QUOTE(@v144b_event_marker_name)
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_event_location_done = 0,
  CONCAT(
    'ALTER TABLE ', @t_calendar_event,
    ' ADD COLUMN `calendar_event_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_url`'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @v144b_event_location_verified = 0;
SET @sql = CONCAT(
  'SELECT (COUNT(`calendar_event_location`) >= 0) INTO @v144b_event_location_verified FROM ',
  @t_calendar_event
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_event_location_verified = 1,
  CONCAT(
    'INSERT INTO ', @t_calendar_source, ' ',
    '(`calendar_source_date`, `calendar_source_updated_at`, `calendar_source_flag`, `calendar_source_owner`, ',
    '`calendar_source_name`, `calendar_source_color`, `calendar_source_default`, `calendar_source_sort_order`) ',
    'SELECT NOW(), NOW(), 255, 0, ', QUOTE(@v144b_event_marker_name), ', ''blue'', 0, 0 ',
    'WHERE NOT EXISTS (SELECT 1 FROM ', @t_calendar_source,
    ' WHERE `calendar_source_owner` = 0 AND `calendar_source_flag` = 255',
    ' AND `calendar_source_name` = ', QUOTE(@v144b_event_marker_name), ')'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

-- Recurrence occurrence-override location.
SET @sql = CONCAT(
  'SELECT COUNT(*) INTO @v144b_exception_location_done FROM ', @t_calendar_source,
  ' WHERE `calendar_source_owner` = 0',
  ' AND `calendar_source_flag` = 255',
  ' AND `calendar_source_name` = ', QUOTE(@v144b_exception_marker_name)
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_exception_location_done = 0,
  CONCAT(
    'ALTER TABLE ', @t_calendar_event_exception,
    ' ADD COLUMN `calendar_event_exception_location` VARCHAR(255) NULL DEFAULT NULL AFTER `calendar_event_exception_url`'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @v144b_exception_location_verified = 0;
SET @sql = CONCAT(
  'SELECT (COUNT(`calendar_event_exception_location`) >= 0) INTO @v144b_exception_location_verified FROM ',
  @t_calendar_event_exception
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;

SET @sql = IF(
  @v144b_exception_location_verified = 1,
  CONCAT(
    'INSERT INTO ', @t_calendar_source, ' ',
    '(`calendar_source_date`, `calendar_source_updated_at`, `calendar_source_flag`, `calendar_source_owner`, ',
    '`calendar_source_name`, `calendar_source_color`, `calendar_source_default`, `calendar_source_sort_order`) ',
    'SELECT NOW(), NOW(), 255, 0, ', QUOTE(@v144b_exception_marker_name), ', ''blue'', 0, 0 ',
    'WHERE NOT EXISTS (SELECT 1 FROM ', @t_calendar_source,
    ' WHERE `calendar_source_owner` = 0 AND `calendar_source_flag` = 255',
    ' AND `calendar_source_name` = ', QUOTE(@v144b_exception_marker_name), ')'
  ),
  'SELECT 1'
);
PREPARE v144b_stmt FROM @sql;
EXECUTE v144b_stmt;
DEALLOCATE PREPARE v144b_stmt;
