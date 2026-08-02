-- RSS Reader Modernization V1.1-C / R1
-- Adds NEW-state storage without changing existing tables or application rows.
-- Set @table_prefix to the prefix used by the existing Version 1.0.0 tables.
-- Legacy/current user DB example: ig_ / recommended new-install example: rss_
-- IMPORTANT: take a verified DB backup and run the V1.1-C preflight first.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @t_feed_item_state = CONCAT('`', @table_prefix, 'feed_item_state`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @t_feed_item_state, ' (',
  '`state_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`owner_id` INT UNSIGNED NOT NULL COMMENT ''user_info.user_id'',',
  '`content_id` INT UNSIGNED NOT NULL COMMENT ''content.content_id'',',
  '`item_identity` CHAR(71) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,',
  '`first_seen_at` DATETIME NOT NULL,',
  '`last_seen_at` DATETIME NOT NULL,',
  '`seen_at` DATETIME NULL DEFAULT NULL,',
  '`state_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:有効/1:無効'',',
  'PRIMARY KEY (`state_id`),',
  'UNIQUE KEY `uq_feed_item_state_owner_content_identity` (`owner_id`, `content_id`, `item_identity`),',
  'KEY `idx_feed_item_state_owner_content_seen` (`owner_id`, `content_id`, `seen_at`, `state_flag`),',
  'KEY `idx_feed_item_state_last_seen` (`last_seen_at`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Feed記事NEW状態'''
);
PREPARE v11c_stmt FROM @sql; EXECUTE v11c_stmt; DEALLOCATE PREPARE v11c_stmt;

-- CREATE TABLE IF NOT EXISTS makes a second run non-destructive.
-- Existing table definitions are not altered silently; run postflight/verify.
-- Foreign keys are intentionally not added. Existing orphan/delete policy remains unchanged.
