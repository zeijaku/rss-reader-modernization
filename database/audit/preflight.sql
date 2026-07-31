-- SB-13 R2 PRE-MIGRATION AUDIT (read-only)
-- Set @table_prefix to the prefix used by the target database.
-- For a new R2 database the recommended value is rss_.
-- No statement in this file modifies application data or schema.

SET @table_prefix = 'rss_';
SET @n_user_info = CONCAT(@table_prefix, 'user_info');
SET @n_user_conf = CONCAT(@table_prefix, 'user_conf');
SET @n_content = CONCAT(@table_prefix, 'content');
SET @n_content_stock = CONCAT(@table_prefix, 'content_stock');
SET @t_user_info = CONCAT('`', @n_user_info, '`');
SET @t_user_conf = CONCAT('`', @n_user_conf, '`');
SET @t_content = CONCAT('`', @n_content, '`');
SET @t_content_stock = CONCAT('`', @n_content_stock, '`');

SET @sql = CONCAT(
  'SELECT ''', @n_user_info, ''' AS table_name, COUNT(*) AS row_count FROM ', @t_user_info,
  ' UNION ALL SELECT ''', @n_user_conf, ''', COUNT(*) FROM ', @t_user_conf,
  ' UNION ALL SELECT ''', @n_content, ''', COUNT(*) FROM ', @t_content,
  ' UNION ALL SELECT ''', @n_content_stock, ''', COUNT(*) FROM ', @t_content_stock
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT user_flag, COUNT(*) AS row_count FROM ', @t_user_info, ' GROUP BY user_flag ORDER BY user_flag');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT content_flag, COUNT(*) AS row_count FROM ', @t_content, ' GROUP BY content_flag ORDER BY content_flag');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT stock_flag, COUNT(*) AS row_count FROM ', @t_content_stock, ' GROUP BY stock_flag ORDER BY stock_flag');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT content_location, COUNT(*) AS row_count FROM ', @t_content, ' GROUP BY content_location ORDER BY content_location');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS duplicate_identity_groups FROM (SELECT user_email FROM ', @t_user_info, ' GROUP BY user_email HAVING COUNT(*) > 1) AS d');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS duplicate_user_conf_user_ids FROM (SELECT user_id FROM ', @t_user_conf, ' GROUP BY user_id HAVING COUNT(*) > 1) AS d');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS orphan_content_rows FROM ', @t_content, ' c LEFT JOIN ', @t_user_info, ' u ON u.user_id = c.content_owner WHERE u.user_id IS NULL');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS orphan_stock_rows FROM ', @t_content_stock, ' s LEFT JOIN ', @t_user_info, ' u ON u.user_id = s.stock_owner WHERE u.user_id IS NULL');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS orphan_conf_rows FROM ', @t_user_conf, ' c LEFT JOIN ', @t_user_info, ' u ON u.user_id = c.user_id WHERE u.user_id IS NULL');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT COUNT(*) AS users_missing_conf FROM ', @t_user_info, ' u LEFT JOIN ', @t_user_conf, ' c ON c.user_id = u.user_id WHERE c.user_id IS NULL');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT(
  'SELECT (SELECT COUNT(*) FROM ', @t_user_conf, ' WHERE user_id < 0)',
  ' + (SELECT COUNT(*) FROM ', @t_content, ' WHERE content_owner < 0)',
  ' + (SELECT COUNT(*) FROM ', @t_content_stock, ' WHERE stock_owner < 0)',
  ' AS negative_relationship_ids'
);
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SELECT TABLE_NAME, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@n_user_info, @n_user_conf, @n_content, @n_content_stock)
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@n_user_info, @n_user_conf, @n_content, @n_content_stock)
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@n_user_info, @n_user_conf, @n_content, @n_content_stock)
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SET @sql = CONCAT('SELECT content_owner, COUNT(*) AS row_count FROM ', @t_content, ' GROUP BY content_owner ORDER BY content_owner');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('SELECT stock_owner, COUNT(*) AS row_count FROM ', @t_content_stock, ' GROUP BY stock_owner ORDER BY stock_owner');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
