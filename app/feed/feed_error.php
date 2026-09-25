<?php

declare(strict_types=1);

/**
 * Public RSS errors intentionally use six actionable categories. Internal
 * transport codes and provider/library messages must not be copied to the UI.
 *
 * @return array{code:string,message:string,status:int}
 */
function feed_public_error_details(string $errorType, string $internalCode = '', int $httpStatus = 0): array
{
    $errorType = strtolower(trim($errorType));
    $internalCode = strtolower(trim($internalCode));
    $httpStatus = max(0, min(599, $httpStatus));

    if ($errorType === 'parse' || $internalCode === 'parse_error') {
        return [
            'code' => 'invalid_feed',
            'message' => '取得先の内容をRSSまたはAtomとして読み取れませんでした。Feed形式や文字コードを確認してください。',
            'status' => 502,
        ];
    }

    if ($errorType === 'server' || in_array($internalCode, ['curl_unavailable', 'curl_init_failed'], true)) {
        return [
            'code' => 'rss_server_unavailable',
            'message' => 'RSS取得に必要なServer機能を利用できません。PHP・DB・Cache設定を確認してください。',
            'status' => 503,
        ];
    }

    if (in_array($internalCode, [
        'invalid_url',
        'port_not_allowed',
        'non_public_address',
        'invalid_redirect',
        'too_many_redirects',
        'response_too_large',
    ], true)) {
        $message = match ($internalCode) {
            'response_too_large' => 'RSSのResponseが安全上限を超えたため取得を停止しました。',
            'invalid_redirect', 'too_many_redirects' => 'RSSの転送先が不正、または転送回数が上限を超えたため取得を停止しました。',
            default => 'RSS URLが無効、またはOutbound Security Policyにより取得を停止しました。URL・Port・接続先を確認してください。',
        };
        return ['code' => 'upstream_blocked', 'message' => $message, 'status' => 422];
    }

    if ($internalCode === 'http_status') {
        $message = match (true) {
            in_array($httpStatus, [401, 403], true)
                => 'RSS ServerがAccessを拒否しました（HTTP ' . $httpStatus . '）。公開範囲や認証設定を確認してください。',
            in_array($httpStatus, [404, 410], true)
                => 'RSS Feedが見つかりません（HTTP ' . $httpStatus . '）。URLの変更や配信終了を確認してください。',
            $httpStatus === 429
                => 'RSS Serverの取得回数制限に達しました（HTTP 429）。時間を置いて再試行してください。',
            $httpStatus >= 500
                => 'RSS Server側で一時的な障害が発生しています（HTTP ' . $httpStatus . '）。時間を置いて再試行してください。',
            $httpStatus >= 400
                => 'RSS Serverが取得要求を受け付けませんでした（HTTP ' . $httpStatus . '）。',
            default => 'RSS Serverから正常ではないHTTP Responseが返されました。',
        };
        return ['code' => 'rss_http_error', 'message' => $message, 'status' => 502];
    }

    if (in_array($internalCode, ['timeout', 'empty_response', 'retry_backoff', 'unexpected_not_modified'], true)) {
        $message = $internalCode === 'timeout'
            ? 'RSS Serverから時間内に応答がありませんでした。時間を置いて再試行してください。'
            : 'RSS Serverから有効なResponseを取得できませんでした。時間を置いて再試行してください。';
        return ['code' => 'rss_temporarily_unavailable', 'message' => $message, 'status' => 504];
    }

    $message = match ($internalCode) {
        'dns_failed' => 'RSS Serverの名前を解決できませんでした。Host名とDNS状態を確認してください。',
        'tls_error' => 'RSS Serverとの安全なHTTPS接続を確認できませんでした。証明書の状態を確認してください。',
        default => 'RSS Serverへ接続できませんでした。URLまたは配信元Serverの状態を確認してください。',
    };
    return ['code' => 'rss_connection_failed', 'message' => $message, 'status' => 502];
}

function feed_public_error_is_upstream_code(string $code): bool
{
    return in_array($code, [
        'upstream_blocked',
        'rss_connection_failed',
        'rss_temporarily_unavailable',
        'rss_http_error',
        'invalid_feed',
        'rss_server_unavailable',
    ], true);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_internal_failure(string $operation, int $userId, int $contentId, Throwable $exception): array
{
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }
    $safeOperation = preg_match('/\A[a-z0-9._-]{1,64}\z/D', $operation) === 1 ? $operation : 'unknown';
    error_log(sprintf(
        'RSS failure ref=%s operation=%s user_id=%d content_id=%d class=%s',
        $reference,
        $safeOperation,
        max(0, $userId),
        max(0, $contentId),
        $exception::class
    ));
    return api_error(
        'rss_server_unavailable',
        'RSS Reader内部で取得処理を完了できませんでした。参照番号: ' . $reference,
        503
    );
}
