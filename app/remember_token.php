<?php

declare(strict_types=1);

const REMEMBER_TOKEN_SELECTOR_BYTES = 12;
const REMEMBER_TOKEN_VALIDATOR_BYTES = 32;
const REMEMBER_TOKEN_TTL_SECONDS = 2592000; // Fixed 30 days.

/**
 * Create a persistent-login token for an active user.
 *
 * The raw validator is returned only for the future cookie integration. The
 * database stores its SHA-256 digest and never stores the cookie value.
 *
 * @return array{ok:bool,cookie_value?:string,expires_at?:int,reason?:string}
 */
function remember_token_issue(int $userId, ?int $now = null): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'reason' => 'invalid_user'];
    }

    $timestamp = $now ?? time();
    $pdo = conn_db();
    $started = remember_token_begin_transaction($pdo);

    try {
        $sql = 'SELECT user_id FROM ' . db_table_identifier('user_info')
            . ' WHERE user_id = :user_id AND user_flag = 0 LIMIT 1';
        if (remember_token_supports_for_update($pdo)) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        if ($stmt->fetchColumn() === false) {
            remember_token_commit_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'invalid_user'];
        }

        $material = remember_token_generate_material();
        $expiresAt = $timestamp + REMEMBER_TOKEN_TTL_SECONDS;

        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('remember_token') . ' ('
            . 'remember_token_user_id, remember_token_selector, remember_token_validator_hash, '
            . 'remember_token_created_at, remember_token_expires_at, remember_token_last_used_at'
            . ') VALUES ('
            . ':user_id, :selector, :validator_hash, :created_at, :expires_at, NULL'
            . ')'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $material['selector'],
            ':validator_hash' => remember_token_hash_validator($material['validator']),
            ':created_at' => remember_token_datetime($timestamp),
            ':expires_at' => remember_token_datetime($expiresAt),
        ]);

        remember_token_commit_if_started($pdo, $started);
        return [
            'ok' => true,
            'cookie_value' => remember_token_encode($material['selector'], $material['validator']),
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $exception) {
        remember_token_rollback_if_started($pdo, $started);
        throw $exception;
    }
}

/**
 * Validate a cookie token and rotate its validator while keeping fixed expiry.
 *
 * @return array{ok:bool,user_id?:int,cookie_value?:string,expires_at?:int,reason?:string}
 */
function remember_token_validate_and_rotate(string $cookieValue, ?int $now = null): array
{
    $parsed = remember_token_parse($cookieValue);
    if ($parsed === null) {
        return ['ok' => false, 'reason' => 'invalid_format'];
    }

    $timestamp = $now ?? time();
    $pdo = conn_db();
    $started = remember_token_begin_transaction($pdo);

    try {
        $sql = 'SELECT rt.remember_token_id, rt.remember_token_user_id, '
            . 'rt.remember_token_validator_hash, rt.remember_token_expires_at, ui.user_flag '
            . 'FROM ' . db_table_identifier('remember_token') . ' rt '
            . 'LEFT JOIN ' . db_table_identifier('user_info') . ' ui '
            . 'ON ui.user_id = rt.remember_token_user_id '
            . 'WHERE rt.remember_token_selector = :selector LIMIT 1';
        if (remember_token_supports_for_update($pdo)) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':selector' => $parsed['selector']]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            remember_token_commit_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'invalid_token'];
        }

        $tokenId = (int) ($row['remember_token_id'] ?? 0);
        $userId = (int) ($row['remember_token_user_id'] ?? 0);
        $userFlag = $row['user_flag'] ?? null;
        $storedHash = (string) ($row['remember_token_validator_hash'] ?? '');
        $expiresAt = remember_token_datetime_to_timestamp((string) ($row['remember_token_expires_at'] ?? ''));

        if ($tokenId <= 0 || $userId <= 0 || $userFlag === null || (int) $userFlag !== 0) {
            remember_token_delete_by_id($pdo, $tokenId);
            remember_token_commit_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'inactive_user'];
        }

        if ($expiresAt === null || $expiresAt <= $timestamp) {
            remember_token_delete_by_id($pdo, $tokenId);
            remember_token_commit_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'expired'];
        }

        $candidateHash = remember_token_hash_validator($parsed['validator']);
        if (preg_match('/\A[a-f0-9]{64}\z/D', $storedHash) !== 1 || !hash_equals($storedHash, $candidateHash)) {
            // A selector with the wrong validator can indicate theft or replay.
            remember_token_delete_by_id($pdo, $tokenId);
            remember_token_commit_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'invalid_token'];
        }

        $newValidator = bin2hex(random_bytes(REMEMBER_TOKEN_VALIDATOR_BYTES));
        $newHash = remember_token_hash_validator($newValidator);
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('remember_token') . ' '
            . 'SET remember_token_validator_hash = :validator_hash, '
            . 'remember_token_last_used_at = :last_used_at '
            . 'WHERE remember_token_id = :token_id '
            . 'AND remember_token_validator_hash = :previous_hash'
        );
        $stmt->execute([
            ':validator_hash' => $newHash,
            ':last_used_at' => remember_token_datetime($timestamp),
            ':token_id' => $tokenId,
            ':previous_hash' => $storedHash,
        ]);
        if ($stmt->rowCount() !== 1) {
            remember_token_rollback_if_started($pdo, $started);
            return ['ok' => false, 'reason' => 'rotation_failed'];
        }

        remember_token_commit_if_started($pdo, $started);
        return [
            'ok' => true,
            'user_id' => $userId,
            'cookie_value' => remember_token_encode($parsed['selector'], $newValidator),
            'expires_at' => $expiresAt,
        ];
    } catch (Throwable $exception) {
        remember_token_rollback_if_started($pdo, $started);
        throw $exception;
    }
}

/** Revoke the token identified by a cookie value. */
function remember_token_revoke_cookie(string $cookieValue): bool
{
    $parsed = remember_token_parse($cookieValue);
    if ($parsed === null) {
        return false;
    }

    $stmt = conn_db()->prepare(
        'DELETE FROM ' . db_table_identifier('remember_token') . ' '
        . 'WHERE remember_token_selector = :selector'
    );
    $stmt->execute([':selector' => $parsed['selector']]);
    return $stmt->rowCount() > 0;
}

/** Revoke every persistent-login token for a user, for example after password change. */
function remember_token_revoke_user(int $userId, ?PDO $pdo = null): int
{
    if ($userId <= 0) {
        return 0;
    }

    $stmt = ($pdo ?? conn_db())->prepare(
        'DELETE FROM ' . db_table_identifier('remember_token') . ' '
        . 'WHERE remember_token_user_id = :user_id'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}

/** Delete expired persistent-login tokens. */
function remember_token_cleanup_expired(?int $now = null): int
{
    $stmt = conn_db()->prepare(
        'DELETE FROM ' . db_table_identifier('remember_token') . ' '
        . 'WHERE remember_token_expires_at <= :expires_at'
    );
    $stmt->execute([':expires_at' => remember_token_datetime($now ?? time())]);
    return $stmt->rowCount();
}

/** @return array{selector:string,validator:string}|null */
function remember_token_parse(string $cookieValue): ?array
{
    if (preg_match('/\A([a-f0-9]{24})\.([a-f0-9]{64})\z/D', $cookieValue, $matches) !== 1) {
        return null;
    }

    return ['selector' => $matches[1], 'validator' => $matches[2]];
}

function remember_token_encode(string $selector, string $validator): string
{
    if (preg_match('/\A[a-f0-9]{24}\z/D', $selector) !== 1
        || preg_match('/\A[a-f0-9]{64}\z/D', $validator) !== 1) {
        throw new InvalidArgumentException('Invalid remember token material.');
    }

    return $selector . '.' . $validator;
}

function remember_token_hash_validator(string $validator): string
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $validator) !== 1) {
        throw new InvalidArgumentException('Invalid remember token validator.');
    }

    return hash('sha256', $validator);
}

/** @return array{selector:string,validator:string} */
function remember_token_generate_material(): array
{
    return [
        'selector' => bin2hex(random_bytes(REMEMBER_TOKEN_SELECTOR_BYTES)),
        'validator' => bin2hex(random_bytes(REMEMBER_TOKEN_VALIDATOR_BYTES)),
    ];
}

function remember_token_datetime(int $timestamp): string
{
    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone('Asia/Tokyo'))
        ->format('Y-m-d H:i:s');
}

function remember_token_datetime_to_timestamp(string $value): ?int
{
    $timezone = new DateTimeZone('Asia/Tokyo');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable) {
        return null;
    }
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }
    if ($date->format('Y-m-d H:i:s') !== $value) {
        return null;
    }

    return $date->getTimestamp();
}

function remember_token_supports_for_update(PDO $pdo): bool
{
    return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
}

function remember_token_begin_transaction(PDO $pdo): bool
{
    if ($pdo->inTransaction()) {
        return false;
    }
    $pdo->beginTransaction();
    return true;
}

function remember_token_commit_if_started(PDO $pdo, bool $started): void
{
    if ($started && $pdo->inTransaction()) {
        $pdo->commit();
    }
}

function remember_token_rollback_if_started(PDO $pdo, bool $started): void
{
    if ($started && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

function remember_token_delete_by_id(PDO $pdo, int $tokenId): void
{
    if ($tokenId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM ' . db_table_identifier('remember_token') . ' '
        . 'WHERE remember_token_id = :token_id'
    );
    $stmt->execute([':token_id' => $tokenId]);
}
