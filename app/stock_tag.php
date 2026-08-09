<?php

declare(strict_types=1);

const STOCK_TAG_MAX_PER_USER = 100;
const STOCK_TAG_MAX_PER_STOCK = 10;
const STOCK_TAG_MAX_NAME_LENGTH = 40;
const STOCK_TAG_SUGGESTION_MIN_SCORE = 70;

function stock_tag_validate_name(mixed $value): ?string
{
    $name = app_validate_text($value, STOCK_TAG_MAX_NAME_LENGTH, false);
    if ($name === null || str_contains($name, '<') || str_contains($name, '>')) {
        return null;
    }
    $name = preg_replace('/\s+/u', ' ', $name);
    if (!is_string($name)) {
        return null;
    }
    $name = trim($name);
    $name = preg_replace('/\A[,，、]+|[,，、]+\z/u', '', $name);
    if (!is_string($name)) {
        return null;
    }
    $name = trim($name);
    return $name !== '' ? $name : null;
}

function stock_tag_compare_key(string $value): string
{
    $value = trim($value);
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
        if (is_string($normalized)) {
            $value = $normalized;
        }
    }
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }
    $value = preg_replace('/\s+/u', ' ', $value);
    return is_string($value) ? trim($value) : '';
}

function stock_tag_text_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
    return stripos($haystack, $needle) !== false;
}

function stock_tag_title_contains_tag(string $title, string $tagName): bool
{
    $tagName = trim($tagName);
    if ($tagName === '') {
        return false;
    }
    if (preg_match('/\A[A-Za-z0-9.+#_-]+\z/u', $tagName) === 1) {
        $pattern = '/(?<![A-Za-z0-9])' . preg_quote($tagName, '/') . '(?![A-Za-z0-9])/iu';
        return preg_match($pattern, $title) === 1;
    }
    return stock_tag_text_contains($title, $tagName);
}


function stock_tag_domain_from_url(?string $url): string
{
    $validated = app_validate_stock_url($url);
    if ($validated === null) {
        return '';
    }
    $host = parse_url($validated, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower(rtrim($host, '.'));
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}


/** @return list<array{tag_id:int,tag_name:string,usage_count:int,last_used_at:string}> */
function stock_tag_list_user(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $sql = 'SELECT t.tag_id, t.tag_name, COUNT(s.stock_id) AS usage_count, COALESCE(MAX(CASE WHEN s.stock_id IS NOT NULL THEN m.map_date END), t.tag_date) AS last_used_at '
        . 'FROM ' . db_table_identifier('stock_tag') . ' t '
        . 'LEFT JOIN ' . db_table_identifier('stock_tag_map') . ' m '
        . 'ON m.map_owner = t.tag_owner AND m.map_tag_id = t.tag_id '
        . 'LEFT JOIN ' . db_table_identifier('content_stock') . ' s '
        . 'ON s.stock_id = m.map_stock_id AND s.stock_owner = t.tag_owner AND s.stock_flag = 0 '
        . 'WHERE t.tag_owner = :owner AND t.tag_flag = 0 '
        . 'GROUP BY t.tag_id, t.tag_name, t.tag_date '
        . 'ORDER BY usage_count DESC, last_used_at DESC, t.tag_name ASC, t.tag_id ASC '
        . 'LIMIT 100';
    $stmt = conn_db()->prepare($sql);
    $stmt->execute([':owner' => $ownerId]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $tagId = app_validate_positive_int($row['tag_id'] ?? null);
        $tagName = stock_tag_validate_name($row['tag_name'] ?? null);
        if ($tagId === null || $tagName === null) {
            continue;
        }
        $result[] = [
            'tag_id' => $tagId,
            'tag_name' => $tagName,
            'usage_count' => max(0, (int) ($row['usage_count'] ?? 0)),
            'last_used_at' => (string) ($row['last_used_at'] ?? ''),
        ];
    }
    return $result;
}

/** @return array{tag_id:int,tag_name:string}|null */
function stock_tag_find_owned(int $ownerId, int $tagId): ?array
{
    if ($ownerId <= 0 || $tagId <= 0) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT tag_id, tag_name FROM ' . db_table_identifier('stock_tag') . ' '
        . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0 LIMIT 1'
    );
    $stmt->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $name = stock_tag_validate_name($row['tag_name'] ?? null);
    return $name === null ? null : ['tag_id' => (int) $row['tag_id'], 'tag_name' => $name];
}

/** @param list<int> $stockIds @return array<int,list<array{tag_id:int,tag_name:string}>> */
function stock_tag_assigned_for_stocks(int $ownerId, array $stockIds): array
{
    $stockIds = array_values(array_unique(array_filter(array_map('intval', $stockIds), static fn(int $id): bool => $id > 0)));
    if ($ownerId <= 0 || $stockIds === []) {
        return [];
    }
    $stockIds = array_slice($stockIds, 0, 100);
    $params = [':map_owner' => $ownerId, ':tag_owner' => $ownerId];
    $placeholders = [];
    foreach ($stockIds as $index => $stockId) {
        $key = ':stock_' . $index;
        $placeholders[] = $key;
        $params[$key] = $stockId;
    }
    $sql = 'SELECT m.map_stock_id, t.tag_id, t.tag_name '
        . 'FROM ' . db_table_identifier('stock_tag_map') . ' m '
        . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' t ON t.tag_id = m.map_tag_id '
        . 'WHERE m.map_owner = :map_owner AND t.tag_owner = :tag_owner AND t.tag_flag = 0 '
        . 'AND m.map_stock_id IN (' . implode(', ', $placeholders) . ') '
        . 'ORDER BY t.tag_name ASC, t.tag_id ASC';
    $stmt = conn_db()->prepare($sql);
    $stmt->execute($params);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $stockId = (int) ($row['map_stock_id'] ?? 0);
        $tagId = (int) ($row['tag_id'] ?? 0);
        $tagName = stock_tag_validate_name($row['tag_name'] ?? null);
        if ($stockId <= 0 || $tagId <= 0 || $tagName === null) {
            continue;
        }
        $result[$stockId] ??= [];
        $result[$stockId][] = ['tag_id' => $tagId, 'tag_name' => $tagName];
    }
    return $result;
}

/** @return array<string,mixed>|null */
function stock_tag_lock_owned_stock(PDO $pdo, int $ownerId, int $stockId): ?array
{
    $sql = 'SELECT * FROM ' . db_table_identifier('content_stock') . ' '
        . 'WHERE stock_id = :stock_id AND stock_owner = :owner AND stock_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':stock_id' => $stockId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stock_tag_count_for_stock(PDO $pdo, int $ownerId, int $stockId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('stock_tag_map') . ' '
        . 'WHERE map_owner = :owner AND map_stock_id = :stock_id'
    );
    $stmt->execute([':owner' => $ownerId, ':stock_id' => $stockId]);
    return max(0, (int) $stmt->fetchColumn());
}

function stock_tag_count_for_user(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('stock_tag') . ' '
        . 'WHERE tag_owner = :owner AND tag_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId]);
    return max(0, (int) $stmt->fetchColumn());
}

/** @return array{tag_id:int,tag_name:string}|null */
function stock_tag_find_by_name(PDO $pdo, int $ownerId, string $tagName): ?array
{
    $targetKey = stock_tag_compare_key($tagName);
    $stmt = $pdo->prepare(
        'SELECT tag_id, tag_name FROM ' . db_table_identifier('stock_tag') . ' '
        . 'WHERE tag_owner = :owner AND tag_flag = 0 ORDER BY tag_id ASC LIMIT 100'
    );
    $stmt->execute([':owner' => $ownerId]);
    foreach ($stmt->fetchAll() as $row) {
        $existingName = stock_tag_validate_name($row['tag_name'] ?? null);
        if ($existingName !== null && hash_equals(stock_tag_compare_key($existingName), $targetKey)) {
            return ['tag_id' => (int) $row['tag_id'], 'tag_name' => $existingName];
        }
    }
    return null;
}

/** @return array{tag_id:int,tag_name:string} */
function stock_tag_find_or_create(PDO $pdo, int $ownerId, string $tagName): array
{
    $validated = stock_tag_validate_name($tagName);
    if ($validated === null) {
        throw new InvalidArgumentException('Tag name is invalid.');
    }
    $existing = stock_tag_find_by_name($pdo, $ownerId, $validated);
    if ($existing !== null) {
        return $existing;
    }
    if (stock_tag_count_for_user($pdo, $ownerId) >= STOCK_TAG_MAX_PER_USER) {
        throw new LengthException('A user can have up to ' . STOCK_TAG_MAX_PER_USER . ' Stock tags.');
    }

    $now = app_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('stock_tag') . ' '
            . '(tag_date, tag_updated_at, tag_flag, tag_owner, tag_name) '
            . 'VALUES (:created_at, :updated_at, 0, :owner, :tag_name)'
        );
        $stmt->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':owner' => $ownerId,
            ':tag_name' => $validated,
        ]);
        return ['tag_id' => (int) $pdo->lastInsertId(), 'tag_name' => $validated];
    } catch (PDOException $exception) {
        $existing = stock_tag_find_by_name($pdo, $ownerId, $validated);
        if ($existing !== null) {
            return $existing;
        }
        throw $exception;
    }
}

/** @return array{tag_id:int,tag_name:string,attached:bool} */
function stock_tag_attach(int $ownerId, int $stockId, ?int $tagId, ?string $tagName): array
{
    if ($ownerId <= 0 || $stockId <= 0 || ($tagId === null && $tagName === null)) {
        throw new InvalidArgumentException('Stock tag settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (stock_tag_lock_owned_stock($pdo, $ownerId, $stockId) === null) {
            throw new RuntimeException('Stock was not found.');
        }

        if ($tagId !== null) {
            $stmt = $pdo->prepare(
                'SELECT tag_id, tag_name FROM ' . db_table_identifier('stock_tag') . ' '
                . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0 LIMIT 1'
            );
            $stmt->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                throw new RuntimeException('Tag was not found.');
            }
            $tag = ['tag_id' => (int) $row['tag_id'], 'tag_name' => (string) $row['tag_name']];
        } else {
            $tag = stock_tag_find_or_create($pdo, $ownerId, (string) $tagName);
        }

        $check = $pdo->prepare(
            'SELECT map_id FROM ' . db_table_identifier('stock_tag_map') . ' '
            . 'WHERE map_owner = :owner AND map_stock_id = :stock_id AND map_tag_id = :tag_id LIMIT 1'
        );
        $check->execute([':owner' => $ownerId, ':stock_id' => $stockId, ':tag_id' => $tag['tag_id']]);
        if ($check->fetchColumn() !== false) {
            if ($started) {
                $pdo->commit();
            }
            return ['tag_id' => $tag['tag_id'], 'tag_name' => $tag['tag_name'], 'attached' => false];
        }

        if (stock_tag_count_for_stock($pdo, $ownerId, $stockId) >= STOCK_TAG_MAX_PER_STOCK) {
            throw new LengthException('A Stock item can have up to ' . STOCK_TAG_MAX_PER_STOCK . ' tags.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('stock_tag_map') . ' '
            . '(map_date, map_owner, map_stock_id, map_tag_id) '
            . 'VALUES (:map_date, :owner, :stock_id, :tag_id)'
        );
        $stmt->execute([
            ':map_date' => app_now(),
            ':owner' => $ownerId,
            ':stock_id' => $stockId,
            ':tag_id' => $tag['tag_id'],
        ]);
        if ($started) {
            $pdo->commit();
        }
        return ['tag_id' => $tag['tag_id'], 'tag_name' => $tag['tag_name'], 'attached' => true];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function stock_tag_detach(int $ownerId, int $stockId, int $tagId): bool
{
    if ($ownerId <= 0 || $stockId <= 0 || $tagId <= 0) {
        return false;
    }
    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        if (stock_tag_lock_owned_stock($pdo, $ownerId, $stockId) === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'DELETE FROM ' . db_table_identifier('stock_tag_map') . ' '
            . 'WHERE map_owner = :owner AND map_stock_id = :stock_id AND map_tag_id = :tag_id'
        );
        $stmt->execute([':owner' => $ownerId, ':stock_id' => $stockId, ':tag_id' => $tagId]);
        $deleted = $stmt->rowCount() > 0;
        if ($started) {
            $pdo->commit();
        }
        return $deleted;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array{tag_id:int,tag_name:string,merged:bool,merged_from_id:int|null} */
function stock_tag_rename(int $ownerId, int $tagId, string $newName): array
{
    $validated = stock_tag_validate_name($newName);
    if ($ownerId <= 0 || $tagId <= 0 || $validated === null) {
        throw new InvalidArgumentException('Tag settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $sql = 'SELECT tag_id, tag_name FROM ' . db_table_identifier('stock_tag') . ' '
            . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Tag was not found.');
        }
        $currentName = stock_tag_validate_name($row['tag_name'] ?? null);
        if ($currentName === null) {
            throw new RuntimeException('Tag was not found.');
        }

        $existing = stock_tag_find_by_name($pdo, $ownerId, $validated);
        if ($existing !== null && (int) $existing['tag_id'] !== $tagId) {
            $targetTagId = (int) $existing['tag_id'];
            $mapStmt = $pdo->prepare(
                'SELECT map_id, map_stock_id FROM ' . db_table_identifier('stock_tag_map') . ' '
                . 'WHERE map_owner = :owner AND map_tag_id = :tag_id ORDER BY map_id ASC'
            );
            $mapStmt->execute([':owner' => $ownerId, ':tag_id' => $tagId]);
            $maps = $mapStmt->fetchAll();

            $targetCheck = $pdo->prepare(
                'SELECT map_id FROM ' . db_table_identifier('stock_tag_map') . ' '
                . 'WHERE map_owner = :owner AND map_stock_id = :stock_id AND map_tag_id = :tag_id LIMIT 1'
            );
            $deleteMap = $pdo->prepare(
                'DELETE FROM ' . db_table_identifier('stock_tag_map') . ' WHERE map_id = :map_id AND map_owner = :owner'
            );
            $updateMap = $pdo->prepare(
                'UPDATE ' . db_table_identifier('stock_tag_map') . ' SET map_tag_id = :target_tag_id '
                . 'WHERE map_id = :map_id AND map_owner = :owner'
            );
            foreach ($maps as $map) {
                $mapId = (int) ($map['map_id'] ?? 0);
                $stockId = (int) ($map['map_stock_id'] ?? 0);
                if ($mapId <= 0 || $stockId <= 0) {
                    continue;
                }
                $targetCheck->execute([
                    ':owner' => $ownerId,
                    ':stock_id' => $stockId,
                    ':tag_id' => $targetTagId,
                ]);
                if ($targetCheck->fetchColumn() !== false) {
                    $deleteMap->execute([':map_id' => $mapId, ':owner' => $ownerId]);
                } else {
                    $updateMap->execute([
                        ':target_tag_id' => $targetTagId,
                        ':map_id' => $mapId,
                        ':owner' => $ownerId,
                    ]);
                }
            }

            $deleteTag = $pdo->prepare(
                'DELETE FROM ' . db_table_identifier('stock_tag') . ' '
                . 'WHERE tag_id = :tag_id AND tag_owner = :owner'
            );
            $deleteTag->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
            $touchTarget = $pdo->prepare(
                'UPDATE ' . db_table_identifier('stock_tag') . ' SET tag_updated_at = :updated_at '
                . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0'
            );
            $touchTarget->execute([
                ':updated_at' => app_now(),
                ':tag_id' => $targetTagId,
                ':owner' => $ownerId,
            ]);

            if ($started) {
                $pdo->commit();
            }
            return [
                'tag_id' => $targetTagId,
                'tag_name' => (string) $existing['tag_name'],
                'merged' => true,
                'merged_from_id' => $tagId,
            ];
        }

        if ($currentName !== $validated) {
            $update = $pdo->prepare(
                'UPDATE ' . db_table_identifier('stock_tag') . ' '
                . 'SET tag_name = :tag_name, tag_updated_at = :updated_at '
                . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0'
            );
            $update->execute([
                ':tag_name' => $validated,
                ':updated_at' => app_now(),
                ':tag_id' => $tagId,
                ':owner' => $ownerId,
            ]);
        }

        if ($started) {
            $pdo->commit();
        }
        return ['tag_id' => $tagId, 'tag_name' => $validated, 'merged' => false, 'merged_from_id' => null];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array{tag_id:int,tag_name:string,detached_count:int} */
function stock_tag_delete(int $ownerId, int $tagId): array
{
    if ($ownerId <= 0 || $tagId <= 0) {
        throw new InvalidArgumentException('Tag settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $sql = 'SELECT tag_id, tag_name FROM ' . db_table_identifier('stock_tag') . ' '
            . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Tag was not found.');
        }
        $tagName = stock_tag_validate_name($row['tag_name'] ?? null);
        if ($tagName === null) {
            throw new RuntimeException('Tag was not found.');
        }

        $deleteMaps = $pdo->prepare(
            'DELETE FROM ' . db_table_identifier('stock_tag_map') . ' '
            . 'WHERE map_owner = :owner AND map_tag_id = :tag_id'
        );
        $deleteMaps->execute([':owner' => $ownerId, ':tag_id' => $tagId]);
        $detachedCount = max(0, $deleteMaps->rowCount());

        $deleteTag = $pdo->prepare(
            'DELETE FROM ' . db_table_identifier('stock_tag') . ' '
            . 'WHERE tag_id = :tag_id AND tag_owner = :owner AND tag_flag = 0'
        );
        $deleteTag->execute([':tag_id' => $tagId, ':owner' => $ownerId]);
        if ($deleteTag->rowCount() <= 0) {
            throw new RuntimeException('Tag was not found.');
        }

        if ($started) {
            $pdo->commit();
        }
        return ['tag_id' => $tagId, 'tag_name' => $tagName, 'detached_count' => $detachedCount];
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return list<string> */
function stock_tag_extract_title_candidates(string $title): array
{
    $title = trim(strip_tags($title));
    if ($title === '' || !app_is_valid_utf8($title)) {
        return [];
    }

    $candidates = [];
    $add = static function (string $value) use (&$candidates): void {
        $value = stock_tag_validate_name($value) ?? '';
        if ($value === '') {
            return;
        }
        $unwrapped = preg_replace('/\A[【\[「『]+|[】\]」』]+\z/u', '', $value);
        if (is_string($unwrapped) && $unwrapped !== '') {
            $value = trim($unwrapped);
        }
        $trimmedSuffix = preg_replace('/(?:(?:最新|速報|まとめ|紹介|解説|変更|対応|情報|発表|更新|追加|記事|アップデート|リリース))+$/u', '', $value);
        if (is_string($trimmedSuffix) && app_text_length($trimmedSuffix) >= 2) {
            $value = $trimmedSuffix;
        }
        $key = stock_tag_compare_key($value);
        if ($key === '' || isset($candidates[$key])) {
            return;
        }
        $stop = [
            '新機能', '機能', '変更', '追加', '更新', '対応', '発表', '記事', '情報', 'まとめ', '方法', '使い方',
            'ニュース', '速報', '最新', '今回', '今日', '明日', '今週', '今年', '公式', 'サービス', 'サイト', 'ページ',
            'ポイント', 'おすすめ', '紹介', '解説', '詳細', '理由', '結果', '予定', '開始', '公開', '提供',
            'アップデート', 'リリース', 'news', 'update', 'new', 'release', 'version', 'about', 'with', 'from', 'this', 'that', 'the',
            'how', 'what', 'why', 'when', 'where', 'your', 'you', 'and', 'for', 'into', 'using', 'use',
        ];
        foreach ($stop as $stopWord) {
            if ($key === stock_tag_compare_key($stopWord)) {
                return;
            }
        }
        if (app_text_length($value) < 2 || app_text_length($value) > STOCK_TAG_MAX_NAME_LENGTH) {
            return;
        }
        $candidates[$key] = $value;
    };

    if (preg_match_all('/([A-Za-z][A-Za-z0-9.+#_-]{1,20})\s+([0-9]+(?:\.[0-9]+){1,3})(?![0-9.])/u', $title, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $add($match[1] . ' ' . $match[2]);
        }
    }
    if (preg_match_all('/[A-Za-z][A-Za-z0-9.+#_-]{1,31}/u', $title, $matches)) {
        foreach ($matches[0] as $match) {
            $add($match);
        }
    }
    if (preg_match_all('/[\p{Han}\p{Katakana}ー]{2,16}/u', $title, $matches)) {
        foreach ($matches[0] as $match) {
            $add($match);
        }
    }
    if (preg_match_all('/[【\[「『]([^】\]」』]{2,24})[】\]」』]/u', $title, $matches)) {
        foreach ($matches[1] as $match) {
            $add($match);
        }
    }

    return array_slice(array_values($candidates), 0, 12);
}

/**
 * Build lightweight learning data from the user's recent Stock items.
 * content_stock does not keep the original Feed id, so the normalized article
 * Domain is used as the safest available source tendency. V1.11-D counts
 * untagged Stock items in sample_count as well, avoiding inflated ratios.
 *
 * @param list<array<string,mixed>> $stockRows
 * @return array<string,list<array{tag_id:int,tag_name:string,match_count:int,sample_count:int,ratio:float}>>
 */
function stock_tag_domain_tendencies(int $ownerId, array $stockRows, int $rowLimit = 600): array
{
    if ($ownerId <= 0 || $stockRows === []) {
        return [];
    }

    $targetDomains = [];
    foreach ($stockRows as $row) {
        $domain = stock_tag_domain_from_url(isset($row['stock_data']) ? (string) $row['stock_data'] : null);
        if ($domain !== '') {
            $targetDomains[$domain] = true;
        }
    }
    if ($targetDomains === []) {
        return [];
    }

    $rowLimit = max(100, min(1200, $rowLimit));
    $stockStmt = conn_db()->prepare(
        'SELECT stock_id, stock_data FROM ' . db_table_identifier('content_stock') . ' '
        . 'WHERE stock_owner = :owner AND stock_flag = 0 '
        . 'ORDER BY stock_id DESC LIMIT ' . $rowLimit
    );
    $stockStmt->execute([':owner' => $ownerId]);

    $domainStocks = [];
    $stockDomains = [];
    foreach ($stockStmt->fetchAll() as $row) {
        $stockId = (int) ($row['stock_id'] ?? 0);
        $domain = stock_tag_domain_from_url(isset($row['stock_data']) ? (string) $row['stock_data'] : null);
        if ($stockId <= 0 || $domain === '' || !isset($targetDomains[$domain])) {
            continue;
        }
        $domainStocks[$domain] ??= [];
        $domainStocks[$domain][$stockId] = true;
        $stockDomains[$stockId] = $domain;
    }
    if ($stockDomains === []) {
        return [];
    }

    $domainTagCounts = [];
    $stockIds = array_keys($stockDomains);
    foreach (array_chunk($stockIds, 200) as $chunkIndex => $chunk) {
        $params = [':map_owner' => $ownerId, ':tag_owner' => $ownerId];
        $placeholders = [];
        foreach ($chunk as $index => $stockId) {
            $key = ':stock_' . $chunkIndex . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $stockId;
        }
        $sql = 'SELECT m.map_stock_id, t.tag_id, t.tag_name '
            . 'FROM ' . db_table_identifier('stock_tag_map') . ' m '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' t '
            . 'ON t.tag_id = m.map_tag_id AND t.tag_owner = :tag_owner AND t.tag_flag = 0 '
            . 'WHERE m.map_owner = :map_owner AND m.map_stock_id IN (' . implode(', ', $placeholders) . ')';
        $stmt = conn_db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stockId = (int) ($row['map_stock_id'] ?? 0);
            $tagId = (int) ($row['tag_id'] ?? 0);
            $tagName = stock_tag_validate_name($row['tag_name'] ?? null);
            $domain = $stockDomains[$stockId] ?? '';
            if ($domain === '' || $tagId <= 0 || $tagName === null) {
                continue;
            }
            $domainTagCounts[$domain] ??= [];
            if (!isset($domainTagCounts[$domain][$tagId])) {
                $domainTagCounts[$domain][$tagId] = [
                    'tag_id' => $tagId,
                    'tag_name' => $tagName,
                    'stock_ids' => [],
                ];
            }
            $domainTagCounts[$domain][$tagId]['stock_ids'][$stockId] = true;
        }
    }

    $result = [];
    foreach ($domainStocks as $domain => $stocks) {
        $sampleCount = count($stocks);
        if ($sampleCount < 2 || !isset($domainTagCounts[$domain])) {
            continue;
        }
        foreach ($domainTagCounts[$domain] as $tag) {
            $matchCount = count($tag['stock_ids']);
            $ratio = $sampleCount > 0 ? $matchCount / $sampleCount : 0.0;
            if ($matchCount < 2 || $ratio < 0.40) {
                continue;
            }
            $result[$domain][] = [
                'tag_id' => (int) $tag['tag_id'],
                'tag_name' => (string) $tag['tag_name'],
                'match_count' => $matchCount,
                'sample_count' => $sampleCount,
                'ratio' => $ratio,
            ];
        }
        if (isset($result[$domain])) {
            usort($result[$domain], static function (array $a, array $b): int {
                $ratioCompare = $b['ratio'] <=> $a['ratio'];
                if ($ratioCompare !== 0) {
                    return $ratioCompare;
                }
                $countCompare = $b['match_count'] <=> $a['match_count'];
                return $countCompare !== 0 ? $countCompare : strcasecmp($a['tag_name'], $b['tag_name']);
            });
            $result[$domain] = array_slice($result[$domain], 0, 8);
        }
    }

    return $result;
}

/**
 * Learn tags that the user repeatedly combines on the same Stock item.
 * This is intentionally one-hop only; inferred tags never become new seeds in
 * the same evaluation, preventing automatic inference chains.
 *
 * @return array<int,list<array{tag_id:int,tag_name:string,match_count:int,sample_count:int,ratio:float}>>
 */
function stock_tag_cooccurrence_tendencies(int $ownerId, int $rowLimit = 600): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $rowLimit = max(100, min(1200, $rowLimit));
    $stockStmt = conn_db()->prepare(
        'SELECT stock_id FROM ' . db_table_identifier('content_stock') . ' '
        . 'WHERE stock_owner = :owner AND stock_flag = 0 '
        . 'ORDER BY stock_id DESC LIMIT ' . $rowLimit
    );
    $stockStmt->execute([':owner' => $ownerId]);
    $stockIds = [];
    foreach ($stockStmt->fetchAll() as $row) {
        $stockId = (int) ($row['stock_id'] ?? 0);
        if ($stockId > 0) {
            $stockIds[] = $stockId;
        }
    }
    if ($stockIds === []) {
        return [];
    }

    $stocks = [];
    foreach (array_chunk($stockIds, 200) as $chunkIndex => $chunk) {
        $params = [':map_owner' => $ownerId, ':tag_owner' => $ownerId];
        $placeholders = [];
        foreach ($chunk as $index => $stockId) {
            $key = ':stock_' . $chunkIndex . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $stockId;
        }
        $sql = 'SELECT m.map_stock_id, t.tag_id, t.tag_name '
            . 'FROM ' . db_table_identifier('stock_tag_map') . ' m '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' t '
            . 'ON t.tag_id = m.map_tag_id AND t.tag_owner = :tag_owner AND t.tag_flag = 0 '
            . 'WHERE m.map_owner = :map_owner AND m.map_stock_id IN (' . implode(', ', $placeholders) . ') '
            . 'ORDER BY m.map_stock_id ASC, t.tag_id ASC';
        $stmt = conn_db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $stockId = (int) ($row['map_stock_id'] ?? 0);
            $tagId = (int) ($row['tag_id'] ?? 0);
            $tagName = stock_tag_validate_name($row['tag_name'] ?? null);
            if ($stockId <= 0 || $tagId <= 0 || $tagName === null) {
                continue;
            }
            $stocks[$stockId] ??= [];
            $stocks[$stockId][$tagId] = ['tag_id' => $tagId, 'tag_name' => $tagName];
        }
    }

    $seedCounts = [];
    $pairCounts = [];
    $tagNames = [];
    foreach ($stocks as $tags) {
        foreach ($tags as $seedId => $seedTag) {
            $seedCounts[$seedId] = ($seedCounts[$seedId] ?? 0) + 1;
            $tagNames[$seedId] = $seedTag['tag_name'];
            foreach ($tags as $candidateId => $candidateTag) {
                if ($candidateId === $seedId) {
                    continue;
                }
                $pairCounts[$seedId] ??= [];
                $pairCounts[$seedId][$candidateId] = ($pairCounts[$seedId][$candidateId] ?? 0) + 1;
                $tagNames[$candidateId] = $candidateTag['tag_name'];
            }
        }
    }

    $result = [];
    foreach ($pairCounts as $seedId => $candidates) {
        $sampleCount = (int) ($seedCounts[$seedId] ?? 0);
        if ($sampleCount < 2) {
            continue;
        }
        foreach ($candidates as $candidateId => $matchCount) {
            $ratio = $sampleCount > 0 ? $matchCount / $sampleCount : 0.0;
            if ($matchCount < 2 || $ratio < 0.50 || !isset($tagNames[$candidateId])) {
                continue;
            }
            $result[$seedId][] = [
                'tag_id' => (int) $candidateId,
                'tag_name' => (string) $tagNames[$candidateId],
                'match_count' => (int) $matchCount,
                'sample_count' => $sampleCount,
                'ratio' => $ratio,
            ];
        }
        if (isset($result[$seedId])) {
            usort($result[$seedId], static function (array $a, array $b): int {
                $ratioCompare = $b['ratio'] <=> $a['ratio'];
                if ($ratioCompare !== 0) {
                    return $ratioCompare;
                }
                $countCompare = $b['match_count'] <=> $a['match_count'];
                return $countCompare !== 0 ? $countCompare : strcasecmp($a['tag_name'], $b['tag_name']);
            });
            $result[$seedId] = array_slice($result[$seedId], 0, 6);
        }
    }

    return $result;
}

/** @param list<array{tag_id:int,tag_name:string,usage_count:int,last_used_at?:string}> $userTags
 *  @param list<array{tag_id:int,tag_name:string}> $assignedTags
 *  @param list<array{tag_id:int,tag_name:string,match_count:int,sample_count:int,ratio:float}> $domainTendencies
 *  @param array<int,list<array{tag_id:int,tag_name:string,match_count:int,sample_count:int,ratio:float}>> $cooccurrenceTendencies
 *  @return list<array{tag_id:int,tag_name:string,score:int,reason:string,is_new:bool,confidence:string,auto_attach:bool}>
 */
function stock_tag_suggestions(
    array $stockRow,
    array $userTags,
    array $assignedTags,
    array $domainTendencies = [],
    array $cooccurrenceTendencies = []
): array {
    $title = (string) ($stockRow['stock_title'] ?? '');
    $url = app_validate_stock_url($stockRow['stock_data'] ?? null) ?? '';
    $domain = stock_tag_domain_from_url($url);
    $assigned = [];
    $seedTagIds = [];
    $seedNames = [];
    foreach ($assignedTags as $tag) {
        $tagId = (int) ($tag['tag_id'] ?? 0);
        $tagName = (string) ($tag['tag_name'] ?? '');
        $assigned[stock_tag_compare_key($tagName)] = true;
        if ($tagId > 0 && $tagName !== '') {
            $seedTagIds[$tagId] = true;
            $seedNames[$tagId] = $tagName;
        }
    }

    $scored = [];
    $put = static function (
        int $tagId,
        string $name,
        int $score,
        string $reason,
        bool $isNew,
        bool $autoAttach = false
    ) use (&$scored, $assigned): void {
        $name = stock_tag_validate_name($name) ?? '';
        $key = stock_tag_compare_key($name);
        if ($name === '' || $key === '' || isset($assigned[$key]) || $score < STOCK_TAG_SUGGESTION_MIN_SCORE) {
            return;
        }
        if (!isset($scored[$key])) {
            $scored[$key] = [
                'tag_id' => $tagId,
                'tag_name' => $name,
                'score' => $score,
                'reason' => $reason,
                'is_new' => $isNew,
                'confidence' => $autoAttach ? 'high' : 'medium',
                'auto_attach' => $autoAttach,
            ];
            return;
        }

        $current = $scored[$key];
        if ($score > (int) $current['score']) {
            $current['tag_id'] = $tagId;
            $current['tag_name'] = $name;
            $current['score'] = $score;
            $current['is_new'] = $isNew;
        }
        if ($reason !== '' && !str_contains((string) $current['reason'], $reason)) {
            $current['reason'] .= ' / ' . $reason;
        }
        $current['auto_attach'] = (bool) $current['auto_attach'] || $autoAttach;
        $current['confidence'] = $current['auto_attach'] ? 'high' : 'medium';
        $scored[$key] = $current;
    };

    foreach ($userTags as $tag) {
        $tagId = (int) ($tag['tag_id'] ?? 0);
        $name = (string) ($tag['tag_name'] ?? '');
        if ($tagId <= 0 || $name === '') {
            continue;
        }
        if (stock_tag_title_contains_tag($title, $name)) {
            $seedTagIds[$tagId] = true;
            $seedNames[$tagId] = $name;
            $put($tagId, $name, 120 + min(20, (int) $tag['usage_count']), 'タイトルと既存Tagが一致', false, true);
        } elseif ($domain !== '' && stock_tag_text_contains($domain, stock_tag_compare_key($name))) {
            $seedTagIds[$tagId] = true;
            $seedNames[$tagId] = $name;
            $put($tagId, $name, 112 + min(15, (int) $tag['usage_count']), 'Domainと既存Tagが一致', false, false);
        }
    }

    foreach ($domainTendencies as $tendency) {
        $tagId = (int) ($tendency['tag_id'] ?? 0);
        $tagName = (string) ($tendency['tag_name'] ?? '');
        $matchCount = max(0, (int) ($tendency['match_count'] ?? 0));
        $sampleCount = max(0, (int) ($tendency['sample_count'] ?? 0));
        $ratio = max(0.0, min(1.0, (float) ($tendency['ratio'] ?? 0.0)));
        if ($tagId <= 0 || $tagName === '' || $matchCount < 2 || $sampleCount < 2 || $ratio < 0.40) {
            continue;
        }

        $autoAttach = $sampleCount >= 5 && $matchCount >= 4 && $ratio >= 0.80;
        $score = 92 + min(18, $matchCount * 3) + (int) round($ratio * 12);
        if ($autoAttach) {
            $score += 12;
        }
        $reason = '同じDomainで過去' . $matchCount . '/' . $sampleCount . '件に使用';
        $put($tagId, $tagName, $score, $reason, false, $autoAttach);
    }

    foreach (array_keys($seedTagIds) as $seedTagId) {
        if (!isset($cooccurrenceTendencies[$seedTagId])) {
            continue;
        }
        $seedName = $seedNames[$seedTagId] ?? ('Tag #' . $seedTagId);
        foreach ($cooccurrenceTendencies[$seedTagId] as $tendency) {
            $tagId = (int) ($tendency['tag_id'] ?? 0);
            $tagName = (string) ($tendency['tag_name'] ?? '');
            $matchCount = max(0, (int) ($tendency['match_count'] ?? 0));
            $sampleCount = max(0, (int) ($tendency['sample_count'] ?? 0));
            $ratio = max(0.0, min(1.0, (float) ($tendency['ratio'] ?? 0.0)));
            if ($tagId <= 0 || isset($seedTagIds[$tagId]) || $tagName === '' || $matchCount < 2 || $sampleCount < 2 || $ratio < 0.50) {
                continue;
            }
            $autoAttach = $sampleCount >= 5 && $matchCount >= 4 && $ratio >= 0.80;
            $score = 88 + min(16, $matchCount * 2) + (int) round($ratio * 12) + ($autoAttach ? 12 : 0);
            $reason = 'Tag「' . $seedName . '」と過去' . $matchCount . '/' . $sampleCount . '件で併用';
            $put($tagId, $tagName, $score, $reason, false, $autoAttach);
        }
    }

    $userTagByKey = [];
    foreach ($userTags as $tag) {
        $userTagByKey[stock_tag_compare_key((string) $tag['tag_name'])] = $tag;
    }
    foreach (stock_tag_extract_title_candidates($title) as $candidate) {
        $key = stock_tag_compare_key($candidate);
        if (isset($userTagByKey[$key])) {
            $tag = $userTagByKey[$key];
            $put((int) $tag['tag_id'], (string) $tag['tag_name'], 115 + min(20, (int) $tag['usage_count']), 'タイトルから既存Tagを検出', false, true);
        } else {
            $candidateScore = 70;
            if (preg_match('/[0-9]/u', $candidate) === 1) {
                $candidateScore += 6;
            }
            if (preg_match('/\A[A-Za-z][A-Za-z0-9.+#_-]*(?:\s+[0-9]+(?:\.[0-9]+){1,3})?\z/u', $candidate) === 1) {
                $candidateScore += 4;
            }
            $put(0, $candidate, $candidateScore, 'タイトルから候補を抽出', true, false);
        }
    }

    $result = array_values($scored);
    usort($result, static function (array $a, array $b): int {
        $autoCompare = ((int) $b['auto_attach']) <=> ((int) $a['auto_attach']);
        if ($autoCompare !== 0) {
            return $autoCompare;
        }
        $scoreCompare = $b['score'] <=> $a['score'];
        return $scoreCompare !== 0 ? $scoreCompare : strcasecmp($a['tag_name'], $b['tag_name']);
    });
    return array_slice($result, 0, 5);
}

/** @param list<array{tag_id:int,tag_name:string,usage_count:int}> $userTags
 *  @param list<array{tag_id:int,tag_name:string}> $assignedTags
 *  @return list<array{tag_id:int,tag_name:string,usage_count:int}>
 */
function stock_tag_popular_unassigned(array $userTags, array $assignedTags, int $limit = 5): array
{
    $assigned = [];
    foreach ($assignedTags as $tag) {
        $assigned[(int) ($tag['tag_id'] ?? 0)] = true;
    }
    $result = [];
    foreach ($userTags as $tag) {
        $tagId = (int) ($tag['tag_id'] ?? 0);
        if ($tagId <= 0 || isset($assigned[$tagId])) {
            continue;
        }
        $result[] = $tag;
        if (count($result) >= max(1, min(10, $limit))) {
            break;
        }
    }
    return $result;
}
