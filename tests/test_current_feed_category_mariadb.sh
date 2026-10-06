#!/usr/bin/env sh
set -eu

MARIADBD=$(command -v mariadbd || true)
INSTALL_DB=$(command -v mariadb-install-db || true)
if [ ! -x "$MARIADBD" ] || [ ! -x "$INSTALL_DB" ]; then
    echo 'RESULT: PASS 0 / FAIL 0 / SKIP 1 (MariaDB server tools unavailable)'
    exit 0
fi

TEST_ROOT=$(mktemp -d /tmp/rss-v145a-mariadb.XXXXXX)
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
    cat <<'SQL'
CREATE DATABASE rss_v145a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rss_v145a;

CREATE TABLE rss_content (
    content_id INT UNSIGNED NOT NULL,
    content_owner INT UNSIGNED NOT NULL,
    content_flag TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rss_feed_metadata (
    metadata_content_id INT UNSIGNED NOT NULL,
    feed_title VARCHAR(255) NOT NULL DEFAULT '',
    site_url VARCHAR(1024) NOT NULL DEFAULT '',
    category_path VARCHAR(512) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (metadata_content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rss_content (content_id, content_owner, content_flag) VALUES
    (1, 7, 0),
    (2, 7, 0),
    (3, 8, 0),
    (4, 7, 1);

INSERT INTO rss_feed_metadata
    (metadata_content_id, feed_title, site_url, category_path, created_at, updated_at)
VALUES
    (1, 'Owner 7 A', 'https://example.test/a', 'Cloud', NOW(), NOW()),
    (2, 'Owner 7 B', 'https://example.test/b', 'News', NOW(), NOW()),
    (3, 'Owner 8', 'https://example.test/c', 'Cloud', NOW(), NOW()),
    (4, 'Inactive', 'https://example.test/d', 'Cloud', NOW(), NOW());

INSERT INTO rss_feed_metadata
    (metadata_content_id, feed_title, site_url, category_path, created_at, updated_at)
SELECT c.content_id, '', '', 'Cloud', NOW(), NOW()
FROM rss_content c
WHERE c.content_id = 2 AND c.content_owner = 7 AND c.content_flag = 0
ON DUPLICATE KEY UPDATE category_path = VALUES(category_path), updated_at = VALUES(updated_at);

INSERT INTO rss_feed_metadata
    (metadata_content_id, feed_title, site_url, category_path, created_at, updated_at)
SELECT c.content_id, '', '', 'ShouldNotChange', NOW(), NOW()
FROM rss_content c
WHERE c.content_id = 3 AND c.content_owner = 7 AND c.content_flag = 0
ON DUPLICATE KEY UPDATE category_path = VALUES(category_path), updated_at = VALUES(updated_at);

SET @ok_set_owned = ((SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 2) = 'Cloud');
SET @ok_set_preserves = ((SELECT CONCAT(feed_title, '|', site_url) FROM rss_feed_metadata WHERE metadata_content_id = 2) = 'Owner 7 B|https://example.test/b');
SET @ok_set_blocks_other = ((SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 3) = 'Cloud');

UPDATE rss_feed_metadata SET category_path = 'Platform', updated_at = NOW()
WHERE category_path = 'Cloud'
AND EXISTS (
    SELECT 1 FROM rss_content c
    WHERE c.content_id = rss_feed_metadata.metadata_content_id
      AND c.content_owner = 7
      AND c.content_flag = 0
);

SET @ok_rename_owned = ((SELECT COUNT(*) FROM rss_feed_metadata WHERE metadata_content_id IN (1,2) AND category_path = 'Platform') = 2);
SET @ok_rename_blocks_other = ((SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 3) = 'Cloud');
SET @ok_rename_blocks_inactive = ((SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id = 4) = 'Cloud');

UPDATE rss_feed_metadata SET category_path = '', updated_at = NOW()
WHERE category_path = 'Platform'
AND EXISTS (
    SELECT 1 FROM rss_content c
    WHERE c.content_id = rss_feed_metadata.metadata_content_id
      AND c.content_owner = 7
      AND c.content_flag = 0
);

SET @ok_delete_uncategorized = ((SELECT COUNT(*) FROM rss_feed_metadata WHERE metadata_content_id IN (1,2) AND category_path = '') = 2);
SET @ok_delete_keeps_feed = ((SELECT COUNT(*) FROM rss_content WHERE content_id IN (1,2) AND content_owner = 7 AND content_flag = 0) = 2);
SET @ok_metadata_preserved = ((SELECT COUNT(*) FROM rss_feed_metadata WHERE metadata_content_id IN (1,2) AND feed_title <> '' AND site_url <> '') = 2);

SET @all_ok = (
    @ok_set_owned
    AND @ok_set_preserves
    AND @ok_set_blocks_other
    AND @ok_rename_owned
    AND @ok_rename_blocks_other
    AND @ok_rename_blocks_inactive
    AND @ok_delete_uncategorized
    AND @ok_delete_keeps_feed
    AND @ok_metadata_preserved
);
SET @assert_sql = IF(@all_ok, 'SELECT 1', 'SELECT * FROM v145a_category_assertion_failed');
PREPARE v145a_assert_stmt FROM @assert_sql;
EXECUTE v145a_assert_stmt;
DEALLOCATE PREPARE v145a_assert_stmt;
SQL
} | run_bootstrap >/dev/null

echo 'PASS: MariaDB accepts V1.45-A per-Feed Category upsert syntax'
echo 'PASS: per-Feed Category update preserves title/site metadata'
echo 'PASS: another owner Feed cannot be changed through the owner-scoped setter'
echo 'PASS: Category rename affects only active Feeds owned by the authenticated owner'
echo 'PASS: Category delete returns owned Feeds to uncategorized without deleting Feed rows'
echo 'RESULT: PASS 5 / FAIL 0 / SKIP 0'
