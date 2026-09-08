<?php

declare(strict_types=1);

/** @return array{status:int,body:array<string,mixed>} */
function api_account_session_revoke(int $userId, array $input): array
{
    $sessionId = isset($input['session_id']) && (is_string($input['session_id']) || is_int($input['session_id']))
        ? (int) $input['session_id']
        : 0;
    if ($sessionId <= 0) {
        return api_validation_error('Sessionを確認してください。');
    }

    try {
        $result = auth_session_registry_revoke_one($userId, $sessionId);
        if (($result['ok'] ?? false) === true) {
            auth_audit_log_record('session_revoke', 'success', $userId, null, 'single');
            return api_success(['session_id' => $sessionId]);
        }

        return match ((string) ($result['reason'] ?? '')) {
            'current_session' => api_error('current_session', '現在使用中のSessionはこの画面からLogout出来ません。通常のLogoutを使用してください。', 409),
            'current_unknown' => api_error('session_unavailable', '現在のSessionを確認出来ませんでした。', 409),
            default => api_error('session_not_found', '対象Sessionは既にLogout済みか、有効期限が切れています。', 404),
        };
    } catch (Throwable $exception) {
        error_log('Account Session revoke failed: ' . $exception::class);
        return api_error('session_revoke_failed', 'SessionをLogout出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_session_revoke_others(int $userId): array
{
    try {
        $count = auth_session_registry_revoke_others($userId);
        auth_audit_log_record('session_revoke_others', 'success', $userId, null, 'others');
        return api_success(['revoked' => max(0, $count)]);
    } catch (Throwable $exception) {
        error_log('Account Session revoke others failed: ' . $exception::class);
        return api_error('session_revoke_failed', '他のSessionをLogout出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_session_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'account.session.revoke' => api_account_session_revoke($userId, $input),
        'account.session.revoke_others' => api_account_session_revoke_others($userId),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
