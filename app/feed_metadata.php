<?php

declare(strict_types=1);

const FEED_METADATA_TITLE_MAX_LENGTH = 255;
const FEED_METADATA_SITE_URL_MAX_LENGTH = 1024;
const FEED_METADATA_CATEGORY_MAX_LENGTH = 512;

/** @return list<array<string,mixed>> */
function feed_metadata_list_owned(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT c.content_id, c.content_value AS feed_url, c.content_location, c.content_style, '
        . "COALESCE(m.feed_title, '') AS feed_title, COALESCE(m.site_url, '') AS site_url, "
        . "COALESCE(m.category_path, '') AS category_path "
        . 'FROM ' . db_table_identifier('content') . ' c '
        . 'LEFT JOIN ' . db_table_identifier('feed_metadata') . ' m ON m.metadata_content_id = c.content_id '
        . 'WHERE c.content_owner = :owner AND c.content_flag = 0 '
        . 'ORDER BY c.content_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function feed_metadata_upsert(
    int $contentId,
    string $feedTitle,
    string $siteUrl,
    string $categoryPath
): void {
    if ($contentId <= 0) {
        throw new InvalidArgumentException('Feed metadata content id is invalid.');
    }

    $feedTitle = app_validate_text($feedTitle, FEED_METADATA_TITLE_MAX_LENGTH, true) ?? '';
    $siteUrl = $siteUrl === '' ? '' : (app_validate_external_link($siteUrl, FEED_METADATA_SITE_URL_MAX_LENGTH) ?? '');
    $categoryPath = app_validate_text($categoryPath, FEED_METADATA_CATEGORY_MAX_LENGTH, true) ?? '';
    $now = app_now();
    $pdo = conn_db();

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_metadata') . ' '
            . '(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) '
            . 'VALUES (:content_id, :feed_title, :site_url, :category_path, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE feed_title = VALUES(feed_title), site_url = VALUES(site_url), '
            . 'category_path = VALUES(category_path), updated_at = VALUES(updated_at)'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_metadata') . ' '
            . '(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) '
            . 'VALUES (:content_id, :feed_title, :site_url, :category_path, :created_at, :updated_at) '
            . 'ON CONFLICT(metadata_content_id) DO UPDATE SET feed_title = excluded.feed_title, '
            . 'site_url = excluded.site_url, category_path = excluded.category_path, updated_at = excluded.updated_at'
        );
    }

    $stmt->execute([
        ':content_id' => $contentId,
        ':feed_title' => $feedTitle,
        ':site_url' => $siteUrl,
        ':category_path' => $categoryPath,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

/**
 * Fill a feed title only when the owned feed has no stored title yet.
 * Existing OPML titles and the other metadata fields are never overwritten.
 */
function feed_metadata_fill_title_if_empty(int $ownerId, int $contentId, string $feedTitle): bool
{
    if ($ownerId <= 0 || $contentId <= 0) {
        return false;
    }

    $feedTitle = app_validate_text($feedTitle, FEED_METADATA_TITLE_MAX_LENGTH, false) ?? '';
    if ($feedTitle === '') {
        return false;
    }

    $pdo = conn_db();
    $now = app_now();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_metadata') . ' '
            . '(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) '
            . 'SELECT c.content_id, :feed_title, \'\', \'\', :created_at, :updated_at '
            . 'FROM ' . db_table_identifier('content') . ' c '
            . 'WHERE c.content_id = :content_id AND c.content_owner = :owner AND c.content_flag = 0 '
            . "ON DUPLICATE KEY UPDATE feed_title = IF(feed_title = '', VALUES(feed_title), feed_title)"
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_metadata') . ' '
            . '(metadata_content_id, feed_title, site_url, category_path, created_at, updated_at) '
            . 'SELECT c.content_id, :feed_title, \'\', \'\', :created_at, :updated_at '
            . 'FROM ' . db_table_identifier('content') . ' c '
            . 'WHERE c.content_id = :content_id AND c.content_owner = :owner AND c.content_flag = 0 '
            . "ON CONFLICT(metadata_content_id) DO UPDATE SET feed_title = CASE WHEN feed_title = '' THEN excluded.feed_title ELSE feed_title END"
        );
    }

    $stmt->execute([
        ':feed_title' => $feedTitle,
        ':created_at' => $now,
        ':updated_at' => $now,
        ':content_id' => $contentId,
        ':owner' => $ownerId,
    ]);
    return $stmt->rowCount() > 0;
}

/** @return array<string,int> normalized URL => content id */
function feed_metadata_owned_url_map(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT content_id, content_value FROM ' . db_table_identifier('content') . ' '
        . 'WHERE content_owner = :owner AND content_flag = 0 ORDER BY content_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = app_validate_feed_url($row['content_value'] ?? null);
        if ($normalized === null) {
            continue;
        }
        $result[$normalized] = (int) ($row['content_id'] ?? 0);
    }
    return $result;
}
