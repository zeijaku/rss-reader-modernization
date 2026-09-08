<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Method Not Allowed';
    exit;
}

$csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
if (!app_csrf_is_valid($csrfToken)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

$logoutUserId = app_session_user_id();
if ($logoutUserId !== null && function_exists('auth_audit_log_record')) {
    auth_audit_log_record('logout', 'success', $logoutUserId, null, null);
}

persistent_login_revoke_current();
app_session_logout();
app_session_start();
app_flash_set('auth_notice', 'ログアウトしました。', 'success');
header('Location: ./', true, 303);
exit;
