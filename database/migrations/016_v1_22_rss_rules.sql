-- V1.22-C RSS Rules foundation
-- Existing DB migration for MySQL / MariaDB.
-- Set the prefix to the same value as DB_TABLE_PREFIX before execution.
-- Rule ownership lives only on rss_rule; child conditions do not duplicate user_id.

SET NAMES utf8mb4;
SET @table_prefix = 'ig_';
SET @rule_table = CONCAT(@table_prefix, 'rss_rule');
SET @condition_table = CONCAT(@table_prefix, 'rss_rule_condition');
SET @quoted_rule = CONCAT('`', REPLACE(@rule_table, '`', '``'), '`');
SET @quoted_condition = CONCAT('`', REPLACE(@condition_table, '`', '``'), '`');

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @quoted_rule, ' (',
  '`rule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`rule_owner` INT UNSIGNED NOT NULL,',
  '`rule_name` VARCHAR(100) NOT NULL,',
  '`rule_enabled` TINYINT(1) NOT NULL DEFAULT 1,',
  '`scope_content_id` INT UNSIGNED NULL,',
  '`match_mode` VARCHAR(8) NOT NULL DEFAULT ''all'',',
  '`rule_action` VARCHAR(32) NOT NULL,',
  '`rule_flag` TINYINT UNSIGNED NOT NULL DEFAULT 0,',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`rule_id`),',
  'KEY `idx_rss_rule_owner_active` (`rule_owner`,`rule_flag`,`rule_id`),',
  'KEY `idx_rss_rule_scope` (`scope_content_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''RSS Rules foundation'''
);
PREPARE v122c_rule_stmt FROM @sql; EXECUTE v122c_rule_stmt; DEALLOCATE PREPARE v122c_rule_stmt;

SET @sql = CONCAT(
  'CREATE TABLE IF NOT EXISTS ', @quoted_condition, ' (',
  '`condition_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,',
  '`condition_rule_id` INT UNSIGNED NOT NULL,',
  '`condition_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
  '`condition_field` VARCHAR(16) NOT NULL,',
  '`condition_operator` VARCHAR(24) NOT NULL,',
  '`condition_value` VARCHAR(255) NOT NULL,',
  '`created_at` DATETIME NOT NULL,',
  '`updated_at` DATETIME NOT NULL,',
  'PRIMARY KEY (`condition_id`),',
  'KEY `idx_rss_rule_condition_rule` (`condition_rule_id`,`condition_order`,`condition_id`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''RSS Rule conditions'''
);
PREPARE v122c_condition_stmt FROM @sql; EXECUTE v122c_condition_stmt; DEALLOCATE PREPARE v122c_condition_stmt;