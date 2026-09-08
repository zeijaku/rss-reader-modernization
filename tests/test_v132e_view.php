<?php

declare(strict_types=1);

const AUTH_RECOVERY_CODE_COUNT = 10;
$GLOBALS['totp_mode'] = 'enabled';
$GLOBALS['recovery_mode'] = ['configured' => true, 'total' => 10, 'unused' => 2, 'used' => 8];

function auth_totp_status(int $userId): array
{
    if (($GLOBALS['totp_mode'] ?? '') === 'throw') {
        throw new RuntimeException('fixture');
    }
    if (($GLOBALS['totp_mode'] ?? '') === 'pending') {
        return ['configured' => true, 'enabled' => false];
    }
    if (($GLOBALS['totp_mode'] ?? '') === 'unconfigured') {
        return ['configured' => false, 'enabled' => false];
    }
    return ['configured' => true, 'enabled' => true];
}

function auth_recovery_code_status(int $userId): array
{
    if (($GLOBALS['recovery_mode'] ?? null) === 'throw') {
        throw new RuntimeException('fixture');
    }
    return is_array($GLOBALS['recovery_mode'] ?? null)
        ? $GLOBALS['recovery_mode']
        : ['configured' => false, 'total' => 0, 'unused' => 0, 'used' => 0];
}

function app_html(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require_once dirname(__DIR__) . '/app/view/account_security.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

$state = account_security_view_state(1, 'Test');
$check($state['totp_state'] === 'enabled', 'enabled TOTP state is detected');
$check($state['recovery_remaining'] === 2 && $state['recovery_configured'] === true, 'Recovery Code remaining count is loaded');
ob_start();
account_security_render($state, 'testSecurity');
$html = (string) ob_get_clean();
$check(str_contains($html, '残り2 / 10個'), 'low Recovery Code count is visible');
$check(str_contains($html, '残りが少なくなっています'), 'low Recovery Code state shows a warning');
$check(str_contains($html, 'data-account-recovery-manage open'), 'low Recovery Code management section opens automatically');
$check(str_contains($html, 'data-account-totp-badge') && str_contains($html, '>有効</span>'), 'enabled 2FA badge is rendered');

$GLOBALS['recovery_mode'] = ['configured' => true, 'total' => 10, 'unused' => 0, 'used' => 10];
$state = account_security_view_state(1, 'Test');
ob_start();
account_security_render($state, 'testSecurity');
$html = (string) ob_get_clean();
$check(str_contains($html, '残り0 / 10個'), 'exhausted Recovery Code state is visible');
$check(str_contains($html, '使用可能なRecovery Codeがありません'), 'exhausted state asks the user to regenerate while Authenticator works');

$GLOBALS['totp_mode'] = 'unconfigured';
$GLOBALS['recovery_mode'] = ['configured' => false, 'total' => 0, 'unused' => 0, 'used' => 0];
$state = account_security_view_state(1, 'Test');
$check($state['totp_state'] === 'unconfigured', 'unconfigured TOTP state is detected');
ob_start();
account_security_render($state, 'testSecurity');
$html = (string) ob_get_clean();
$check(str_contains($html, '2FAを設定する'), 'unconfigured state offers setup');
$check(str_contains($html, 'data-account-recovery-codes') && str_contains($html, 'data-account-recovery-codes data-account-recovery-remaining="0" hidden'), 'Recovery Code panel stays hidden until 2FA is enabled');

$GLOBALS['totp_mode'] = 'throw';
$state = account_security_view_state(1, 'Test');
$check($state['totp_available'] === false && $state['recovery_available'] === false, 'TOTP load failure fails closed for the Security status UI');

exit($failed === 0 ? 0 : 1);
