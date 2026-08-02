-- V1.1-G read-only postflight
--
-- phpMyAdminでは、左側からRSS Readerの実Databaseを選択してから実行してください。
-- DB_TABLE_PREFIXと同じ値を設定します。

SET @table_prefix = 'ig_';
SET @database_name = DATABASE();
SET @database_is_application = (
    @database_name IS NOT NULL
    AND @database_name NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
);
SET @memo_name = CONCAT(@table_prefix, 'memo');
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
  AND TABLE_NAME = @memo_name
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @memo_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SET @t_memo = CONCAT(
    '`', REPLACE(@database_name, '`', '``'), '`.`',
    REPLACE(@memo_name, '`', '``'), '`'
);
SET @t_widget = CONCAT(
    '`', REPLACE(@database_name, '`', '``'), '`.`',
    REPLACE(@widget_name, '`', '``'), '`'
);

SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT ',
        '(SELECT COUNT(*) FROM ', @t_memo, ' WHERE memo_flag = 0) AS active_memo_count, ',
        '(SELECT COUNT(*) FROM ', @t_widget, ' WHERE widget_type = ''memo'' AND widget_flag = 0) AS active_memo_widget_count'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してから再実行してください'' AS v11g_error'
);
PREPARE v11g_audit FROM @sql; EXECUTE v11g_audit; DEALLOCATE PREPARE v11g_audit;

SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT m.memo_id FROM ', @t_memo, ' m LEFT JOIN ', @t_widget, ' w ',
        'ON w.widget_owner = m.memo_owner AND w.widget_type = ''memo'' ',
        'AND w.widget_reference_id = m.memo_id AND w.widget_flag = 0 ',
        'WHERE m.memo_flag = 0 AND w.widget_id IS NULL ORDER BY m.memo_id LIMIT 20'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してから再実行してください'' AS v11g_error'
);
PREPARE v11g_audit FROM @sql; EXECUTE v11g_audit; DEALLOCATE PREPARE v11g_audit;
