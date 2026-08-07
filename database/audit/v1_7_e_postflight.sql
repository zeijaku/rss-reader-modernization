-- V1.7-E Remember Token postflight (read-only)
-- Select the application database and set DB_TABLE_PREFIX before execution.

SET @table_prefix = 'ig_';
SET @database_name = DATABASE();
SET @token_name = CONCAT(@table_prefix, 'remember_token');

SELECT
    @database_name AS selected_database,
    @table_prefix AS table_prefix,
    CASE
        WHEN @database_name IS NULL OR @database_name IN ('information_schema', 'mysql', 'performance_schema', 'sys')
            THEN 'ERROR: RSS Readerの実Databaseを選択してください'
        ELSE 'OK'
    END AS database_selection;

SELECT COLUMN_NAME, COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @token_name
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @database_name
  AND TABLE_NAME = @token_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SET @t_token = CONCAT('`', REPLACE(@database_name, '`', '``'), '`.`', REPLACE(@token_name, '`', '``'), '`');
SET @database_is_application = (
    @database_name IS NOT NULL
    AND @database_name NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
);
SET @sql = IF(
    @database_is_application = 1,
    CONCAT(
        'SELECT ',
        '(SELECT COUNT(*) FROM ', @t_token, ') AS token_count, ',
        '(SELECT COUNT(*) FROM ', @t_token, ' WHERE remember_token_expires_at <= NOW()) AS expired_token_count, ',
        '(SELECT COUNT(*) FROM ', @t_token, ' WHERE remember_token_selector NOT REGEXP ''^[0-9a-f]{24}$'') AS invalid_selector_count, ',
        '(SELECT COUNT(*) FROM ', @t_token, ' WHERE remember_token_validator_hash NOT REGEXP ''^[0-9a-f]{64}$'') AS invalid_validator_hash_count'
    ),
    'SELECT ''ERROR: RSS Readerの実Databaseを選択してください'' AS v17e_error'
);
PREPARE v17e_audit FROM @sql; EXECUTE v17e_audit; DEALLOCATE PREPARE v17e_audit;
