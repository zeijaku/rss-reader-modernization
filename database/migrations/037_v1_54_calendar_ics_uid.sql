-- V1.54 D1: persistent transfer UID. Run after 036, before deploying D1.
-- Set @table_prefix to DB_TABLE_PREFIX. Fresh installs use schema.sql only.
-- Existing shared-host marker pattern (033/034); no metadata-schema permissions.
SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_calendar_event = CONCAT('`', @table_prefix, 'calendar_event`');
SET @t_calendar_source = CONCAT('`', @table_prefix, 'calendar_source`');
SET @marker = '__migration_037_ics_uid__';
SET @done = 0;
SET @sql = CONCAT('SELECT COUNT(*) INTO @done FROM ', @t_calendar_source,
 ' WHERE calendar_source_owner=0 AND calendar_source_flag=255 AND calendar_source_name=', QUOTE(@marker));
PREPARE v154_stmt FROM @sql; EXECUTE v154_stmt; DEALLOCATE PREPARE v154_stmt;
SET @sql = IF(@done = 0, CONCAT('ALTER TABLE ', @t_calendar_event,
 ' ADD COLUMN calendar_event_ics_uid VARBINARY(255) NULL DEFAULT NULL,',
 ' ADD KEY idx_calendar_event_ics_uid (calendar_event_owner, calendar_event_source_id, calendar_event_flag, calendar_event_ics_uid)'), 'SELECT 1');
PREPARE v154_stmt FROM @sql; EXECUTE v154_stmt; DEALLOCATE PREPARE v154_stmt;
-- Verify column and index before recording completion.
SET @verified = 0;
SET @sql = CONCAT('SELECT (COUNT(calendar_event_ics_uid)>=0) INTO @verified FROM ', @t_calendar_event,
 ' FORCE INDEX (idx_calendar_event_ics_uid)');
PREPARE v154_stmt FROM @sql; EXECUTE v154_stmt; DEALLOCATE PREPARE v154_stmt;
SET @sql = IF(@verified=1, CONCAT('INSERT INTO ', @t_calendar_source,
 ' (calendar_source_date,calendar_source_updated_at,calendar_source_flag,calendar_source_owner,calendar_source_name,calendar_source_color,calendar_source_default,calendar_source_sort_order)',
 ' SELECT NOW(),NOW(),255,0,',QUOTE(@marker),',''blue'',0,0 WHERE NOT EXISTS (SELECT 1 FROM ',@t_calendar_source,
 ' WHERE calendar_source_owner=0 AND calendar_source_flag=255 AND calendar_source_name=',QUOTE(@marker),')'), 'SELECT 1');
PREPARE v154_stmt FROM @sql; EXECUTE v154_stmt; DEALLOCATE PREPARE v154_stmt;
-- Hash database UUIDs rather than exposing time/MAC-derived UUIDs. No update timestamp change.
-- Repeatable backfill: an existing UID is never overwritten.
SET @sql = CONCAT('UPDATE ', @t_calendar_event,
 ' SET calendar_event_ics_uid=CONCAT(''rr-'',SHA2(CONCAT(UUID(),RAND(),UUID()),256)) WHERE calendar_event_ics_uid IS NULL');
PREPARE v154_stmt FROM @sql; EXECUTE v154_stmt; DEALLOCATE PREPARE v154_stmt;
