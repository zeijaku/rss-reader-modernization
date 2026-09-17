<?php

declare(strict_types=1);

define('APP_MAIL_GOOGLE_OAUTH_CLIENT_ID', 'test-client.apps.googleusercontent.com');
define('APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET', 'test-client-secret');
define('APP_MAIL_GOOGLE_OAUTH_REDIRECT_URI', 'https://reader.example.test/mail_oauth_google.php');
define('APP_MAIL_GOOGLE_OAUTH_ALLOWED_EMAIL', '');

$testUserId = 7;
$testOAuthPending = null;
$testAccessTokens = [];
function app_session_is_authenticated(): bool { return true; }
function app_session_user_id(): ?int { return 7; }
function app_session_mail_google_oauth_store(int $ownerId, string $stateHash, string $codeVerifier, int $startedAt): void
{
    global $testOAuthPending;
    $testOAuthPending = ['owner_id' => $ownerId, 'state_hash' => $stateHash, 'code_verifier' => $codeVerifier, 'started_at' => $startedAt];
}
function app_session_mail_google_oauth_take(): ?array
{
    global $testOAuthPending;
    $pending = $testOAuthPending;
    $testOAuthPending = null;
    return $pending;
}
function app_session_mail_google_access_token_get(int $ownerId, int $accountId, string $refreshHash): ?string
{
    global $testAccessTokens;
    $entry = $testAccessTokens[$ownerId . ':' . $accountId] ?? null;
    return is_array($entry) && hash_equals((string) ($entry['refresh_hash'] ?? ''), $refreshHash)
        ? (string) ($entry['access_token'] ?? '')
        : null;
}
function app_session_mail_google_access_token_store(int $ownerId, int $accountId, string $refreshHash, string $accessToken, int $expiresAt): void
{
    global $testAccessTokens;
    $testAccessTokens[$ownerId . ':' . $accountId] = [
        'refresh_hash' => $refreshHash,
        'access_token' => $accessToken,
        'expires_at' => $expiresAt,
    ];
}
function mail_crypto_decrypt(int $ownerId, int $accountId, string $envelope): string
{
    return $envelope;
}

require_once dirname(__DIR__) . '/app/mail/mail_google_oauth.php';
require_once dirname(__DIR__) . '/app/mail/mail_smtp_client.php';

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$begin = mail_google_oauth_begin($testUserId);
$url = parse_url($begin['authorization_url']);
parse_str((string) ($url['query'] ?? ''), $query);
$check(($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === 'accounts.google.com', 'authorization uses the fixed Google HTTPS endpoint');
$check(($query['access_type'] ?? '') === 'offline' && ($query['prompt'] ?? '') === 'consent', 'authorization requests an offline refresh token');
$check(($query['code_challenge_method'] ?? '') === 'S256' && isset($query['code_challenge']), 'authorization uses PKCE S256');
$check(isset($query['state']) && is_string($query['state']), 'authorization includes random state');

$requests = [];
$transport = static function (string $url, string $method, array $form, ?string $bearer) use (&$requests): array {
    $requests[] = [$url, $method, $form, $bearer];
    if ($url === 'https://oauth2.googleapis.com/token') {
        return ['ok' => true, 'status' => 200, 'body' => json_encode([
            'access_token' => 'access-token-for-test',
            'refresh_token' => 'refresh-token-for-test',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'openid email https://mail.google.com/',
        ], JSON_THROW_ON_ERROR)];
    }
    return ['ok' => true, 'status' => 200, 'body' => json_encode([
        'email' => 'owner@example.com',
        'email_verified' => true,
    ], JSON_THROW_ON_ERROR)];
};

$complete = mail_google_oauth_complete($testUserId, (string) $query['state'], 'test-auth-code', $transport);
$check($complete['email'] === 'owner@example.com', 'callback accepts a verified Google email');
$check($complete['refresh_token'] === 'refresh-token-for-test', 'callback returns only durable authorization material');
$check($testOAuthPending === null, 'callback state is consumed exactly once');
$check(($requests[0][2]['code_verifier'] ?? '') !== '', 'token exchange submits the session-bound PKCE verifier');

$replayRejected = false;
try {
    mail_google_oauth_complete($testUserId, (string) $query['state'], 'test-auth-code', $transport);
} catch (AppMailGoogleOAuthException) {
    $replayRejected = true;
}
$check($replayRejected, 'a consumed callback cannot be replayed');

$refresh = mail_google_oauth_refresh_access_token('refresh-token-for-test', $transport);
$check($refresh === 'access-token-for-test', 'refresh grant returns the short-lived access token');
$lastRequest = $requests[count($requests) - 1];
$check(($lastRequest[2]['grant_type'] ?? '') === 'refresh_token', 'runtime credential uses the refresh-token grant');

$cachedHash = hash('sha256', 'refresh-token-for-test');
$testAccessTokens['7:41'] = [
    'refresh_hash' => $cachedHash,
    'access_token' => 'session-cached-access-token',
    'expires_at' => time() + 3600,
];
$requestCountBeforeCacheRead = count($requests);
$cachedAuth = mail_account_runtime_imap_auth(7, 41, [
    'mail_account_auth_type' => 'google_oauth',
    'mail_account_username' => 'owner@example.com',
    'mail_account_secret' => 'refresh-token-for-test',
], $transport);
$check($cachedAuth['credential'] === 'session-cached-access-token', 'runtime reuses a session-cached unexpired access token');
$check(count($requests) === $requestCountBeforeCacheRead, 'session-cached runtime credential avoids another Google token request');

$runtimeAuth = mail_account_runtime_imap_auth(7, 42, [
    'mail_account_auth_type' => 'google_oauth',
    'mail_account_username' => 'owner@example.com',
    'mail_account_secret' => 'another-refresh-token',
], $transport);
$storedRuntime = $testAccessTokens['7:42'] ?? null;
$check($runtimeAuth['credential'] === 'access-token-for-test', 'runtime refresh still returns the access token on a cache miss');
$check(is_array($storedRuntime) && ($storedRuntime['access_token'] ?? '') === 'access-token-for-test'
    && (int) ($storedRuntime['expires_at'] ?? 0) > time() + 300, 'runtime stores the refreshed access token with a bounded expiry');

$check(mail_phpmailer_load(), 'bundled PHPMailer OAuth boundary is available');
$mailer = new PHPMailer\PHPMailer\PHPMailer(true);
mail_smtp_client_apply_oauth_auth($mailer, 'owner@example.com', 'access-token-for-test');
$oauthPayload = base64_decode($mailer->getOAuth()->getOauth64(), true);
$check($mailer->AuthType === 'XOAUTH2' && $mailer->Password === '', 'SMTP selects XOAUTH2 without a password');
$check($oauthPayload === "user=owner@example.com\x01auth=Bearer access-token-for-test\x01\x01", 'SMTP emits the Gmail SASL XOAUTH2 payload');

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) { $passed++; }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
