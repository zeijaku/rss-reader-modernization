<?php

declare(strict_types=1);

/**
 * V1.1-C NEW表示用の状態管理。
 * Feed本文や記事Titleは保存せず、既存Item Identityと時刻だけを保持する。
 */

function feed_item_state_valid_identity(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/\Am1i:v1:[a-f0-9]{64}\z/D', $value) !== 1) {
        return null;
    }

    return $value;
}

/**
 * DB処理前に差分を組み立てる。Testからも同じ判定を確認出来るよう、ここではI/Oを行わない。
 *
 * @param list<array<string,mixed>> $items
 * @param array<string,array{seen_at:?string}> $existingRows
 * @return array{
 *   items:list<array<string,mixed>>,
 *   insert_rows:list<array{item_identity:string,seen_at:?string}>,
 *   touch_identities:list<string>,
 *   new_count:int
 * }
 */
function feed_item_state_plan(array $items, array $existingRows, bool $initialBaseline, string $now): array
{
    $newIdentities = [];
    $insertRows = [];
    $touchIdentities = [];
    $plannedSeenAt = [];
    $resultItems = [];

    foreach ($items as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }

        $item = $rawItem;
        $identity = feed_item_state_valid_identity($item['item_identity'] ?? null);
        $isNew = false;

        if ($identity !== null) {
            if (array_key_exists($identity, $plannedSeenAt)) {
                $seenAt = $plannedSeenAt[$identity];
            } elseif (isset($existingRows[$identity])) {
                $seenAt = $existingRows[$identity]['seen_at'] ?? null;
                $plannedSeenAt[$identity] = $seenAt;
                $touchIdentities[] = $identity;
            } else {
                $seenAt = $initialBaseline ? $now : null;
                $plannedSeenAt[$identity] = $seenAt;
                $insertRows[] = [
                    'item_identity' => $identity,
                    'seen_at' => $seenAt,
                ];
            }

            $isNew = $seenAt === null;
        }

        $item['is_new'] = $isNew;
        $resultItems[] = $item;
        if ($isNew && $identity !== null) {
            $newIdentities[$identity] = true;
        }
    }

    return [
        'items' => $resultItems,
        'insert_rows' => $insertRows,
        'touch_identities' => array_values(array_unique($touchIdentities)),
        'new_count' => count($newIdentities),
    ];
}

/** @return array<string,array{seen_at:?string}> */
function feed_item_state_load_existing(PDO $pdo, int $ownerId, int $contentId, array $identities): array
{
    $result = [];
    foreach (array_chunk(array_values(array_unique($identities)), 100) as $chunkIndex => $chunk) {
        if ($chunk === []) {
            continue;
        }

        $params = [
            ':owner_id' => $ownerId,
            ':content_id' => $contentId,
        ];
        $placeholders = [];
        foreach ($chunk as $index => $identity) {
            $name = ':identity_' . $chunkIndex . '_' . $index;
            $placeholders[] = $name;
            $params[$name] = $identity;
        }

        $stmt = $pdo->prepare(
            'SELECT item_identity, seen_at FROM ' . db_table_identifier('feed_item_state') . ' '
            . 'WHERE owner_id = :owner_id AND content_id = :content_id '
            . 'AND state_flag = 0 AND item_identity IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $identity = feed_item_state_valid_identity($row['item_identity'] ?? null);
            if ($identity === null) {
                continue;
            }
            $result[$identity] = [
                'seen_at' => isset($row['seen_at']) && is_string($row['seen_at']) && $row['seen_at'] !== ''
                    ? $row['seen_at']
                    : null,
            ];
        }
    }

    return $result;
}

function feed_item_state_lock_owned_content(PDO $pdo, int $ownerId, int $contentId): bool
{
    $sql = 'SELECT content_id FROM ' . db_table_identifier('content') . ' '
        . 'WHERE content_id = :content_id AND content_owner = :owner_id AND content_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $contentId,
        ':owner_id' => $ownerId,
    ]);
    return $stmt->fetchColumn() !== false;
}

/**
 * @param list<array<string,mixed>> $items
 * @return array{items:list<array<string,mixed>>,new_count:int,initial_baseline:bool}
 */
function feed_item_state_sync(int $ownerId, int $contentId, array $items): array
{
    if ($ownerId <= 0 || $contentId <= 0) {
        throw new InvalidArgumentException('Feed item state owner/content is invalid.');
    }

    $identities = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $identity = feed_item_state_valid_identity($item['item_identity'] ?? null);
        if ($identity !== null) {
            $identities[] = $identity;
        }
    }

    if ($identities === []) {
        $plan = feed_item_state_plan($items, [], true, app_now());
        return [
            'items' => $plan['items'],
            'new_count' => 0,
            'initial_baseline' => false,
        ];
    }

    $pdo = conn_db();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if (!feed_item_state_lock_owned_content($pdo, $ownerId, $contentId)) {
            throw new RuntimeException('Owned Feed content was not found while saving item state.');
        }

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . db_table_identifier('feed_item_state') . ' '
            . 'WHERE owner_id = :owner_id AND content_id = :content_id'
        );
        $countStmt->execute([
            ':owner_id' => $ownerId,
            ':content_id' => $contentId,
        ]);
        $initialBaseline = (int) $countStmt->fetchColumn() === 0;
        $now = app_now();
        $existingRows = feed_item_state_load_existing($pdo, $ownerId, $contentId, $identities);
        $plan = feed_item_state_plan($items, $existingRows, $initialBaseline, $now);

        $insertStmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_item_state') . ' '
            . '(owner_id, content_id, item_identity, first_seen_at, last_seen_at, seen_at, state_flag) '
            . 'VALUES (:owner_id, :content_id, :item_identity, :first_seen_at, :last_seen_at, :seen_at, 0)'
        );
        foreach ($plan['insert_rows'] as $row) {
            $insertStmt->execute([
                ':owner_id' => $ownerId,
                ':content_id' => $contentId,
                ':item_identity' => $row['item_identity'],
                ':first_seen_at' => $now,
                ':last_seen_at' => $now,
                ':seen_at' => $row['seen_at'],
            ]);
        }

        if ($plan['touch_identities'] !== []) {
            $touchStmt = $pdo->prepare(
                'UPDATE ' . db_table_identifier('feed_item_state') . ' '
                . 'SET last_seen_at = :last_seen_at '
                . 'WHERE owner_id = :owner_id AND content_id = :content_id '
                . 'AND item_identity = :item_identity AND state_flag = 0'
            );
            foreach ($plan['touch_identities'] as $identity) {
                $touchStmt->execute([
                    ':last_seen_at' => $now,
                    ':owner_id' => $ownerId,
                    ':content_id' => $contentId,
                    ':item_identity' => $identity,
                ]);
            }
        }

        feed_item_state_cleanup_inactive($pdo, $ownerId, $now);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'items' => $plan['items'],
            'new_count' => $plan['new_count'],
            'initial_baseline' => $initialBaseline,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function feed_item_state_cleanup_inactive(PDO $pdo, int $ownerId, string $now): int
{
    $days = max(1, min(3650, (int) APP_FEED_ITEM_STATE_RETENTION_DAYS));
    $reference = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $now,
        new DateTimeZone('Asia/Tokyo')
    );
    if (!$reference instanceof DateTimeImmutable) {
        throw new RuntimeException('Feed item state cleanup time is invalid.');
    }
    $cutoff = $reference->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare(
            'DELETE state FROM ' . db_table_identifier('feed_item_state') . ' state '
            . 'LEFT JOIN ' . db_table_identifier('content') . ' content '
            . 'ON content.content_id = state.content_id AND content.content_owner = state.owner_id '
            . 'WHERE state.owner_id = :owner_id AND state.last_seen_at < :cutoff '
            . 'AND (content.content_id IS NULL OR content.content_flag <> 0)'
        );
    } else {
        $stmt = $pdo->prepare(
            'DELETE FROM ' . db_table_identifier('feed_item_state') . ' '
            . 'WHERE owner_id = :owner_id AND last_seen_at < :cutoff '
            . 'AND NOT EXISTS (SELECT 1 FROM ' . db_table_identifier('content') . ' content '
            . 'WHERE content.content_id = ' . db_table_identifier('feed_item_state') . '.content_id '
            . 'AND content.content_owner = ' . db_table_identifier('feed_item_state') . '.owner_id '
            . 'AND content.content_flag = 0)'
        );
    }

    $stmt->execute([
        ':owner_id' => $ownerId,
        ':cutoff' => $cutoff,
    ]);
    return $stmt->rowCount();
}

function feed_item_state_mark_seen(int $ownerId, int $contentId, ?string $itemIdentity = null): int
{
    if ($ownerId <= 0 || $contentId <= 0) {
        throw new InvalidArgumentException('Feed item state owner/content is invalid.');
    }
    if ($itemIdentity !== null && feed_item_state_valid_identity($itemIdentity) === null) {
        throw new InvalidArgumentException('item_identity is invalid.');
    }

    $pdo = conn_db();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if (!feed_item_state_lock_owned_content($pdo, $ownerId, $contentId)) {
            throw new RuntimeException('Owned Feed content was not found while updating item state.');
        }

        $sql = 'UPDATE ' . db_table_identifier('feed_item_state') . ' '
            . 'SET seen_at = :seen_at '
            . 'WHERE owner_id = :owner_id AND content_id = :content_id '
            . 'AND state_flag = 0 AND seen_at IS NULL';
        $params = [
            ':seen_at' => app_now(),
            ':owner_id' => $ownerId,
            ':content_id' => $contentId,
        ];
        if ($itemIdentity !== null) {
            $sql .= ' AND item_identity = :item_identity';
            $params[':item_identity'] = $itemIdentity;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        if ($startedTransaction) {
            $pdo->commit();
        }
        return $count;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
