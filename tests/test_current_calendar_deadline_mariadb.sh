#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
BASE=${MARIADB_TEST_BASE:-/usr}
SERVER="$BASE/sbin/mariadbd"
INSTALL="$BASE/bin/mariadb-install-db"
if [ ! -x "$SERVER" ] || [ ! -x "$INSTALL" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi
TEST_DIR=$(mktemp -d /tmp/rss-deadline-db.XXXXXX)
trap 'rm -rf "$TEST_DIR"' EXIT HUP INT TERM
"$INSTALL" --no-defaults --basedir="$BASE" --datadir="$TEST_DIR/data" --auth-root-authentication-method=normal --skip-test-db > "$TEST_DIR/install.log" 2>&1
{
    printf '%s\n' 'CREATE DATABASE deadline_test;' 'USE deadline_test;' 'CREATE TABLE qa_calendar_event (calendar_event_id INT PRIMARY KEY);' 'CREATE TABLE qa_calendar_event_exception (calendar_event_exception_id INT PRIMARY KEY);' 'INSERT INTO qa_calendar_event VALUES (1);' 'INSERT INTO qa_calendar_event_exception VALUES (1);'
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$ROOT/database/migrations/032_v1_41_calendar_deadline.sql"
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'qa_';/" "$ROOT/database/migrations/032_v1_41_calendar_deadline.sql"
    printf '%s\n' 'SET @ok = ((SELECT calendar_event_deadline_highlight FROM qa_calendar_event WHERE calendar_event_id=1)=0 AND (SELECT calendar_event_exception_deadline_highlight IS NULL FROM qa_calendar_event_exception WHERE calendar_event_exception_id=1));' "SET @sql=IF(@ok,'SELECT 1','SELECT * FROM deadline_defaults_assertion_failed');" 'PREPARE assertion_stmt FROM @sql;' 'EXECUTE assertion_stmt;' 'DEALLOCATE PREPARE assertion_stmt;'
    sed "s/SET @table_prefix = 'ig_';/SET @table_prefix = 'invalid-prefix';/" "$ROOT/database/migrations/032_v1_41_calendar_deadline.sql"
    printf '%s\n' "SET @sql=IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE())=2,'SELECT 1','SELECT * FROM invalid_prefix_assertion_failed');" 'PREPARE assertion_stmt FROM @sql;' 'EXECUTE assertion_stmt;' 'DEALLOCATE PREPARE assertion_stmt;'
    sed "s/SET @table_prefix = 'rss_';/SET @table_prefix = 'fresh_';/" "$ROOT/database/schema.sql"
    printf '%s\n' "SET @ok=((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='fresh_calendar_event' AND COLUMN_NAME='calendar_event_deadline_highlight') OR (TABLE_NAME='fresh_calendar_event_exception' AND COLUMN_NAME='calendar_event_exception_deadline_highlight')))=2);" "SET @sql=IF(@ok,'SELECT 1','SELECT * FROM fresh_deadline_assertion_failed');" 'PREPARE assertion_stmt FROM @sql;' 'EXECUTE assertion_stmt;' 'DEALLOCATE PREPARE assertion_stmt;'
} | sed 's/; EXECUTE/;\nEXECUTE/g; s/; DEALLOCATE/;\nDEALLOCATE/g' | "$SERVER" --no-defaults --basedir="$BASE" --datadir="$TEST_DIR/data" --bootstrap --user="$(id -un)" --wsrep-on=OFF --innodb-use-native-aio=0 > "$TEST_DIR/test.log" 2>&1 || { cat "$TEST_DIR/test.log"; exit 1; }
echo 'PASS: deadline migration preserves rows, defaults OFF / inherit, repeats safely, rejects invalid prefix and matches fresh schema'
echo 'RESULT: PASS 5 / FAIL 0 / SKIP 0'
