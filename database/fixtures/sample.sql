-- Sanitized SAMPLE DATA ONLY for a disposable database created from database/schema.sql.
-- This file contains no production data.
-- Set @table_prefix to the same prefix used when the schema was created.
-- This fake identity is not derived from your APP_HASH_KEY and is not intended for UI login.

SET @table_prefix = 'rss_';
SET @t_user_info = CONCAT('`', @table_prefix, 'user_info`');
SET @t_user_conf = CONCAT('`', @table_prefix, 'user_conf`');
SET @t_content = CONCAT('`', @table_prefix, 'content`');
SET @t_content_stock = CONCAT('`', @table_prefix, 'content_stock`');

SET @sql = CONCAT('INSERT INTO ', @t_user_info, ' (user_id, user_date, user_flag, user_email, user_password) VALUES (1001, ''2026-01-01 00:00:00'', 0, ''aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'', ''$2y$12$BJYT7YqfI0mGmDQWDex7seSdqSnALNiCFouaPdydY/Xbk32gpjIAW'')');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('INSERT INTO ', @t_user_conf, ' (conf_id, conf_date, user_id, conf_style, conf_style_nav) VALUES (1001, ''2026-01-01 00:00:00'', 1001, ''bootstrap'', ''dark'')');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('INSERT INTO ', @t_content, ' (content_id, content_date, content_flag, content_owner, content_location, content_style, content_value) VALUES (1001, ''2026-01-01 00:00:00'', 0, 1001, 0, ''success'', ''https://example.com/feed.xml''), (1002, ''2026-01-01 00:00:01'', 0, 1001, 1, ''info'', ''https://example.org/atom.xml'')');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('INSERT INTO ', @t_content_stock, ' (stock_id, stock_date, stock_flag, stock_owner, stock_data, stock_title) VALUES (1001, ''2026-01-01 00:00:02'', 0, 1001, ''https://example.com/article'', ''Example article'')');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
