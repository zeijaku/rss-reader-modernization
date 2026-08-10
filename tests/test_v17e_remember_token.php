<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/common/common_conf.php';
require_once dirname(__DIR__) . '/app/common/common_db.php';
require_once dirname(__DIR__) . '/app/remember_token.php';

final class V17eRememberStatement extends PDOStatement
{
    private array $rows = [];
    private int $position = 0;
    private int $affected = 0;

    public function __construct(private V17eRememberPDO $pdo, private string $sql)
    {
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->position = 0;
        $this->affected = 0;
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));

        if (str_starts_with($sql, 'SELECT user_id FROM `ig_user_info`')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $user = $this->pdo->users[$id] ?? null;
            if (is_array($user) && (int) ($user['user_flag'] ?? 1) === 0) {
                $this->rows[] = ['user_id' => $id];
            }
            return true;
        }

        if (str_starts_with($sql, 'INSERT INTO `ig_remember_token`')) {
            $id = $this->pdo->nextTokenId++;
            $this->pdo->tokens[$id] = [
                'remember_token_id' => $id,
                'remember_token_user_id' => (int) $params[':user_id'],
                'remember_token_selector' => (string) $params[':selector'],
                'remember_token_validator_hash' => (string) $params[':validator_hash'],
                'remember_token_created_at' => (string) $params[':created_at'],
                'remember_token_expires_at' => (string) $params[':expires_at'],
                'remember_token_last_used_at' => null,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($sql, 'SELECT rt.remember_token_id')) {
            $selector = (string) ($params[':selector'] ?? '');
            foreach ($this->pdo->tokens as $token) {
                if ($token['remember_token_selector'] !== $selector) {
                    continue;
                }
                $user = $this->pdo->users[(int) $token['remember_token_user_id']] ?? null;
                $this->rows[] = $token + ['user_flag' => is_array($user) ? $user['user_flag'] : null];
                break;
            }
            return true;
        }

        if (str_starts_with($sql, 'UPDATE `ig_remember_token`')) {
            $id = (int) ($params[':token_id'] ?? 0);
            if (isset($this->pdo->tokens[$id])
                && hash_equals($this->pdo->tokens[$id]['remember_token_validator_hash'], (string) ($params[':previous_hash'] ?? ''))) {
                $this->pdo->tokens[$id]['remember_token_validator_hash'] = (string) $params[':validator_hash'];
                $this->pdo->tokens[$id]['remember_token_last_used_at'] = (string) $params[':last_used_at'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_id')) {
            $id = (int) ($params[':token_id'] ?? 0);
            if (isset($this->pdo->tokens[$id])) {
                unset($this->pdo->tokens[$id]);
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_selector')) {
            $selector = (string) ($params[':selector'] ?? '');
            foreach (array_keys($this->pdo->tokens) as $id) {
                if ($this->pdo->tokens[$id]['remember_token_selector'] === $selector) {
                    unset($this->pdo->tokens[$id]);
                    $this->affected++;
                }
            }
            return true;
        }

        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_user_id')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            foreach (array_keys($this->pdo->tokens) as $id) {
                if ((int) $this->pdo->tokens[$id]['remember_token_user_id'] === $userId) {
                    unset($this->pdo->tokens[$id]);
                    $this->affected++;
                }
            }
            return true;
        }

        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_expires_at')) {
            $expiry = (string) ($params[':expires_at'] ?? '');
            foreach (array_keys($this->pdo->tokens) as $id) {
                if ($this->pdo->tokens[$id]['remember_token_expires_at'] <= $expiry) {
                    unset($this->pdo->tokens[$id]);
                    $this->affected++;
                }
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->position++] ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? null;
        if (!is_array($row)) {
            return false;
        }
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class V17eRememberPDO extends PDO
{
    public array $users = [
        1 => ['user_id' => 1, 'user_flag' => 0],
        2 => ['user_id' => 2, 'user_flag' => 0],
    ];
    public array $tokens = [];
    public int $nextTokenId = 1;
    private bool $transaction = false;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V17eRememberStatement($this, $query);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

$checks = [];
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = $condition;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
};

$pdo = new V17eRememberPDO();
set_db_connection_for_testing($pdo);
$base = 1786032000; // 2026-08-07 09:00:00 Asia/Tokyo

$material = remember_token_generate_material();
$check(strlen($material['selector']) === 24 && ctype_xdigit($material['selector']), 'Selector is 12 random bytes encoded as 24 hex characters');
$check(strlen($material['validator']) === 64 && ctype_xdigit($material['validator']), 'Validator is 32 random bytes encoded as 64 hex characters');
$encoded = remember_token_encode($material['selector'], $material['validator']);
$check(remember_token_parse($encoded) === $material, 'Token encoding and strict parsing round-trip');
foreach (['', 'abc', strtoupper($encoded), $encoded . '.', '../' . $encoded] as $invalid) {
    $check(remember_token_parse($invalid) === null, 'Malformed token is rejected');
}
$hash = remember_token_hash_validator($material['validator']);
$check(strlen($hash) === 64 && $hash !== $material['validator'], 'Validator is stored as a SHA-256 digest rather than raw value');
$check(remember_token_datetime_to_timestamp(remember_token_datetime($base)) === $base, 'Remember Token datetime conversion is strict and reversible');
$check(remember_token_datetime_to_timestamp('2026-02-31 00:00:00') === null, 'Invalid database datetime fails closed');

$invalidIssue = remember_token_issue(999, $base);
$check($invalidIssue === ['ok' => false, 'reason' => 'invalid_user'], 'Token is not issued for a missing or inactive user');

$issued = remember_token_issue(1, $base);
$check(($issued['ok'] ?? false) === true, 'Token is issued for an active user');
$check(($issued['expires_at'] ?? 0) === $base + REMEMBER_TOKEN_TTL_SECONDS, 'Token expiry is fixed at 30 days from issue');
$parsedIssued = remember_token_parse((string) $issued['cookie_value']);
$check(is_array($parsedIssued), 'Issued cookie value has strict selector.validator format');
$token = array_values($pdo->tokens)[0] ?? [];
$check(($token['remember_token_selector'] ?? '') === ($parsedIssued['selector'] ?? ''), 'Database stores the selector for exact lookup');
$check(($token['remember_token_validator_hash'] ?? '') === remember_token_hash_validator((string) ($parsedIssued['validator'] ?? '')), 'Database stores only the validator hash');
$check(!str_contains(json_encode($token), (string) ($parsedIssued['validator'] ?? '')), 'Raw validator is absent from the stored row');

$oldCookie = (string) $issued['cookie_value'];
$oldExpiry = (int) $issued['expires_at'];
$validated = remember_token_validate_and_rotate($oldCookie, $base + 60);
$check(($validated['ok'] ?? false) === true && ($validated['user_id'] ?? 0) === 1, 'Valid token authenticates the expected active user');
$check(($validated['cookie_value'] ?? '') !== $oldCookie, 'Successful validation rotates the validator');
$check(($validated['expires_at'] ?? 0) === $oldExpiry, 'Rotation preserves the original fixed expiry');
$newParsed = remember_token_parse((string) ($validated['cookie_value'] ?? ''));
$rotatedToken = array_values($pdo->tokens)[0] ?? [];
$check(($rotatedToken['remember_token_selector'] ?? '') === ($newParsed['selector'] ?? ''), 'Rotation keeps the selector stable');
$check(($rotatedToken['remember_token_validator_hash'] ?? '') === remember_token_hash_validator((string) ($newParsed['validator'] ?? '')), 'Rotation replaces the stored validator hash');
$check(($rotatedToken['remember_token_last_used_at'] ?? null) === remember_token_datetime($base + 60), 'Rotation records last-used time');

$replay = remember_token_validate_and_rotate($oldCookie, $base + 120);
$check(($replay['ok'] ?? true) === false && ($replay['reason'] ?? '') === 'invalid_token', 'Replayed pre-rotation validator fails closed');
$check($pdo->tokens === [], 'Wrong validator revokes the selector to contain replay or theft');

$expired = remember_token_issue(1, $base);
$expiredResult = remember_token_validate_and_rotate((string) $expired['cookie_value'], $base + REMEMBER_TOKEN_TTL_SECONDS);
$check(($expiredResult['reason'] ?? '') === 'expired' && $pdo->tokens === [], 'Expired token is rejected and deleted');

$inactive = remember_token_issue(1, $base);
$pdo->users[1]['user_flag'] = 1;
$inactiveResult = remember_token_validate_and_rotate((string) $inactive['cookie_value'], $base + 1);
$check(($inactiveResult['reason'] ?? '') === 'inactive_user' && $pdo->tokens === [], 'Token for disabled user is rejected and deleted');
$pdo->users[1]['user_flag'] = 0;

$one = remember_token_issue(1, $base);
$two = remember_token_issue(1, $base + 1);
$other = remember_token_issue(2, $base + 2);
$check(count($pdo->tokens) === 3, 'Multiple devices and users can hold independent tokens');
$check(remember_token_revoke_cookie((string) $one['cookie_value']), 'Current-device token can be revoked by cookie selector');
$check(count($pdo->tokens) === 2, 'Current-device revocation leaves unrelated tokens intact');
$check(remember_token_revoke_user(1) === 1, 'All remaining tokens for one user can be revoked');
$check(count($pdo->tokens) === 1, 'User-wide revocation leaves another user intact');
$check(remember_token_revoke_cookie('bad') === false, 'Malformed cookie is ignored without database access');

$future = remember_token_issue(1, $base + 100);
$check(count($pdo->tokens) === 2, 'Cleanup fixture contains one old and one future token');
$deleted = remember_token_cleanup_expired($base + REMEMBER_TOKEN_TTL_SECONDS + 10);
$check($deleted === 1 && count($pdo->tokens) === 1, 'Expiry cleanup removes only rows at or before cutoff');
$remaining = array_values($pdo->tokens)[0] ?? [];
$check((int) ($remaining['remember_token_user_id'] ?? 0) === 1, 'Future token remains after cleanup');

set_db_connection_for_testing(null);
$passed = count(array_filter($checks));
$failed = count($checks) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
