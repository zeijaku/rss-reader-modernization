#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIGRATION="$ROOT/database/migrations/038_v1_55_memo_format.sql"
MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    if [ "${REQUIRE_MEMO_MARIADB:-0}" = 1 ]; then
        echo 'FAIL: MariaDB server tools required'; exit 1
    fi
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi
TEST_ROOT=$(mktemp -d /tmp/rss-memo-mariadb.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"
SERVER_PID=''
cleanup() {
    if [ -n "$SERVER_PID" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true
    rmdir "$TEST_ROOT" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM
"$INSTALL_DB" --basedir=/usr --datadir="$DATA_DIR" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1
# Run the actual client SQL parser (schema includes multiple statements per line).
# Isolated socket; never connect to the runner's system database.
SOCKET="$TEST_ROOT/db.sock"
"$MARIADBD" --no-defaults --basedir=/usr --datadir="$DATA_DIR" --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 --skip-networking --socket="$SOCKET" --pid-file="$TEST_ROOT/db.pid" >"$TEST_ROOT/server.log" 2>&1 &
SERVER_PID=$!
n=0
until mariadb --no-defaults --protocol=SOCKET --socket="$SOCKET" -u root -e 'SELECT 1' >/dev/null 2>&1; do
    n=$((n+1))
    if [ "$n" -ge 100 ] || ! kill -0 "$SERVER_PID" 2>/dev/null; then
        cat "$TEST_ROOT/server.log" >&2; exit 1
    fi
    sleep .1
done
run_client() {
    mariadb --no-defaults --protocol=SOCKET --socket="$SOCKET" -u root --default-character-set=utf8mb4 --batch
}

{
    printf '%s\n' 'CREATE DATABASE rss_memo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' 'USE rss_memo;'
    sed "s/SET @table_prefix = 'rss_';/SET @table_prefix = 'qa_';/" "$ROOT/database/schema.sql"
    printf '%s\n' \
        "SET @fresh_ok = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_memo' AND COLUMN_NAME='memo_format' AND CHARACTER_MAXIMUM_LENGTH=8 AND IS_NULLABLE='NO')=1);" \
        "SET @fresh_assert=IF(@fresh_ok,'SELECT 1','SELECT * FROM memo_fresh_failed'); PREPARE fresh_assert FROM @fresh_assert; EXECUTE fresh_assert; DEALLOCATE PREPARE fresh_assert;" \
        'ALTER TABLE qa_memo DROP COLUMN memo_format;' \
        "INSERT INTO qa_memo (memo_date,memo_updated_at,memo_flag,memo_owner,memo_title,memo_body) VALUES ('2026-01-01','2026-01-02',0,42,'既存',CONCAT('  memo',CHAR(10),'tail  ')),('2026-01-01','2026-01-03',1,99,'other','deleted');" \
        "CREATE USER 'memoqa'@'localhost'; GRANT SELECT, ALTER, INSERT, UPDATE ON rss_memo.qa_memo TO 'memoqa'@'localhost';"
} | run_client
{
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    printf '%s\n' "SET @preserved=((SELECT COUNT(*) FROM qa_memo WHERE memo_format='text')=2 AND (SELECT COUNT(*) FROM qa_memo WHERE memo_id=1 AND memo_owner=42 AND memo_title='既存' AND BINARY memo_body=BINARY CONCAT('  memo',CHAR(10),'tail  ') AND memo_updated_at='2026-01-02 00:00:00')=1 AND (SELECT COUNT(*) FROM qa_memo WHERE memo_id=2 AND memo_owner=99 AND memo_flag=1 AND memo_body='deleted' AND memo_updated_at='2026-01-03 00:00:00')=1);" \
        "UPDATE qa_memo SET memo_format='markdown' WHERE memo_id=1;"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$MIGRATION"
    printf '%s\n' \
        "INSERT INTO qa_memo (memo_date,memo_updated_at,memo_flag,memo_owner,memo_title,memo_body,memo_format) VALUES (NOW(),NOW(),0,42,'max',REPEAT(_utf8mb4 0xF09F9880,16000),'markdown');" \
        "SET @ok=(@preserved AND (SELECT COUNT(*) FROM qa_memo WHERE memo_id=1 AND memo_format='markdown')=1 AND (SELECT COUNT(*) FROM qa_memo WHERE memo_title='max' AND CHAR_LENGTH(memo_body)=16000 AND OCTET_LENGTH(memo_body)=64000)=1);" \
        "SET @assert_sql=IF(@ok,'SELECT 1','SELECT * FROM memo_migration_assertion_failed'); PREPARE memo_assert FROM @assert_sql; EXECUTE memo_assert; DEALLOCATE PREPARE memo_assert;"
} | mariadb --no-defaults --protocol=SOCKET --socket="$SOCKET" -u memoqa --default-character-set=utf8mb4 --batch rss_memo
echo 'PASS: MariaDB Migration 038 twice with restricted user, original Memo content/timestamps preserved, 64000-byte UTF-8 TEXT'
