<?php

declare(strict_types=1);

/** @return array{status:int,body:array<string,mixed>} */
function api_account_totp_begin(int $userId): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }

    try {
        $status = auth_totp_status($userId);
        if (($status['enabled'] ?? false) === true) {
            return api_error('totp_already_enabled', '2段階認証は既に有効です。', 409);
        }
        if (($status['configured'] ?? false) === true) {
            return api_error('totp_enrollment_pending', '2段階認証の設定は既に開始されています。', 409);
        }

        $result = auth_totp_begin_enrollment($userId);
        if (($result['ok'] ?? false) !== true) {
            $reason = (string) ($result['reason'] ?? '');
            if ($reason === 'already_enabled') {
                return api_error('totp_already_enabled', '2段階認証は既に有効です。', 409);
            }
            if ($reason === 'invalid_user') {
                return api_error('not_found', 'Account was not found.', 404);
            }
            return api_error('totp_enrollment_failed', '2FA設定を開始出来ませんでした。', 409);
        }

        // Secret and otpauth URI deliberately stay on the server in C2.
        // C3 will expose only the provisioning material needed for QR setup.
        return api_success(['state' => 'pending']);
    } catch (AppTotpException $exception) {
        error_log('Account TOTP enrollment unavailable: ' . $exception::class);
        return api_error('totp_unavailable', '2FA設定を開始出来ませんでした。サーバー設定を確認してください。', 503);
    } catch (Throwable $exception) {
        error_log('Account TOTP enrollment failed: ' . $exception::class);
        return api_error('totp_enrollment_failed', '2FA設定を開始出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_totp_provisioning(int $userId): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }

    try {
        $result = auth_totp_pending_provisioning($userId);
        if (($result['ok'] ?? false) !== true) {
            $reason = (string) ($result['reason'] ?? '');
            if ($reason === 'already_enabled') {
                return api_error('totp_already_enabled', '2段階認証は既に有効です。', 409);
            }
            if ($reason === 'not_configured') {
                return api_error('totp_not_pending', '2FA設定が開始されていません。', 409);
            }
            return api_error('totp_provisioning_unavailable', 'QRコードの準備が出来ませんでした。', 409);
        }

        $secret = (string) ($result['secret'] ?? '');
        $otpauthUri = (string) ($result['otpauth_uri'] ?? '');
        if (preg_match('/\A[A-Z2-7]{32}\z/D', $secret) !== 1
            || !str_starts_with($otpauthUri, 'otpauth://totp/')
            || strlen($otpauthUri) > 1024) {
            throw new AppTotpException('TOTP provisioning output is invalid.');
        }

        // C3: provisioning material is returned only to the authenticated owner
        // after API session + CSRF validation. public/api_v1.php sends no-store.
        // Do not log or persist the plaintext Secret outside this response.
        return api_success([
            'state' => 'pending',
            'secret' => $secret,
            'otpauth_uri' => $otpauthUri,
        ]);
    } catch (AppTotpException $exception) {
        error_log('Account TOTP provisioning unavailable: ' . $exception::class);
        return api_error('totp_unavailable', 'QRコードの準備が出来ませんでした。サーバー設定を確認してください。', 503);
    } catch (Throwable $exception) {
        error_log('Account TOTP provisioning failed: ' . $exception::class);
        return api_error('totp_provisioning_failed', 'QRコードの準備が出来ませんでした。', 503);
    }
}


/** @return array{status:int,body:array<string,mixed>} */
function api_account_totp_confirm(int $userId, array $input): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }

    $code = isset($input['code']) && is_string($input['code']) ? trim($input['code']) : '';
    if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
        return api_validation_error('Authenticatorアプリの6桁コードを入力してください。');
    }

    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128);

    try {
        $status = auth_totp_status($userId);
        if (($status['enabled'] ?? false) === true) {
            return api_error('totp_already_enabled', '2段階認証は既に有効です。', 409);
        }
        if (($status['configured'] ?? false) !== true) {
            return api_error('totp_not_pending', '2FA設定が開始されていません。', 409);
        }

        $throttle = auth_2fa_throttle_status($userId, $ipAddress);
        if (($throttle['blocked'] ?? false) === true) {
            return api_error('totp_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
        }

        $result = auth_totp_enable_with_code($userId, $code);
        if (($result['ok'] ?? false) !== true) {
            $reason = (string) ($result['reason'] ?? '');
            if ($reason === 'invalid_code') {
                auth_2fa_throttle_record_failure($userId, $ipAddress);
                auth_audit_log_record('totp_enable', 'failure', $userId, null, 'totp');
                return api_error('totp_code_invalid', '6桁コードを確認してください。', 403);
            }
            if ($reason === 'already_enabled' || $reason === 'state_changed') {
                return api_error('totp_state_changed', '2FAの状態が変更されています。画面を再読込してください。', 409);
            }
            if ($reason === 'not_configured') {
                return api_error('totp_not_pending', '2FA設定が開始されていません。', 409);
            }
            return api_error('totp_enable_failed', '2段階認証を有効化出来ませんでした。', 409);
        }

        auth_2fa_throttle_record_success($userId, $ipAddress);

        // V1.32 policy: Remember Tokens issued before 2FA enablement must not
        // remain trusted. C-login already requires TOTP for any surviving token,
        // so a cleanup failure cannot bypass 2FA; keep the account enabled and log
        // only the failure class (never the code/token itself).
        try {
            remember_token_revoke_user($userId);
        } catch (Throwable $exception) {
            error_log('Account TOTP remember-token revocation failed: ' . $exception::class);
        }
        try {
            persistent_login_clear_cookie();
        } catch (Throwable $exception) {
            error_log('Account TOTP remember-cookie cleanup failed: ' . $exception::class);
        }

        auth_audit_log_record('totp_enable', 'success', $userId, null, 'totp');
        return api_success(['state' => 'enabled']);
    } catch (AppTotpException $exception) {
        error_log('Account TOTP confirmation unavailable: ' . $exception::class);
        return api_error('totp_unavailable', '2段階認証を確認出来ませんでした。サーバー設定を確認してください。', 503);
    } catch (Throwable $exception) {
        error_log('Account TOTP confirmation failed: ' . $exception::class);
        return api_error('totp_enable_failed', '2段階認証を有効化出来ませんでした。', 503);
    }
}


/** @return array{status:int,body:array<string,mixed>} */
function api_account_totp_recovery_generate(int $userId, array $input): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }

    $code = isset($input['code']) && is_string($input['code']) ? trim($input['code']) : '';
    if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
        return api_validation_error('Authenticatorアプリの6桁コードを入力してください。');
    }

    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128);

    try {
        $status = auth_totp_status($userId);
        if (($status['enabled'] ?? false) !== true) {
            return api_error('totp_not_enabled', '2段階認証が有効ではありません。', 409);
        }

        $throttle = auth_2fa_throttle_status($userId, $ipAddress);
        if (($throttle['blocked'] ?? false) === true) {
            return api_error('totp_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
        }

        if (!auth_totp_verify_enabled_code($userId, $code)) {
            auth_2fa_throttle_record_failure($userId, $ipAddress);
            auth_audit_log_record('recovery_codes_generate', 'failure', $userId, null, 'totp');
            return api_error('totp_code_invalid', '6桁コードを確認してください。', 403);
        }

        $result = auth_recovery_code_replace($userId);
        if (($result['ok'] ?? false) !== true || !is_array($result['codes'] ?? null)) {
            return api_error('recovery_code_generate_failed', 'Recovery Codeを生成出来ませんでした。', 409);
        }

        $codes = array_values($result['codes']);
        if (count($codes) !== AUTH_RECOVERY_CODE_COUNT) {
            throw new RuntimeException('Recovery Code count is invalid.');
        }
        foreach ($codes as $recoveryCode) {
            if (!is_string($recoveryCode)
                || preg_match('/\A[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}\z/D', $recoveryCode) !== 1) {
                throw new RuntimeException('Recovery Code output is invalid.');
            }
        }

        auth_2fa_throttle_record_success($userId, $ipAddress);
        auth_audit_log_record('recovery_codes_generate', 'success', $userId, null, 'totp');

        // Plaintext Recovery Codes are returned exactly once to this authenticated,
        // CSRF-validated no-store response. The database contains hashes only.
        return api_success([
            'state' => 'enabled',
            'recovery_codes' => $codes,
            'remaining' => count($codes),
        ]);
    } catch (AppTotpException $exception) {
        error_log('Account Recovery Code generation unavailable: ' . $exception::class);
        return api_error('totp_unavailable', 'Recovery Codeを生成出来ませんでした。サーバー設定を確認してください。', 503);
    } catch (Throwable $exception) {
        error_log('Account Recovery Code generation failed: ' . $exception::class);
        return api_error('recovery_code_generate_failed', 'Recovery Codeを生成出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_totp_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'account.totp.begin' => api_account_totp_begin($userId),
        'account.totp.provisioning' => api_account_totp_provisioning($userId),
        'account.totp.confirm' => api_account_totp_confirm($userId, $input),
        'account.totp.recovery.generate' => api_account_totp_recovery_generate($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
