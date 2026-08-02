-- V1.1-D read-only postflight (R2 correction)
--
-- phpMyAdminでは、左側からRSS Readerの実Databaseを選択してから実行してください。
-- DB_TABLE_PREFIXと同じ値を設定します。

SET @table_prefix = 'rss_';
SET @database_name = DATABASE();
SET @database_is_application = (
    @database_name IS NOT NULL
    AND @database_name NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
);

SET @content_name = CONCAT(@table_prefix, 'content');
SET @widget_name = CONCAT(@table_prefix, 'dashboard_widget');

-- 最初に、実行対象DatabaseとPrefixを表示します。
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
  AND TABLE_NAME = @widget_name
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @widget_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Table名はDatabase名を含めて組み立てます。
-- information_schema等が選択されている場合はTable参照を行わず、明示的な案内を返します。
SET @t_content = CONCAT(
    '`', REPLACE(@database_name, '`', '``'), '`.`',
    REPLACE(@content_name, '`', '``'), '`'
);
SET @t_widget = CONCAT(
    '`', REPLACE(@database_name, '`', '``'), '`.`',
    REPLACE(@widget_name, '`', '``'), '`'
);

SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT COUNT(*) AS missing_feed_widgets FROM ', @t_content, ' c ',
        'LEFT JOIN ', @t_widget, ' w ON w.widget_owner = c.content_owner ',
        'AND w.widget_type = ''feed'' ',
        'AND w.widget_reference_id = c.content_id ',
        'AND w.widget_flag = 0 ',
        'WHERE c.content_flag = 0 AND w.widget_id IS NULL'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してから再実行してください'' AS v11d_error'
);

PREPARE v11d_audit FROM @sql;
EXECUTE v11d_audit;
DEALLOCATE PREPARE v11d_audit;
