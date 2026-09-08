<?php

declare(strict_types=1);

const SESSION_IDLE_TIMEOUT = 7200;
const SESSION_ABSOLUTE_TIMEOUT = 43200;

final class GSessionPDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];
    /** @var array<string,int> selector => user id */
    public array $remember = [];
    private int $nextId = 1;
    private int $lastId = 0;
    private bool $tx = false;
    private array $snapshot = [];

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new GSessionStatement($this, preg_replace('/\s+/', ' ', trim($query)) ?? trim($query));
    }
    public function beginTransaction(): bool
    {
        $this->snapshot = [$this->rows, $this->remember, $this->nextId, $this->lastId];
        $this->tx = true;
        return true;
    }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool
    {
        [$this->rows, $this->remember, $this->nextId, $this->lastId] = $this->snapshot;
        $this->tx = false;
        return true;
    }
    public function inTransaction(): bool { return $this->tx; }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
    public function allocateId(): int { $this->lastId = $this->nextId++; return $this->lastId; }
}

final class GSessionStatement extends PDOStatement
{
    /** @var list<array<string,mixed>> */
    private array $fetchedRows = [];
    private int $affected = 0;

    public function __construct(private GSessionPDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->fetchedRows = [];
        $this->affected = 0;

        if (str_starts_with($this->sql, 'INSERT INTO `ig_auth_session`')) {
            $id = $this->pdo->allocateId();
            $this->pdo->rows[$id] = [
                'auth_session_id' => $id,
                'auth_session_user_id' => (int) ($params[':user_id'] ?? 0),
                'auth_session_token_hash' => (string) ($params[':token_hash'] ?? ''),
                'auth_session_remember_selector' => null,
                'auth_session_client_label' => (string) ($params[':client_label'] ?? ''),
                'auth_session_created_at' => (string) ($params[':created_at'] ?? ''),
                'auth_session_last_seen_at' => (string) ($params[':last_seen_at'] ?? ''),
                'auth_session_expires_at' => (string) ($params[':expires_at'] ?? ''),
                'auth_session_revoked_at' => null,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_session_id, auth_session_expires_at, auth_session_revoked_at FROM `ig_auth_session`')) {
            foreach ($this->pdo->rows as $row) {
                if ((int) $row['auth_session_user_id'] === (int) ($params[':user_id'] ?? 0)
                    && hash_equals((string) $row['auth_session_token_hash'], (string) ($params[':token_hash'] ?? ''))) {
                    $this->fetchedRows[] = [
                        'auth_session_id' => $row['auth_session_id'],
                        'auth_session_expires_at' => $row['auth_session_expires_at'],
                        'auth_session_revoked_at' => $row['auth_session_revoked_at'],
                    ];
                    break;
                }
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_auth_session` SET auth_session_last_seen_at')) {
            $id = (int) ($params[':session_id'] ?? 0);
            if (isset($this->pdo->rows[$id])) {
                $row =& $this->pdo->rows[$id];
                if ((int) $row['auth_session_user_id'] === (int) ($params[':user_id'] ?? 0)
                    && $row['auth_session_revoked_at'] === null
                    && hash_equals((string) $row['auth_session_token_hash'], (string) ($params[':token_hash'] ?? ''))) {
                    $row['auth_session_last_seen_at'] = (string) ($params[':last_seen_at'] ?? '');
                    $row['auth_session_expires_at'] = (string) ($params[':expires_at'] ?? '');
                    $row['auth_session_client_label'] = (string) ($params[':client_label'] ?? '');
                    $this->affected = 1;
                }
                unset($row);
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_auth_session` SET auth_session_remember_selector = NULL')) {
            foreach ($this->pdo->rows as &$row) {
                if ((int) $row['auth_session_user_id'] === (int) ($params[':user_id'] ?? 0)
                    && (string) ($row['auth_session_remember_selector'] ?? '') === (string) ($params[':selector'] ?? '')
                    && !hash_equals((string) $row['auth_session_token_hash'], (string) ($params[':token_hash'] ?? ''))) {
                    $row['auth_session_remember_selector'] = null;
                    $this->affected++;
                }
            }
            unset($row);
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_auth_session` SET auth_session_remember_selector = :selector')) {
            foreach ($this->pdo->rows as &$row) {
                if ((int) $row['auth_session_user_id'] === (int) ($params[':user_id'] ?? 0)
                    && $row['auth_session_revoked_at'] === null
                    && hash_equals((string) $row['auth_session_token_hash'], (string) ($params[':token_hash'] ?? ''))) {
                    $row['auth_session_remember_selector'] = (string) ($params[':selector'] ?? '');
                    $this->affected = 1;
                    break;
                }
            }
            unset($row);
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_session_id, auth_session_token_hash, auth_session_remember_selector, auth_session_client_label,')) {
            $now = (string) ($params[':now'] ?? '');
            foreach ($this->pdo->rows as $row) {
                if ((int) $row['auth_session_user_id'] !== (int) ($params[':user_id'] ?? 0)
                    || $row['auth_session_revoked_at'] !== null
                    || strcmp((string) $row['auth_session_expires_at'], $now) <= 0) {
                    continue;
                }
                $this->fetchedRows[] = $row;
            }
            usort($this->fetchedRows, static fn(array $a, array $b): int =>
                [$b['auth_session_last_seen_at'], $b['auth_session_id']] <=> [$a['auth_session_last_seen_at'], $a['auth_session_id']]
            );
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_session_token_hash, auth_session_remember_selector FROM `ig_auth_session`')) {
            $id = (int) ($params[':session_id'] ?? 0);
            $row = $this->pdo->rows[$id] ?? null;
            if (is_array($row)
                && (int) $row['auth_session_user_id'] === (int) ($params[':user_id'] ?? 0)
                && $row['auth_session_revoked_at'] === null) {
                $this->fetchedRows[] = [
                    'auth_session_token_hash' => $row['auth_session_token_hash'],
                    'auth_session_remember_selector' => $row['auth_session_remember_selector'],
                ];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_session_remember_selector FROM `ig_auth_session`')) {
            foreach ($this->pdo->rows as $row) {
                if ((int) $row['auth_session_user_id'] !== (int) ($params[':user_id'] ?? 0)
                    || $row['auth_session_revoked_at'] !== null) {
                    continue;
                }
                if (isset($params[':token_hash'])
                    && !hash_equals((string) $row['auth_session_token_hash'], (string) $params[':token_hash'])) {
                    continue;
                }
                if (isset($params[':except_token_hash'])
                    && hash_equals((string) $row['auth_session_token_hash'], (string) $params[':except_token_hash'])) {
                    continue;
                }
                $this->fetchedRows[] = ['auth_session_remember_selector' => $row['auth_session_remember_selector']];
                if (isset($params[':token_hash'])) { break; }
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE `ig_auth_session` SET auth_session_revoked_at = :revoked_at')) {
            foreach ($this->pdo->rows as &$row) {
                if ((int) $row['auth_session_user_id'] !== (int) ($params[':user_id'] ?? 0)
                    || $row['auth_session_revoked_at'] !== null) {
                    continue;
                }
                if (isset($params[':session_id']) && (int) $row['auth_session_id'] !== (int) $params[':session_id']) {
                    continue;
                }
                if (isset($params[':token_hash'])
                    && !hash_equals((string) $row['auth_session_token_hash'], (string) $params[':token_hash'])) {
                    continue;
                }
                if (isset($params[':except_token_hash'])
                    && hash_equals((string) $row['auth_session_token_hash'], (string) $params[':except_token_hash'])) {
                    continue;
                }
                $row['auth_session_revoked_at'] = (string) ($params[':revoked_at'] ?? '');
                $this->affected++;
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
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static fn(array $row): mixed => array_values($row)[0] ?? null, $this->fetchedRows);
        }
        return $this->fetchedRows;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->fetchedRows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }
    public function rowCount(): int { return $this->affected; }
}

$GLOBALS['g_pdo'] = new GSessionPDO();
$GLOBALS['g_state'] = ['user_id' => 1, 'authenticated_at' => time(), 'token' => null, 'id' => null, 'touch' => 0];
function conn_db(string $type = ''): PDO { return $GLOBALS['g_pdo']; }
function db_table_identifier(string $name): string { return '`ig_' . $name . '`'; }
function app_session_user_id(): ?int { return (int) $GLOBALS['g_state']['user_id']; }
function app_session_authenticated_at(): ?int { return (int) $GLOBALS['g_state']['authenticated_at']; }
function app_session_registry_token(): ?string { return $GLOBALS['g_state']['token']; }
function app_session_registry_id(): ?int { return $GLOBALS['g_state']['id']; }
function app_session_registry_last_touch_at(): int { return (int) $GLOBALS['g_state']['touch']; }
function app_session_registry_store(string $token, int $sessionId, int $lastTouchAt): void
{
    $GLOBALS['g_state']['token'] = $token;
    $GLOBALS['g_state']['id'] = $sessionId;
    $GLOBALS['g_state']['touch'] = $lastTouchAt;
}
function app_session_registry_set_last_touch_at(int $timestamp): void { $GLOBALS['g_state']['touch'] = $timestamp; }
$GLOBALS['g_persistent_cookie'] = null;
function persistent_login_cookie_value(): ?string { return $GLOBALS['g_persistent_cookie']; }
function remember_token_parse(string $cookieValue): ?array
{
    if (preg_match('/\A([a-f0-9]{24})\.([a-f0-9]{64})\z/D', $cookieValue, $matches) !== 1) {
        return null;
    }
    return ['selector' => $matches[1], 'validator' => $matches[2]];
}
function remember_token_revoke_selector_for_user(int $userId, string $selector, ?PDO $pdo = null): int
{
    if (($GLOBALS['g_pdo']->remember[$selector] ?? 0) !== $userId) { return 0; }
    unset($GLOBALS['g_pdo']->remember[$selector]);
    return 1;
}
function remember_token_revoke_user_except_selector(int $userId, ?string $exceptSelector, ?PDO $pdo = null): int
{
    $count = 0;
    foreach (array_keys($GLOBALS['g_pdo']->remember) as $selector) {
        if ($GLOBALS['g_pdo']->remember[$selector] === $userId && $selector !== $exceptSelector) {
            unset($GLOBALS['g_pdo']->remember[$selector]);
            $count++;
        }
    }
    return $count;
}

require_once dirname(__DIR__) . '/app/auth_session_registry.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

session_start();
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152.0.0.0 Safari/537.36';
$firstId = auth_session_registry_register_current(1);
$firstState = $GLOBALS['g_state'];
$firstToken = (string) $firstState['token'];
$firstHash = auth_session_registry_hash_token(1, $firstToken);
$row = $GLOBALS['g_pdo']->rows[$firstId] ?? [];
$check($firstId > 0, 'current authenticated session is registered');
$check(preg_match('/^[a-f0-9]{64}$/', $firstToken) === 1, 'registry token is 32 random bytes encoded as hex');
$check(($row['auth_session_token_hash'] ?? '') === $firstHash && $firstHash !== $firstToken, 'DB row stores only the user-bound token hash');
$check(($row['auth_session_client_label'] ?? '') === 'Chrome 152 / Windows', 'Registry stores a privacy-bounded Browser/platform label');

$selector1 = 'aaaaaaaaaaaaaaaaaaaaaaaa';
$GLOBALS['g_pdo']->remember[$selector1] = 1;
$check(auth_session_registry_bind_remember_selector(1, $selector1), 'current Remember selector binds to the logical session');
$list = auth_session_registry_list(1);
$check(count($list) === 1 && $list[0]['is_current'] === true && $list[0]['remembered'] === true, 'list marks current and Remember state without exposing selector');

$GLOBALS['g_state'] = ['user_id' => 1, 'authenticated_at' => time(), 'token' => null, 'id' => null, 'touch' => 0];
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile/15E148 Safari/604.1';
$secondId = auth_session_registry_register_current(1);
$secondToken = (string) $GLOBALS['g_state']['token'];
$selector2 = 'bbbbbbbbbbbbbbbbbbbbbbbb';
$GLOBALS['g_pdo']->remember[$selector2] = 1;
auth_session_registry_bind_remember_selector(1, $selector2);
$GLOBALS['g_state'] = $firstState;
$check(count(auth_session_registry_list(1)) === 2, 'two Browser sessions are listed independently');
$check((auth_session_registry_revoke_one(1, $secondId)['ok'] ?? false) === true, 'current Browser can revoke one other session');
$check(($GLOBALS['g_pdo']->rows[$secondId]['auth_session_revoked_at'] ?? null) !== null, 'individual revoke records revoked_at');
$check(!isset($GLOBALS['g_pdo']->remember[$selector2]), 'individual revoke invalidates the bound Remember token');
$check((auth_session_registry_revoke_one(1, $firstId)['reason'] ?? '') === 'current_session', 'remote revoke refuses the current Browser');

$GLOBALS['g_state'] = ['user_id' => 1, 'authenticated_at' => time(), 'token' => $secondToken, 'id' => $secondId, 'touch' => 0];
$validation = auth_session_registry_validate_current(1);
$check(($validation['ok'] ?? true) === false && ($validation['reason'] ?? '') === 'revoked', 'revoked Browser fails Registry validation on its next request');

$GLOBALS['g_state'] = ['user_id' => 1, 'authenticated_at' => time(), 'token' => null, 'id' => null, 'touch' => 0];
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/141.0';
$thirdId = auth_session_registry_register_current(1);
$selector3 = 'cccccccccccccccccccccccc';
$GLOBALS['g_pdo']->remember[$selector3] = 1;
auth_session_registry_bind_remember_selector(1, $selector3);
$GLOBALS['g_pdo']->remember['dddddddddddddddddddddddd'] = 1; // dormant Remember token, no active Session row.
$GLOBALS['g_state'] = $firstState;
$count = auth_session_registry_revoke_others(1);
$check($count >= 1 && ($GLOBALS['g_pdo']->rows[$thirdId]['auth_session_revoked_at'] ?? null) !== null, 'revoke-others invalidates every other active Session row');
$check(array_keys($GLOBALS['g_pdo']->remember) === [$selector1], 'revoke-others also removes dormant/other Remember tokens while preserving current selector');

$check(auth_session_registry_client_label('Mozilla/5.0 (Linux; Android 16) AppleWebKit/537.36 Chrome/152.0 Mobile Safari/537.36') === 'Chrome 152 / Android', 'Android client label is bounded and readable');
$check(auth_session_registry_client_label('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/18.0 Safari/605.1.15') === 'Safari 18 / macOS', 'macOS Safari client label is bounded and readable');

// Deployment compatibility: an already-authenticated pre-G Session is adopted
// without forced Logout, and its current Remember selector is associated with
// the newly-created logical Session row.
$adoptSelector = 'eeeeeeeeeeeeeeeeeeeeeeee';
$GLOBALS['g_persistent_cookie'] = $adoptSelector . '.' . str_repeat('f', 64);
$GLOBALS['g_state'] = ['user_id' => 1, 'authenticated_at' => time(), 'token' => null, 'id' => null, 'touch' => 0];
$adopted = auth_session_registry_validate_current(1);
$adoptedId = (int) ($adopted['session_id'] ?? 0);
$check(($adopted['ok'] ?? false) === true && ($adopted['reason'] ?? '') === 'adopted' && $adoptedId > 0, 'pre-G authenticated Session is adopted instead of being forced out');
$check(($GLOBALS['g_pdo']->rows[$adoptedId]['auth_session_remember_selector'] ?? null) === $adoptSelector, 'adopted Session preserves the current Remember selector without storing its validator');
$GLOBALS['g_persistent_cookie'] = null;

session_write_close();
exit($failed === 0 ? 0 : 1);
