#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIGRATION="$ROOT/database/migrations/037_v1_54_calendar_ics_uid.sql"
MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    if [ "${REQUIRE_CALENDAR_TRANSFER_MARIADB:-0}" = 1 ]; then
        echo 'FAIL: MariaDB server tools required'; exit 1
    fi
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi
TEST_ROOT=$(mktemp -d /tmp/rss-transfer-mariadb.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"
cleanup() { find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true; rmdir "$TEST_ROOT" 2>/dev/null || true; }
trap cleanup EXIT HUP INT TERM
"$INSTALL_DB" --basedir=/usr --datadir="$DATA_DIR" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1
run_bootstrap() {
    "$MARIADBD" --no-defaults --basedir=/usr --datadir="$DATA_DIR" --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 2>&1
}
{
    printf '%s\n' 'CREATE DATABASE rss_transfer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' 'USE rss_transfer;'
    sed "s/SET @table_prefix = 'rss_';/SET @table_prefix = 'qa_';/" "$ROOT/database/schema.sql"
    printf '%s\n' \
        "SET @fresh_ok = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_calendar_event' AND COLUMN_NAME='calendar_event_ics_uid' AND DATA_TYPE='varbinary' AND CHARACTER_MAXIMUM_LENGTH=255)=1 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_calendar_event' AND INDEX_NAME='idx_calendar_event_ics_uid')=4);" \
        'ALTER TABLE qa_calendar_event DROP INDEX idx_calendar_event_ics_uid, DROP COLUMN calendar_event_ics_uid;' \
        "INSERT INTO qa_calendar_source (calendar_source_date,calendar_source_updated_at,calendar_source_owner,calendar_source_name,calendar_source_color,calendar_source_default,calendar_source_sort_order) VALUES ('2026-01-01','2026-01-01',42,'work','blue',1,0);" \
        "INSERT INTO qa_calendar_event (calendar_event_date,calendar_event_updated_at,calendar_event_flag,calendar_event_owner,calendar_event_source_id,calendar_event_title,calendar_event_start_date,calendar_event_end_date,calendar_event_note) VALUES ('2026-01-01','2026-01-02',0,42,1,'日本語','2026-01-01','2026-01-01','memo'),('2026-01-01','2026-01-02',0,99,NULL,'other','2026-01-01','2026-01-01',''),('2026-01-01','2026-01-02',1,42,1,'deleted','2026-01-01','2026-01-01','');"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    printf '%s\n' "SET @original_uid = (SELECT calendar_event_ics_uid FROM qa_calendar_event WHERE calendar_event_id=1);" \
        "SET @original_uid2 = (SELECT calendar_event_ics_uid FROM qa_calendar_event WHERE calendar_event_id=2);"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    printf '%s\n' \
        "SET @migration_ok = ((SELECT COUNT(*) FROM qa_calendar_event)=3 AND (SELECT COUNT(DISTINCT calendar_event_ics_uid) FROM qa_calendar_event)=3 AND (SELECT COUNT(*) FROM qa_calendar_event WHERE calendar_event_updated_at='2026-01-02 00:00:00')=3 AND (SELECT COUNT(*) FROM qa_calendar_event WHERE calendar_event_id=1 AND calendar_event_ics_uid=@original_uid AND calendar_event_title='日本語' AND calendar_event_note='memo')=1 AND (SELECT COUNT(*) FROM qa_calendar_event WHERE calendar_event_id=2 AND calendar_event_ics_uid=@original_uid2 AND calendar_event_owner=99)=1 AND (SELECT COUNT(*) FROM qa_calendar_source WHERE calendar_source_owner=0 AND calendar_source_flag=255 AND calendar_source_name='__migration_037_ics_uid__')=1);" \
        "SET @index_ok = ((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_calendar_event' AND INDEX_NAME='idx_calendar_event_ics_uid')=4);" \
        "SET @assert_sql=IF(@fresh_ok AND @migration_ok AND @index_ok,'SELECT 1','SELECT * FROM calendar_transfer_migration_assertion_failed');" \
        'PREPARE transfer_assert FROM @assert_sql; EXECUTE transfer_assert; DEALLOCATE PREPARE transfer_assert;'
} | run_bootstrap
echo 'PASS: MariaDB fresh schema and Migration 037 applied twice; stable distinct UID, preserved owner/content/timestamp, index and marker'
