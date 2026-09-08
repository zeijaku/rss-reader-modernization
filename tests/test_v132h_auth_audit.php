<?php

declare(strict_types=1);

const APP_DEBUG = false;
const INI_HASH_KEY = 'test-only-auth-audit-key';

function auth_normalize_email(string $email): string { return strtolower(trim($email)); }
function auth_email_is_valid(string $email): bool
{
    $email = auth_normalize_email($email);
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function auth_identity_key(string $email): string
{
    return hash_hmac('sha256', auth_normalize_email($email), INI_HASH_KEY);
}
function auth_session_registry_client_label(?string $userAgent = null): string
{
    $userAgent ??= isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $browser = preg_match('/Chrome\/([0-9]+)/i', $userAgent, $m) === 1 ? 'Chrome ' . $m[1] : 'Browser';
    $platform = str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Android') ? 'Android' : '端末');
    return substr($browser . ' / ' . $platform, 0, 120);
}
function db_table_identifier(string $name): string { return '`ig_' . $name . '`'; }

final class HAuditPDO extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $auditRows = [];
    /** @var array<int,string> */
    public array $userIdentity = [];
    public bool $failAudit = false;
    private int $nextId = 1;

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failAudit && str_contains($query, 'ig_auth_audit_log')) {
            throw new PDOException('table unavailable');
        }
        return new HAuditStatement($this, preg_replace('/\s+/', ' ', trim($query)) ?? trim($query));
    }
    public function allocateId(): int { return $this->nextId++; }
}

final class HAuditStatement extends PDOStatement
{
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private int $affected = 0;

    public function __construct(private HAuditPDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->affected = 0;

        if (str_starts_with($this->sql, 'INSERT INTO `ig_auth_audit_log`')) {
            $id = $this->pdo->allocateId();
            $this->pdo->auditRows[$id] = [
                'auth_audit_log_id' => $id,
                'auth_audit_log_user_id' => $params[':user_id'] ?? null,
                'auth_audit_log_identity_hash' => $params[':identity_hash'] ?? null,
                'auth_audit_log_event' => $params[':event'] ?? null,
                'auth_audit_log_result' => $params[':result'] ?? null,
                'auth_audit_log_method' => $params[':method'] ?? null,
                'auth_audit_log_client_label' => $params[':client_label'] ?? null,
                'auth_audit_log_ip_hash' => $params[':ip_hash'] ?? null,
                'auth_audit_log_created_at' => $params[':created_at'] ?? null,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT user_email FROM `ig_user_info`')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->userIdentity[$userId])) {
                $this->rows[] = ['user_email' => $this->pdo->userIdentity[$userId]];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_audit_log_event, auth_audit_log_result')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            $identity = $params[':identity_hash'] ?? null;
            $matches = [];
            foreach ($this->pdo->auditRows as $row) {
                if ((int) ($row['auth_audit_log_user_id'] ?? 0) === $userId
                    || (is_string($identity) && is_string($row['auth_audit_log_identity_hash'] ?? null)
                        && hash_equals($identity, (string) $row['auth_audit_log_identity_hash']))) {
                    $matches[] = $row;
                }
            }
            usort($matches, static fn(array $a, array $b): int => (int) $b['auth_audit_log_id'] <=> (int) $a['auth_audit_log_id']);
            foreach (array_slice($matches, 0, 20) as $row) {
                $this->rows[] = [
                    'auth_audit_log_event' => $row['auth_audit_log_event'],
                    'auth_audit_log_result' => $row['auth_audit_log_result'],
                    'auth_audit_log_method' => $row['auth_audit_log_method'],
                    'auth_audit_log_client_label' => $row['auth_audit_log_client_label'],
                    'auth_audit_log_created_at' => $row['auth_audit_log_created_at'],
                ];
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function rowCount(): int { return $this->affected; }
}

$GLOBALS['h_audit_pdo'] = new HAuditPDO();
function conn_db(string $type = ''): PDO { return $GLOBALS['h_audit_pdo']; }

require_once dirname(__DIR__) . '/app/auth_audit_log.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$_SERVER['REMOTE_ADDR'] = '203.0.113.55';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/152.0.0.0 Safari/537.36';
$identity = auth_identity_key('Owner@example.test');
$GLOBALS['h_audit_pdo']->userIdentity[7] = $identity;

$check(auth_audit_log_record('login', 'failure', null, $identity, 'password'), 'anonymous credential failure is recorded by keyed identity only');
$first = $GLOBALS['h_audit_pdo']->auditRows[1] ?? [];
$check(($first['auth_audit_log_identity_hash'] ?? null) === $identity, 'failed login stores the existing keyed identity');
$check(array_key_exists('auth_audit_log_user_id', $first) && $first['auth_audit_log_user_id'] === null, 'failed login does not invent an authenticated user id');
$check(($first['auth_audit_log_client_label'] ?? '') === 'Chrome 152 / Windows', 'full User-Agent is reduced to bounded Browser/platform label');
$check(preg_match('/^[a-f0-9]{64}$/', (string) ($first['auth_audit_log_ip_hash'] ?? '')) === 1, 'IP address is stored only as a keyed digest');
$check(($first['auth_audit_log_ip_hash'] ?? '') !== $_SERVER['REMOTE_ADDR'], 'raw IP address is not persisted');

$check(auth_audit_log_record('login', 'success', 7, null, 'password+totp'), 'authenticated login success is recorded');
$check(auth_audit_log_record('step_up', 'success', 7, null, 'recovery'), 'step-up method is recorded without factor contents');
$check(!auth_audit_log_record('unknown_event', 'success', 7, null, 'password'), 'unknown event names are rejected');
$check(!auth_audit_log_record('login', 'maybe', 7, null, 'password'), 'unknown result values are rejected');

$list = auth_audit_log_list(7);
$check(count($list) === 3, 'owner activity includes authenticated events and matching keyed login failures');
$check(($list[0]['event'] ?? '') === 'step_up' && ($list[2]['event'] ?? '') === 'login', 'activity is returned newest first');
$serialized = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$check(is_string($serialized) && !str_contains($serialized, $identity), 'activity response never exposes keyed login identity');
$check(is_string($serialized) && !str_contains($serialized, '203.0.113.55'), 'activity response never exposes raw IP');
$check(is_string($serialized) && !str_contains($serialized, 'AppleWebKit'), 'activity response never exposes full User-Agent');

$GLOBALS['h_audit_pdo']->failAudit = true;
$check(auth_audit_log_record('logout', 'success', 7, null, null) === false, 'audit-table outage is best-effort and does not throw into authentication flow');

exit($failed === 0 ? 0 : 1);
