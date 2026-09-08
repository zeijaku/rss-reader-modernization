<?php

declare(strict_types=1);

const AUTH_RECOVERY_CODE_COUNT = 10;
const AUTH_RECOVERY_CODE_LENGTH = 16;
const AUTH_RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/** Normalize a user-entered Recovery Code to its 16-character canonical value. */
function auth_recovery_code_normalize(string $code): ?string
{
    $code = strtoupper(trim($code));
    if ($code === '' || strlen($code) > 32 || preg_match('/\A[A-HJ-NP-Z2-9 -]+\z/D', $code) !== 1) {
        return null;
    }

    $code = str_replace(['-', ' '], '', $code);
    if (strlen($code) !== AUTH_RECOVERY_CODE_LENGTH
        || preg_match('/\A[A-HJ-NP-Z2-9]{16}\z/D', $code) !== 1) {
        return null;
    }

    return $code;
}

function auth_recovery_code_format(string $normalized): string
{
    if (preg_match('/\A[A-HJ-NP-Z2-9]{16}\z/D', $normalized) !== 1) {
        throw new InvalidArgumentException('Recovery Code is invalid.');
    }
    return implode('-', str_split($normalized, 4));
}

function auth_recovery_code_generate_one(): string
{
    $alphabet = AUTH_RECOVERY_CODE_ALPHABET;
    $bytes = random_bytes(AUTH_RECOVERY_CODE_LENGTH);
    $code = '';
    for ($i = 0; $i < AUTH_RECOVERY_CODE_LENGTH; $i++) {
        // 256 is exactly divisible by the 32-character alphabet, so this has no modulo bias.
        $code .= $alphabet[ord($bytes[$i]) & 31];
    }
    return $code;
}

function auth_recovery_code_hash(int $userId, string $normalized): string
{
    if ($userId <= 0 || preg_match('/\A[A-HJ-NP-Z2-9]{16}\z/D', $normalized) !== 1) {
        throw new InvalidArgumentException('Recovery Code hash input is invalid.');
    }

    // Codes contain 80 bits of random entropy. Binding the deterministic hash to
    // the user prevents identical codes for different users from sharing a DB hash.
    return hash('sha256', 'rss-reader:recovery-code:v1:' . $userId . ':' . $normalized);
}

/** @return array{configured:bool,total:int,unused:int,used:int} */
function auth_recovery_code_status(int $userId): array
{
    if ($userId <= 0) {
        return ['configured' => false, 'total' => 0, 'unused' => 0, 'used' => 0];
    }

    $stmt = conn_db()->prepare(
        'SELECT COUNT(*) AS total_count, '
        . 'SUM(CASE WHEN auth_recovery_code_used_at IS NULL THEN 1 ELSE 0 END) AS unused_count '
        . 'FROM ' . db_table_name('auth_recovery_code') . ' WHERE auth_recovery_code_user_id = :user_id'
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = is_array($row) ? max(0, (int) ($row['total_count'] ?? 0)) : 0;
    $unused = is_array($row) ? max(0, (int) ($row['unused_count'] ?? 0)) : 0;
    $unused = min($unused, $total);

    return [
        'configured' => $total > 0,
        'total' => $total,
        'unused' => $unused,
        'used' => max(0, $total - $unused),
    ];
}

/**
 * Replace all existing Recovery Codes for an enabled 2FA account.
 * Plaintext codes exist only in this return value; only hashes are inserted.
 *
 * @return array{ok:bool,reason?:string,codes?:array<int,string>}
 */
function auth_recovery_code_replace(int $userId, int $count = AUTH_RECOVERY_CODE_COUNT): array
{
    if ($userId <= 0 || $count < 1 || $count > AUTH_RECOVERY_CODE_COUNT) {
        return ['ok' => false, 'reason' => 'invalid_request'];
    }

    $totpStatus = auth_totp_status($userId);
    if (($totpStatus['enabled'] ?? false) !== true) {
        return ['ok' => false, 'reason' => 'totp_not_enabled'];
    }

    $codes = [];
    $hashes = [];
    while (count($codes) < $count) {
        $normalized = auth_recovery_code_generate_one();
        $hash = auth_recovery_code_hash($userId, $normalized);
        if (isset($hashes[$hash])) {
            continue;
        }
        $hashes[$hash] = true;
        $codes[] = auth_recovery_code_format($normalized);
    }

    $pdo = conn_db();
    try {
        $pdo->beginTransaction();

        $delete = $pdo->prepare(
            'DELETE FROM ' . db_table_name('auth_recovery_code') . ' WHERE auth_recovery_code_user_id = :user_id'
        );
        $delete->execute([':user_id' => $userId]);

        $insert = $pdo->prepare(
            'INSERT INTO ' . db_table_name('auth_recovery_code') . ' '
            . '(auth_recovery_code_user_id, auth_recovery_code_hash, auth_recovery_code_created_at, auth_recovery_code_used_at) '
            . 'VALUES (:user_id, :code_hash, :created_at, NULL)'
        );
        $createdAt = app_now();
        foreach ($codes as $formattedCode) {
            $normalized = auth_recovery_code_normalize($formattedCode);
            if ($normalized === null) {
                throw new RuntimeException('Generated Recovery Code is invalid.');
            }
            $insert->execute([
                ':user_id' => $userId,
                ':code_hash' => auth_recovery_code_hash($userId, $normalized),
                ':created_at' => $createdAt,
            ]);
        }

        $pdo->commit();
        return ['ok' => true, 'codes' => $codes];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** Atomically consume one unused Recovery Code. */
function auth_recovery_code_consume(int $userId, string $code): bool
{
    if ($userId <= 0) {
        return false;
    }

    $normalized = auth_recovery_code_normalize($code);
    if ($normalized === null) {
        return false;
    }

    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('auth_recovery_code') . ' SET auth_recovery_code_used_at = :used_at '
        . 'WHERE auth_recovery_code_user_id = :user_id AND auth_recovery_code_hash = :code_hash '
        . 'AND auth_recovery_code_used_at IS NULL'
    );
    $stmt->execute([
        ':used_at' => app_now(),
        ':user_id' => $userId,
        ':code_hash' => auth_recovery_code_hash($userId, $normalized),
    ]);

    return $stmt->rowCount() === 1;
}
