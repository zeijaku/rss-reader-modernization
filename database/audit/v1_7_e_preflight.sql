-- V1.7-E Remember Token preflight (read-only)
-- Select the application database and set DB_TABLE_PREFIX before execution.

SET @table_prefix = 'ig_';
SET @database_name = DATABASE();
SET @user_name = CONCAT(@table_prefix, 'user_info');
SET @token_name = CONCAT(@table_prefix, 'remember_token');

SELECT
    @database_name AS selected_database,
    @table_prefix AS table_prefix,
    CASE
        WHEN @database_name IS NULL OR @database_name IN ('information_schema', 'mysql', 'performance_schema', 'sys')
            THEN 'ERROR: RSS Readerの実Databaseを選択してください'
        ELSE 'OK'
    END AS database_selection;

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME IN (@user_name, @token_name)
ORDER BY TABLE_NAME;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @user_name
  AND COLUMN_NAME IN ('user_id', 'user_flag')
ORDER BY ORDINAL_POSITION;
