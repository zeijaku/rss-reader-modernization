-- V1.1-D read-only preflight. Set the same prefix as DB_TABLE_PREFIX.
SET @table_prefix = 'ig_';
SET @content_name = CONCAT(@table_prefix, 'content');
SET @widget_name = CONCAT(@table_prefix, 'dashboard_widget');

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (@content_name, @widget_name)
ORDER BY TABLE_NAME;

SELECT COUNT(*) AS active_feed_count
FROM information_schema.TABLES t
JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
WHERE t.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_NAME = @content_name
  AND c.COLUMN_NAME = 'content_id';

SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = @widget_name
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
