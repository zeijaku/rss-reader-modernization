#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCHEMA="$ROOT/database/schema.sql"

MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi

TEST_ROOT=$(mktemp -d /tmp/rss-current-schema.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"

cleanup() {
    find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true
    rmdir "$TEST_ROOT" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

"$INSTALL_DB" --basedir=/usr --datadir="$DATA_DIR"     --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1

run_bootstrap() {
    "$MARIADBD" --no-defaults --basedir=/usr --datadir="$DATA_DIR"         --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 2>&1
}

{
    printf '%s\n'         'CREATE DATABASE rss_current_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'         'USE rss_current_fresh;'
    sed "s/SET @table_prefix = 'rss_';/SET @table_prefix = 'qa_';/" "$SCHEMA"
    printf '%s\n'         "SET @ok_tables = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'qa\\_%') = 27);"         "SET @ok_mail = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qa_mail_account' AND COLUMN_NAME IN ('mail_account_auth_type','mail_account_smtp_enabled','mail_account_smtp_secret','mail_account_sent_save_mode')) = 4);"         "SET @ok_remember = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qa_remember_token' AND COLUMN_NAME = 'remember_token_second_factor_verified_at') = 1);"         "SET @ok_feature_tables = ((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('qa_link_item','qa_stock_tag','qa_stock_tag_map','qa_feed_keyword','qa_feed_metadata','qa_feed_health','qa_rss_rule','qa_rss_rule_condition','qa_notification')) = 9);"         'SET @all_ok = (@ok_tables AND @ok_mail AND @ok_remember AND @ok_feature_tables);'         "SET @assert_sql = IF(@all_ok, 'SELECT 1', 'SELECT * FROM current_fresh_schema_assertion_failed');"         'PREPARE current_schema_assert_stmt FROM @assert_sql;'         'EXECUTE current_schema_assert_stmt;'         'DEALLOCATE PREPARE current_schema_assert_stmt;'
} | run_bootstrap >/dev/null

echo 'PASS: current schema executes on MariaDB with one SQL file'
echo 'PASS: current schema creates exactly 27 prefixed tables'
echo 'PASS: Mail SMTP/Sent/OAuth2 and Remember 2FA trust columns are present'
echo 'PASS: current feature tables formerly requiring follow-up migrations are present'
echo 'RESULT: PASS 4 / FAIL 0 / SKIP 0'
