<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');

require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';

final class Sb14RollbackStatement extends PDOStatement
{
    private int $affected = 0;

    public function __construct(private Sb14RollbackPDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->affected = 0;

        if (str_starts_with($this->sql, 'INSERT INTO ' . DB_TABLE_PREFIX . 'user_info')) {
            $id = ++$this->pdo->sequence;
            $this->pdo->users[$id] = [
                'user_id' => $id,
                'user_email' => (string) ($params[':email'] ?? ''),
                'user_password' => (string) ($params[':password'] ?? ''),
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO ' . DB_TABLE_PREFIX . 'user_conf')) {
            if ($this->pdo->failConfInsert) {
                throw new RuntimeException('forced configuration insert failure');
            }
            $this->pdo->confs[] = [
                'user_id' => (int) ($params[':user_id'] ?? 0),
            ];
            $this->affected = 1;
            return true;
        }

        throw new RuntimeException('Unexpected SQL in SB-14 rollback fake: ' . $this->sql);
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class Sb14RollbackPDO extends PDO
{
    public array $users = [];
    public array $confs = [];
    public int $sequence = 0;
    public int $lastId = 0;
    public bool $failConfInsert = false;
    public int $commits = 0;
    public int $rollbacks = 0;

    private bool $transaction = false;
    private ?array $snapshot = null;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new Sb14RollbackStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            return false;
        }
        $this->transaction = true;
        $this->snapshot = [$this->users, $this->confs, $this->sequence, $this->lastId];
        return true;
    }

    public function commit(): bool
    {
        if (!$this->transaction) {
            return false;
        }
        $this->transaction = false;
        $this->snapshot = null;
        $this->commits++;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->transaction || $this->snapshot === null) {
            return false;
        }
        [$this->users, $this->confs, $this->sequence, $this->lastId] = $this->snapshot;
        $this->transaction = false;
        $this->snapshot = null;
        $this->rollbacks++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }
}

$tests = 0;
$failures = [];
function sb14_auth_check(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
}

$pdo = new Sb14RollbackPDO();
set_db_connection_for_testing($pdo);

$pdo->failConfInsert = true;
try {
    entry_user('identity-failure', 'hash-failure');
    sb14_auth_check(false, 'configuration insert failure propagates to caller');
} catch (Throwable $e) {
    sb14_auth_check($e->getMessage() === 'forced configuration insert failure', 'configuration insert failure propagates to caller');
}
sb14_auth_check($pdo->users === [], 'failed registration leaves no user row');
sb14_auth_check($pdo->confs === [], 'failed registration leaves no configuration row');
sb14_auth_check($pdo->rollbacks === 1, 'failed registration explicitly rolls back transaction');
sb14_auth_check(!$pdo->inTransaction(), 'failed registration does not leave transaction open');

$pdo->failConfInsert = false;
$userId = entry_user('identity-success', 'hash-success');
sb14_auth_check($userId === 1, 'successful registration returns inserted user id after rollback reset');
sb14_auth_check(count($pdo->users) === 1 && count($pdo->confs) === 1, 'successful registration commits user and configuration together');
sb14_auth_check($pdo->commits === 1, 'successful registration commits transaction');
sb14_auth_check($pdo->rollbacks === 1, 'successful path performs no extra rollback');

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d SB-14 transaction checks failed.\n", count($failures), $tests));
    exit(1);
}

echo "All {$tests} SB-14 registration transaction checks passed.\n";
