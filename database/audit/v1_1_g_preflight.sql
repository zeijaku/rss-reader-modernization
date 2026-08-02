-- V1.1-G preflight (read-only)
-- Select the application database before execution and set the prefix.
SET @table_prefix = 'ig_';
SELECT DATABASE() AS selected_database;
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (CONCAT(@table_prefix, 'dashboard_widget'), CONCAT(@table_prefix, 'memo'))
ORDER BY TABLE_NAME;
