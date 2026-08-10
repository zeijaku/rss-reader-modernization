<?php

declare(strict_types=1);

const FEED_KEYWORD_MAX_PER_USER = 50;
const FEED_KEYWORD_MAX_VALUE_LENGTH = 64;

function feed_keyword_validate_value(mixed $value): ?string
{
    if (!is_string($value) || !app_is_valid_utf8($value) || app_has_control_characters($value)) {
        return null;
    }

    $value = preg_replace('/\s+/u', ' ', trim($value));
    if (!is_string($value) || $value === '' || app_text_length($value) > FEED_KEYWORD_MAX_VALUE_LENGTH) {
        return null;
    }

    return $value;
}

function feed_keyword_compare_key(string $value): string
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

/** @return list<array{keyword_id:int,keyword_value:string}> */
function feed_keyword_list_user(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }

    $stmt = conn_db()->prepare(
        'SELECT keyword_id, keyword_value FROM ' . db_table_identifier('feed_keyword') . ' '
        . 'WHERE keyword_owner = :owner AND keyword_flag = 0 '
        . 'ORDER BY keyword_value ASC, keyword_id ASC LIMIT ' . FEED_KEYWORD_MAX_PER_USER
    );
    $stmt->execute([':owner' => $ownerId]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $keywordId = app_validate_positive_int($row['keyword_id'] ?? null);
        $keywordValue = feed_keyword_validate_value($row['keyword_value'] ?? null);
        if ($keywordId === null || $keywordValue === null) {
            continue;
        }
        $result[] = [
            'keyword_id' => $keywordId,
            'keyword_value' => $keywordValue,
        ];
    }
    return $result;
}

/** @return array{keyword_id:int,keyword_value:string,keyword_flag:int}|null */
function feed_keyword_find_owned(PDO $pdo, int $ownerId, int $keywordId, bool $includeInactive = false): ?array
{
    if ($ownerId <= 0 || $keywordId <= 0) {
        return null;
    }

    $sql = 'SELECT keyword_id, keyword_value, keyword_flag FROM ' . db_table_identifier('feed_keyword') . ' '
        . 'WHERE keyword_id = :keyword_id AND keyword_owner = :owner';
    if (!$includeInactive) {
        $sql .= ' AND keyword_flag = 0';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':keyword_id' => $keywordId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $value = feed_keyword_validate_value($row['keyword_value'] ?? null);
    if ($value === null) {
        return null;
    }

    return [
        'keyword_id' => (int) $row['keyword_id'],
        'keyword_value' => $value,
        'keyword_flag' => (int) ($row['keyword_flag'] ?? 0),
    ];
}

/** @return array{keyword_id:int,keyword_value:string,keyword_flag:int}|null */
function feed_keyword_find_by_value(PDO $pdo, int $ownerId, string $keywordValue): ?array
{
    $targetKey = feed_keyword_compare_key($keywordValue);
    if ($ownerId <= 0 || $targetKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT keyword_id, keyword_value, keyword_flag FROM ' . db_table_identifier('feed_keyword') . ' '
        . 'WHERE keyword_owner = :owner ORDER BY keyword_flag ASC, keyword_id ASC LIMIT 250'
    );
    $stmt->execute([':owner' => $ownerId]);

    foreach ($stmt->fetchAll() as $row) {
        $existingValue = feed_keyword_validate_value($row['keyword_value'] ?? null);
        if ($existingValue === null || feed_keyword_compare_key($existingValue) !== $targetKey) {
            continue;
        }
        return [
            'keyword_id' => (int) ($row['keyword_id'] ?? 0),
            'keyword_value' => $existingValue,
            'keyword_flag' => (int) ($row['keyword_flag'] ?? 0),
        ];
    }

    return null;
}

function feed_keyword_count_active(PDO $pdo, int $ownerId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('feed_keyword') . ' '
        . 'WHERE keyword_owner = :owner AND keyword_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId]);
    return max(0, (int) $stmt->fetchColumn());
}

function feed_keyword_lock_owner(PDO $pdo, int $ownerId): bool
{
    $sql = 'SELECT user_id FROM ' . db_table_identifier('user_info') . ' '
        . 'WHERE user_id = :owner AND user_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId]);
    return $stmt->fetchColumn() !== false;
}

/** @return array{keyword_id:int,keyword_value:string,created:bool,restored:bool} */
function feed_keyword_create(int $ownerId, string $keywordValue): array
{
    $validated = feed_keyword_validate_value($keywordValue);
    if ($ownerId <= 0 || $validated === null) {
        throw new InvalidArgumentException('Keyword is invalid.');
    }

    $pdo = conn_db();
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        if (!feed_keyword_lock_owner($pdo, $ownerId)) {
            throw new RuntimeException('User was not found.');
        }

        $existing = feed_keyword_find_by_value($pdo, $ownerId, $validated);
        if ($existing !== null && $existing['keyword_flag'] === 0) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'keyword_id' => $existing['keyword_id'],
                'keyword_value' => $existing['keyword_value'],
                'created' => false,
                'restored' => false,
            ];
        }

        if (feed_keyword_count_active($pdo, $ownerId) >= FEED_KEYWORD_MAX_PER_USER) {
            throw new LengthException('A user can have up to ' . FEED_KEYWORD_MAX_PER_USER . ' RSS Highlight keywords.');
        }

        $now = app_now();
        if ($existing !== null) {
            $stmt = $pdo->prepare(
                'UPDATE ' . db_table_identifier('feed_keyword') . ' '
                . 'SET keyword_value = :keyword_value, keyword_flag = 0, keyword_updated_at = :updated_at '
                . 'WHERE keyword_id = :keyword_id AND keyword_owner = :owner AND keyword_flag <> 0'
            );
            $stmt->execute([
                ':keyword_value' => $validated,
                ':updated_at' => $now,
                ':keyword_id' => $existing['keyword_id'],
                ':owner' => $ownerId,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Keyword could not be restored.');
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'keyword_id' => $existing['keyword_id'],
                'keyword_value' => $validated,
                'created' => true,
                'restored' => true,
            ];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('feed_keyword') . ' '
            . '(keyword_date, keyword_updated_at, keyword_flag, keyword_owner, keyword_value) '
            . 'VALUES (:created_at, :updated_at, 0, :owner, :keyword_value)'
        );
        $stmt->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':owner' => $ownerId,
            ':keyword_value' => $validated,
        ]);
        $keywordId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
        return [
            'keyword_id' => $keywordId,
            'keyword_value' => $validated,
            'created' => true,
            'restored' => false,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function feed_keyword_delete(int $ownerId, int $keywordId): bool
{
    if ($ownerId <= 0 || $keywordId <= 0) {
        return false;
    }

    $pdo = conn_db();
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        if (!feed_keyword_lock_owner($pdo, $ownerId)) {
            throw new RuntimeException('User was not found.');
        }
        if (feed_keyword_find_owned($pdo, $ownerId, $keywordId) === null) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return false;
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('feed_keyword') . ' '
            . 'SET keyword_flag = 1, keyword_updated_at = :updated_at '
            . 'WHERE keyword_id = :keyword_id AND keyword_owner = :owner AND keyword_flag = 0'
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':keyword_id' => $keywordId,
            ':owner' => $ownerId,
        ]);
        $deleted = $stmt->rowCount() === 1;

        if ($startedTransaction) {
            $pdo->commit();
        }
        return $deleted;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
