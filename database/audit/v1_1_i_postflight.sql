-- V1.1-I read-only postflight
-- phpMyAdminでは、左側からRSS Readerの実Databaseを選択してから実行してください。
-- DB_TABLE_PREFIXと同じ値を設定します。

SET @table_prefix = 'ig_';
SET @database_name = DATABASE();
SET @database_is_application = (
    @database_name IS NOT NULL
    AND @database_name NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
);
SET @event_name = CONCAT(@table_prefix, 'calendar_event');
SET @task_name = CONCAT(@table_prefix, 'task');
SET @widget_name = CONCAT(@table_prefix, 'dashboard_widget');

SELECT
    @database_name AS selected_database,
    @table_prefix AS table_prefix,
    CASE
        WHEN @database_is_application = 1 THEN 'OK'
        ELSE 'ERROR: RSS Readerの実Databaseを選択してから再実行してください'
    END AS database_selection;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @event_name
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @event_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SET @t_event = CONCAT('`', REPLACE(@database_name, '`', '``'), '`.`', REPLACE(@event_name, '`', '``'), '`');
SET @t_task = CONCAT('`', REPLACE(@database_name, '`', '``'), '`.`', REPLACE(@task_name, '`', '``'), '`');
SET @t_widget = CONCAT('`', REPLACE(@database_name, '`', '``'), '`.`', REPLACE(@widget_name, '`', '``'), '`');

SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT ',
        '(SELECT COUNT(*) FROM ', @t_event, ' WHERE calendar_event_flag = 0) AS active_calendar_event_count, ',
        '(SELECT COUNT(*) FROM ', @t_task, ' WHERE task_flag = 0 AND task_due_date IS NOT NULL) AS task_with_due_date_count, ',
        '(SELECT COUNT(*) FROM ', @t_widget, ' WHERE widget_type = ''calendar'' AND widget_flag = 0) AS active_calendar_widget_count'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してから再実行してください'' AS v11i_error'
);
PREPARE v11i_audit FROM @sql; EXECUTE v11i_audit; DEALLOCATE PREPARE v11i_audit;

SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT calendar_event_id FROM ', @t_event, ' ',
        'WHERE calendar_event_flag = 0 AND calendar_event_end_date < calendar_event_start_date ',
        'ORDER BY calendar_event_id LIMIT 20'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してから再実行してください'' AS v11i_error'
);
PREPARE v11i_audit FROM @sql; EXECUTE v11i_audit; DEALLOCATE PREPARE v11i_audit;
