-- V1.1-C read-only postflight. Set the same prefix used by config/local.php.
SET @table_prefix = 'ig_';
SET @t_feed_item_state_plain = CONCAT(@table_prefix, 'feed_item_state');

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = @t_feed_item_state_plain;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = @t_feed_item_state_plain
ORDER BY ORDINAL_POSITION;

SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = @t_feed_item_state_plain
GROUP BY INDEX_NAME, NON_UNIQUE
ORDER BY INDEX_NAME;
