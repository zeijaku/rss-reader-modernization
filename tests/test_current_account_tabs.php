<?php
declare(strict_types=1);
function app_html(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
require_once dirname(__DIR__) . '/app/view/account_security.php';
$passed = 0;
function check(bool $ok, string $name): void { global $passed; if (!$ok) { throw new RuntimeException($name); } $passed++; echo 'PASS: ' . $name . PHP_EOL; }
foreach (['unconfigured', 'pending', 'enabled', 'unavailable'] as $mode) {
    $state = ['totp_available' => $mode !== 'unavailable', 'totp_state' => $mode, 'sessions_available' => true, 'sessions' => [], 'audit_available' => true, 'audit_events' => []];
    ob_start(); account_security_render($state); $legacy = (string) ob_get_clean();
    ob_start(); account_security_render($state, 'accountSecurityTitle', false); $security = (string) ob_get_clean();
    ob_start(); account_security_activity_render($state); $activity = (string) ob_get_clean();
    check(str_contains($legacy, 'data-account-security-activity'), "default shared view retains activity: $mode");
    check(!str_contains($security, 'data-account-security-activity'), "split security excludes activity: $mode");
    check(str_contains($security, 'data-account-session-management'), "session controls stay in security: $mode");
    check(str_contains($activity, '記録されたSecurity Activityはまだありません'), "empty activity remains readable: $mode");
    check(!str_contains($activity, 'data-account-totp-status'), "activity has no duplicate security container: $mode");
}
$state = ['audit_available' => true, 'audit_events' => [['event' => 'login', 'result' => 'success', 'client_label' => '<img src=x onerror=alert(1)>', 'created_at' => '<script>bad</script>']]];
ob_start(); account_security_activity_render($state); $html = (string) ob_get_clean();
check(str_contains($html, '&lt;img') && !str_contains($html, '<img'), 'client label remains escaped');
check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'audit timestamp remains escaped');
check(substr_count($html, 'data-account-security-activity-row') === 1, 'activity rows render once');
ob_start(); account_security_activity_render(['audit_available' => false]); $html = (string) ob_get_clean();
check(str_contains($html, 'role="alert"') && str_contains($html, '読み込めませんでした'), 'unavailable audit retains warning');
foreach (['app/view/dashboard_modals.php', 'public/settings.php'] as $file) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $file);
    foreach (['Basic', 'Security', 'Activity'] as $tab) {
        check(substr_count($src, 'id="account' . $tab . 'Tab"') === 1 && substr_count($src, 'id="account' . $tab . 'Pane"') === 1, "unique tab/panel pair: $file/$tab");
    }
    check(str_contains($src, 'modal-dialog-centered modal-dialog-scrollable'), "account modal scrolls content: $file");
}
echo "RESULT: PASS $passed / FAIL 0 / SKIP 0\n";
