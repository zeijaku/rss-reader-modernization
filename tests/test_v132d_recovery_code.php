<?php

declare(strict_types=1);

final class DRecoveryPDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    private int $nextId = 1;
    private bool $tx = false;
    private array $snapshot = [];

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new DRecoveryStatement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = $this->rows; $this->tx = true; return true; }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool { $this->rows = $this->snapshot; $this->tx = false; return true; }
    public function inTransaction(): bool { return $this->tx; }
    public function nextId(): int { return $this->nextId++; }
}

final class DRecoveryStatement extends PDOStatement
{
    private array $fetchedRows = [];
    private int $affected = 0;
    private string $sql;

    public function __construct(private DRecoveryPDO $pdo, string $sql)
    {
        $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->fetchedRows = [];
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT COUNT(*) AS total_count')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            $total = 0;
            $unused = 0;
            foreach ($this->pdo->rows as $row) {
                if ((int) $row['auth_recovery_code_user_id'] !== $userId) { continue; }
                $total++;
                if ($row['auth_recovery_code_used_at'] === null) { $unused++; }
            }
            $this->fetchedRows[] = ['total_count' => $total, 'unused_count' => $unused];
            return true;
        }

        if (str_starts_with($this->sql, 'DELETE FROM ig_auth_recovery_code')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            foreach (array_keys($this->pdo->rows) as $id) {
                if ((int) $this->pdo->rows[$id]['auth_recovery_code_user_id'] === $userId) {
                    unset($this->pdo->rows[$id]);
                    $this->affected++;
                }
            }
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO ig_auth_recovery_code')) {
            $id = $this->pdo->nextId();
            $this->pdo->rows[$id] = [
                'auth_recovery_code_id' => $id,
                'auth_recovery_code_user_id' => (int) ($params[':user_id'] ?? 0),
                'auth_recovery_code_hash' => (string) ($params[':code_hash'] ?? ''),
                'auth_recovery_code_created_at' => (string) ($params[':created_at'] ?? ''),
                'auth_recovery_code_used_at' => null,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_auth_recovery_code SET auth_recovery_code_used_at')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            $hash = (string) ($params[':code_hash'] ?? '');
            foreach ($this->pdo->rows as &$row) {
                if ((int) $row['auth_recovery_code_user_id'] === $userId
                    && hash_equals((string) $row['auth_recovery_code_hash'], $hash)
                    && $row['auth_recovery_code_used_at'] === null) {
                    $row['auth_recovery_code_used_at'] = (string) ($params[':used_at'] ?? '');
                    $this->affected = 1;
                    break;
                }
            }
            unset($row);
            return true;
        }

        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->fetchedRows) ?? false;
    }

    public function rowCount(): int { return $this->affected; }
}

$GLOBALS['d_recovery_pdo'] = new DRecoveryPDO();
function conn_db(string $type = ''): PDO { return $GLOBALS['d_recovery_pdo']; }
function db_table_name(string $name): string { return 'ig_' . $name; }
function app_now(): string { return '2026-09-06 19:30:00'; }
function auth_totp_status(int $userId): array { return ['configured' => true, 'enabled' => $userId > 0]; }

require_once dirname(__DIR__) . '/app/auth_recovery_code.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$empty = auth_recovery_code_status(1);
$check($empty['configured'] === false && $empty['unused'] === 0, 'new account starts without Recovery Codes');

$result = auth_recovery_code_replace(1);
$codes = is_array($result['codes'] ?? null) ? $result['codes'] : [];
$check(($result['ok'] ?? false) === true && count($codes) === 10, 'generation creates exactly ten Recovery Codes');
$check(count(array_unique($codes)) === 10, 'generated Recovery Codes are unique');
$check(array_reduce($codes, static fn(bool $ok, mixed $code): bool => $ok && is_string($code) && preg_match('/\A[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}\z/D', $code) === 1, true), 'Recovery Codes use the bounded human-readable format');

$rows = array_values($GLOBALS['d_recovery_pdo']->rows);
$check(count($rows) === 10, 'database receives ten hash rows');
$hashOnly = true;
foreach ($rows as $row) {
    $hash = (string) $row['auth_recovery_code_hash'];
    if (preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1) { $hashOnly = false; }
    foreach ($codes as $code) {
        $normalized = auth_recovery_code_normalize((string) $code);
        if ($normalized !== null && (str_contains($hash, $normalized) || str_contains(json_encode($row), (string) $code))) {
            $hashOnly = false;
        }
    }
}
$check($hashOnly, 'database rows contain hashes only and no plaintext Recovery Code');

$status = auth_recovery_code_status(1);
$check($status['configured'] === true && $status['total'] === 10 && $status['unused'] === 10 && $status['used'] === 0, 'status reports ten unused codes after generation');

$first = (string) $codes[0];
$check(auth_recovery_code_consume(1, strtolower(str_replace('-', ' ', $first))) === true, 'normalized Recovery Code can be consumed once');
$check(auth_recovery_code_consume(1, $first) === false, 'the same Recovery Code cannot be reused');
$status = auth_recovery_code_status(1);
$check($status['unused'] === 9 && $status['used'] === 1, 'status decrements after one successful use');
$check(auth_recovery_code_consume(1, 'INVALID-CODE') === false, 'malformed Recovery Code is rejected');

$oldUnused = (string) $codes[1];
$replacement = auth_recovery_code_replace(1);
$newCodes = is_array($replacement['codes'] ?? null) ? $replacement['codes'] : [];
$check(($replacement['ok'] ?? false) === true && count($newCodes) === 10, 'regeneration creates a replacement set');
$check(auth_recovery_code_consume(1, $oldUnused) === false, 'regeneration invalidates every old Recovery Code');
$check(auth_recovery_code_status(1)['unused'] === 10, 'replacement set resets the unused count to ten');

$normalized = auth_recovery_code_normalize((string) $newCodes[0]);
$check(is_string($normalized) && !hash_equals(auth_recovery_code_hash(1, $normalized), auth_recovery_code_hash(2, $normalized)), 'Recovery Code hashes are bound to the user ID');

exit($failed === 0 ? 0 : 1);
