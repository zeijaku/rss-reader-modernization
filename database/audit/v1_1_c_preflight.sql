-- V1.1-C read-only preflight. Set the same prefix used by config/local.php.
SET @table_prefix = 'ig_';

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    CONCAT(@table_prefix, 'user_info'),
    CONCAT(@table_prefix, 'user_conf'),
    CONCAT(@table_prefix, 'content'),
    CONCAT(@table_prefix, 'content_stock'),
    CONCAT(@table_prefix, 'feed_item_state')
  )
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    CONCAT(@table_prefix, 'user_info'),
    CONCAT(@table_prefix, 'user_conf'),
    CONCAT(@table_prefix, 'content'),
    CONCAT(@table_prefix, 'content_stock'),
    CONCAT(@table_prefix, 'feed_item_state')
  )
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;
