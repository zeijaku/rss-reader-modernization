<?php

declare(strict_types=1);

const AUTH_PASSWORD_MAX_LENGTH = 72;
$GLOBALS['user'] = [
    'user_id' => 1,
    'user_password' => password_hash('correct-password', PASSWORD_DEFAULT),
];
$GLOBALS['dummy_calls'] = 0;

function conn_db(string $type = ''): PDO { return new class extends PDO { public function __construct() {} }; }
function account_settings_current_password_is_valid(string $password): bool { return $password !== '' && strlen($password) <= AUTH_PASSWORD_MAX_LENGTH && !str_contains($password, "\0"); }
function account_settings_find_active_user(PDO $conn, int $userId, bool $lock): ?array { return $userId === 1 ? $GLOBALS['user'] : null; }
function auth_dummy_password_verify(string $password): void { $GLOBALS['dummy_calls']++; password_verify($password, password_hash('dummy-password', PASSWORD_DEFAULT)); }
function auth_is_password_hash(string $hash): bool { return password_get_info($hash)['algo'] !== null; }

require_once dirname(__DIR__) . '/app/auth_step_up.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$check(auth_step_up_verify_password(1, 'correct-password') === true, 'Step-up Password verifier accepts the current Password hash');
$check(auth_step_up_verify_password(1, 'wrong-password') === false, 'Step-up Password verifier rejects a wrong Password');
$before = $GLOBALS['dummy_calls'];
$check(auth_step_up_verify_password(999, 'any-password') === false, 'Step-up Password verifier rejects missing/inactive accounts');
$check($GLOBALS['dummy_calls'] === $before + 1, 'missing account performs dummy Password verification to reduce timing disclosure');
$check(auth_step_up_verify_password(1, '') === false, 'empty Password is rejected');

exit($failed === 0 ? 0 : 1);
