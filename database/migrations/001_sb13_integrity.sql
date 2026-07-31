-- RSS Reader Secure Baseline SB-13 / R2
-- One-time migration for an UNMIGRATED SB-12 database.
-- Set @table_prefix to the prefix of the existing tables (Legacy default: ig_).
-- IMPORTANT: take a verified backup and run preflight.sql first.

SET @table_prefix = 'ig_';
SET @t_user_info = CONCAT('`', @table_prefix, 'user_info`');
SET @t_user_conf = CONCAT('`', @table_prefix, 'user_conf`');
SET @t_content = CONCAT('`', @table_prefix, 'content`');
SET @t_content_stock = CONCAT('`', @table_prefix, 'content_stock`');

SET @sql = CONCAT('ALTER TABLE ', @t_user_info, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_user_conf, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content_stock, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('ALTER TABLE ', @t_user_conf, ' MODIFY `user_id` INT UNSIGNED NOT NULL COMMENT ''user_infoのuser_id''');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content, ' MODIFY `content_owner` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''所有者ID[user_info:id]''');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content_stock, ' MODIFY `stock_owner` INT UNSIGNED NOT NULL COMMENT ''データオーナー''');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

SET @sql = CONCAT('ALTER TABLE ', @t_user_info, ' ADD INDEX `idx_user_identity_flag_id` (`user_email`(64), `user_flag`, `user_id`)');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_user_conf, ' ADD UNIQUE INDEX `uq_user_conf_user_id` (`user_id`)');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content, ' ADD INDEX `idx_content_owner_location_flag_id` (`content_owner`, `content_location`, `content_flag`, `content_id`)');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;
SET @sql = CONCAT('ALTER TABLE ', @t_content_stock, ' ADD INDEX `idx_stock_owner_flag_id` (`stock_owner`, `stock_flag`, `stock_id`)');
PREPARE sb13_stmt FROM @sql; EXECUTE sb13_stmt; DEALLOCATE PREPARE sb13_stmt;

-- No DELETE / UPDATE cleanup and no FOREIGN KEY are performed in SB-13.
