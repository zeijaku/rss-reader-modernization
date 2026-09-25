<?php

declare(strict_types=1);

/**
 * Mail credential and Google OAuth exceptions carry only a fixed internal
 * reason code. Provider responses, tokens, authorization codes, passwords,
 * and endpoint exception messages must never be copied into these objects.
 */
final class AppMailCredentialException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        ?Throwable $previous = null
    ) {
        parent::__construct('Mail credential operation failed.', 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

final class AppMailGoogleOAuthException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        ?Throwable $previous = null
    ) {
        parent::__construct('Google OAuth operation failed.', 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

function mail_credential_failure_code(AppMailCredentialException $exception): string
{
    return match ($exception->reason()) {
        'key_id_invalid' => 'mail_credential_key_config_invalid',
        'crypto_unavailable' => 'mail_credential_crypto_unavailable',
        'key_missing' => 'mail_credential_key_missing',
        'key_invalid' => 'mail_credential_key_invalid',
        'context_invalid', 'value_invalid' => 'mail_credential_context_invalid',
        'envelope_invalid' => 'mail_credential_data_invalid',
        'key_mismatch' => 'mail_credential_key_mismatch',
        'decrypt_failed' => 'mail_credential_decrypt_failed',
        'smtp_context_invalid', 'smtp_value_invalid' => 'mail_smtp_credential_context_invalid',
        'smtp_envelope_invalid' => 'mail_smtp_credential_data_invalid',
        'smtp_key_mismatch' => 'mail_smtp_credential_key_mismatch',
        'smtp_decrypt_failed' => 'mail_smtp_credential_decrypt_failed',
        default => 'mail_credential_unavailable',
    };
}

function mail_google_oauth_failure_code(AppMailGoogleOAuthException $exception): string
{
    return match ($exception->reason()) {
        'owner_invalid' => 'mail_oauth_session_invalid',
        'state_missing' => 'mail_oauth_state_missing',
        'state_expired' => 'mail_oauth_state_expired',
        'state_mismatch', 'state_invalid' => 'mail_oauth_state_mismatch',
        'authorization_denied' => 'mail_oauth_access_denied',
        'authorization_code_invalid' => 'mail_oauth_authorization_code_invalid',
        'offline_authorization_missing' => 'mail_oauth_offline_access_missing',
        'scope_missing' => 'mail_oauth_scope_missing',
        'email_unverified' => 'mail_oauth_email_unverified',
        'account_not_allowed' => 'mail_oauth_account_not_allowed',
        'refresh_credential_missing', 'refresh_token_expired' => 'mail_oauth_reconnect_required',
        'dependency_unavailable' => 'mail_oauth_dependency_unavailable',
        'google_timeout' => 'mail_oauth_google_timeout',
        'google_tls_failed' => 'mail_oauth_google_tls_failed',
        'google_unavailable', 'provider_rejected' => 'mail_oauth_google_unavailable',
        'response_invalid', 'transport_invalid', 'access_token_missing', 'access_token_invalid', 'request_init_failed'
            => 'mail_oauth_response_invalid',
        'allowed_email_invalid', 'not_configured', 'client_invalid', 'endpoint_invalid'
            => 'mail_oauth_configuration_invalid',
        'auth_type_unsupported' => 'mail_auth_type_unsupported',
        default => 'mail_oauth_unavailable',
    };
}

function mail_auth_failure_code(Throwable $exception): string
{
    if ($exception instanceof AppMailCredentialException) {
        return mail_credential_failure_code($exception);
    }
    if ($exception instanceof AppMailGoogleOAuthException) {
        return mail_google_oauth_failure_code($exception);
    }
    return 'mail_credential_unavailable';
}

function mail_is_auth_failure_code(string $code): bool
{
    return str_starts_with($code, 'mail_credential_')
        || str_starts_with($code, 'mail_smtp_credential_')
        || str_starts_with($code, 'mail_oauth_')
        || $code === 'mail_auth_type_unsupported';
}

/**
 * @return array{code:string,message:string,status:int}|null
 */
function mail_public_error_details(string $code): ?array
{
    return match ($code) {
        'mail_credential_key_missing' => [
            'code' => $code,
            'message' => 'Mail認証情報の暗号鍵が設定されていません。Server設定を確認してください。',
            'status' => 503,
        ],
        'mail_credential_key_config_invalid', 'mail_credential_key_invalid', 'mail_credential_crypto_unavailable' => [
            'code' => $code,
            'message' => 'Mail認証情報の暗号化設定を利用できません。Server設定を確認してください。',
            'status' => 503,
        ],
        'mail_credential_key_mismatch', 'mail_smtp_credential_key_mismatch' => [
            'code' => $code,
            'message' => '保存時と現在のMail暗号鍵が一致しません。以前の鍵を戻すか、Mail Accountを再接続してください。',
            'status' => 409,
        ],
        'mail_credential_data_invalid', 'mail_credential_decrypt_failed',
        'mail_smtp_credential_data_invalid', 'mail_smtp_credential_decrypt_failed' => [
            'code' => $code,
            'message' => '保存済みのMail認証情報を読み出せません。Mail Accountを再接続してください。',
            'status' => 409,
        ],
        'mail_credential_context_invalid', 'mail_smtp_credential_context_invalid',
        'mail_credential_unavailable', 'mail_smtp_credential_unavailable' => [
            'code' => $code,
            'message' => $code === 'mail_smtp_credential_unavailable'
                ? 'SMTP認証情報を利用できません。Mail Account設定を確認してください。'
                : 'Mail認証情報を利用できません。Mail Account設定を確認してください。',
            'status' => 503,
        ],
        'mail_oauth_reconnect_required' => [
            'code' => $code,
            'message' => 'Gmailの認証期限が切れているか、認証が無効になっています。「Gmail再接続」を実行してください。',
            'status' => 409,
        ],
        'mail_oauth_state_missing' => [
            'code' => $code,
            'message' => 'Gmail認証の開始情報がありません。Account管理の「Gmail再接続」からやり直してください。',
            'status' => 409,
        ],
        'mail_oauth_state_expired' => [
            'code' => $code,
            'message' => 'Gmail認証操作の有効期限（10分）が切れました。「Gmail再接続」からやり直してください。',
            'status' => 409,
        ],
        'mail_oauth_state_mismatch' => [
            'code' => $code,
            'message' => 'Gmail認証状態が一致しません。古い認証画面や別タブを閉じ、「Gmail再接続」からやり直してください。',
            'status' => 409,
        ],
        'mail_oauth_access_denied' => [
            'code' => $code,
            'message' => 'Google側でGmail接続がキャンセルまたは拒否されました。許可内容を確認して再接続してください。',
            'status' => 403,
        ],
        'mail_oauth_authorization_code_invalid' => [
            'code' => $code,
            'message' => 'Googleの認証コードを確認できませんでした。古い認証画面を閉じて再接続してください。',
            'status' => 409,
        ],
        'mail_oauth_offline_access_missing' => [
            'code' => $code,
            'message' => 'Gmailの継続利用に必要な認証情報を取得できませんでした。Googleの許可画面から再接続してください。',
            'status' => 409,
        ],
        'mail_oauth_scope_missing' => [
            'code' => $code,
            'message' => '必要なGmail権限が許可されていません。権限を許可して再接続してください。',
            'status' => 403,
        ],
        'mail_oauth_email_unverified' => [
            'code' => $code,
            'message' => 'Googleアカウントの確認済みメールアドレスを取得できませんでした。',
            'status' => 403,
        ],
        'mail_oauth_account_not_allowed' => [
            'code' => $code,
            'message' => 'このGoogleアカウントはRSS Readerでの利用を許可されていません。',
            'status' => 403,
        ],
        'mail_oauth_session_invalid' => [
            'code' => $code,
            'message' => 'Login Sessionを確認できませんでした。再Login後にGmailを再接続してください。',
            'status' => 401,
        ],
        'mail_oauth_dependency_unavailable' => [
            'code' => $code,
            'message' => 'Google認証に必要なcURL機能を利用できません。Server設定を確認してください。',
            'status' => 503,
        ],
        'mail_oauth_google_timeout' => [
            'code' => $code,
            'message' => 'Google認証サーバーとの通信がタイムアウトしました。時間を置いて再試行してください。',
            'status' => 504,
        ],
        'mail_oauth_google_tls_failed' => [
            'code' => $code,
            'message' => 'Google認証サーバーとの安全な接続に失敗しました。Serverの証明書設定を確認してください。',
            'status' => 502,
        ],
        'mail_oauth_google_unavailable' => [
            'code' => $code,
            'message' => 'Google認証サーバーとの通信に失敗しました。時間を置いて再試行してください。',
            'status' => 502,
        ],
        'mail_oauth_response_invalid' => [
            'code' => $code,
            'message' => 'Google認証サーバーから有効な応答を取得できませんでした。再試行してください。',
            'status' => 502,
        ],
        'mail_oauth_configuration_invalid' => [
            'code' => $code,
            'message' => 'Gmail OAuth2のServer設定を利用できません。Client ID・Secret・Redirect URIを確認してください。',
            'status' => 503,
        ],
        'mail_storage_unavailable' => [
            'code' => $code,
            'message' => 'Mail Accountの保存領域を利用できません。Server側のDB状態とMail Migrationを確認してください。',
            'status' => 503,
        ],
        'mail_auth_type_unsupported' => [
            'code' => $code,
            'message' => 'Mail Accountの認証方式を利用できません。Account設定を確認してください。',
            'status' => 409,
        ],
        'mail_oauth_unavailable' => [
            'code' => $code,
            'message' => 'Gmail認証情報を利用できません。「Gmail再接続」を実行してください。',
            'status' => 503,
        ],
        default => null,
    };
}

/**
 * Log only fixed reason codes and numeric local IDs. Never log exception
 * messages because provider/library text can contain endpoint or credential
 * material.
 */
function mail_log_auth_failure(
    string $operation,
    int $ownerId,
    ?int $accountId,
    Throwable $exception
): string {
    $code = mail_auth_failure_code($exception);
    $safeOperation = preg_match('/\A[a-z0-9._-]{1,64}\z/D', $operation) === 1 ? $operation : 'unknown';
    error_log(sprintf(
        'Mail auth failure operation=%s user_id=%d account_id=%d code=%s class=%s',
        $safeOperation,
        max(0, $ownerId),
        max(0, (int) $accountId),
        $code,
        $exception::class
    ));
    return $code;
}
