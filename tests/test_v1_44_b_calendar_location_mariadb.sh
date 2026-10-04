#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIGRATION="$ROOT/database/migrations/034_v1_44_calendar_location.sql"

MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi

TEST_ROOT=$(mktemp -d /tmp/rss-v144b-mariadb.XXXXXX)
DATA_DIR="$TEST_ROOT/data"
mkdir -p "$DATA_DIR"
cleanup() {
    find "$TEST_ROOT" -depth -mindepth 1 -delete 2>/dev/null || true
    rmdir "$TEST_ROOT" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

"$INSTALL_DB" --basedir=/usr --datadir="$DATA_DIR" \
    --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1

run_bootstrap() {
    "$MARIADBD" --no-defaults --basedir=/usr --datadir="$DATA_DIR" \
        --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 2>&1
}

{
    printf '%s\n' \
      'CREATE DATABASE rss_v144b_migration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' \
      'USE rss_v144b_migration;' \
      'CREATE TABLE ig_calendar_event (calendar_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, calendar_event_url VARCHAR(2048) NULL DEFAULT NULL, PRIMARY KEY (calendar_event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' \
      'CREATE TABLE ig_calendar_event_exception (calendar_event_exception_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, calendar_event_exception_url VARCHAR(2048) NULL DEFAULT NULL, PRIMARY KEY (calendar_event_exception_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' \
      'INSERT INTO ig_calendar_event (calendar_event_url) VALUES (NULL);' \
      'INSERT INTO ig_calendar_event_exception (calendar_event_exception_url) VALUES (NULL);'
    cat "$MIGRATION"
    cat "$MIGRATION"
    printf '%s\n' \
      "SET @ok_event = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event' AND COLUMN_NAME = 'calendar_event_location' AND CHARACTER_MAXIMUM_LENGTH = 255) = 1);" \
      "SET @ok_exception = ((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ig_calendar_event_exception' AND COLUMN_NAME = 'calendar_event_exception_location' AND CHARACTER_MAXIMUM_LENGTH = 255) = 1);" \
      "SET @ok_existing = ((SELECT COUNT(*) FROM ig_calendar_event WHERE calendar_event_location IS NULL) = 1 AND (SELECT COUNT(*) FROM ig_calendar_event_exception WHERE calendar_event_exception_location IS NULL) = 1);" \
      "UPDATE ig_calendar_event SET calendar_event_location = '広島駅 南口 & 1F' WHERE calendar_event_id = 1;" \
      "SET @ok_unicode = ((SELECT calendar_event_location FROM ig_calendar_event WHERE calendar_event_id = 1) = '広島駅 南口 & 1F');" \
      'SET @all_ok = (@ok_event AND @ok_exception AND @ok_existing AND @ok_unicode);' \
      "SET @assert_sql = IF(@all_ok, 'SELECT 1', 'SELECT * FROM v144b_migration_assertion_failed');" \
      'PREPARE v144b_assert_stmt FROM @assert_sql;' \
      'EXECUTE v144b_assert_stmt;' \
      'DEALLOCATE PREPARE v144b_assert_stmt;'
} | run_bootstrap >/dev/null

echo 'PASS: MariaDB accepted V1.44-B migration twice'
echo 'PASS: parent and occurrence location columns are nullable VARCHAR(255)'
echo 'PASS: existing rows remain intact and Japanese location is stored'
echo 'RESULT: PASS 3 / FAIL 0 / SKIP 0'
