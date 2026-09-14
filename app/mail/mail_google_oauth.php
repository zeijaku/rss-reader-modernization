<?php

declare(strict_types=1);

final class AppMailGoogleOAuthException extends RuntimeException
{
}

const MAIL_GOOGLE_OAUTH_SCOPE = 'openid email https://mail.google.com/';
const MAIL_GOOGLE_OAUTH_STATE_TTL_SECONDS = 600;

function mail_google_oauth_configured(): bool
{
    $clientId = (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_ID;
    $clientSecret = (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET;
    $redirectUri = (string) APP_MAIL_GOOGLE_OAUTH_REDIRECT_URI;

    if ($clientId === '' || strlen($clientId) > 512 || preg_match('/[\x00-\x20\x7F]/', $clientId) === 1) {
        return false;
    }
    if ($clientSecret === '' || strlen($clientSecret) > 1024 || str_contains($clientSecret, "\0")) {
        return false;
    }

    $parts = parse_url($redirectUri);
    return is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
        && isset($parts['host'])
        && !isset($parts['user'], $parts['pass'], $parts['fragment'])
        && strlen($redirectUri) <= 2048;
}

function mail_google_oauth_allowed_email(): ?string
{
    $email = strtolower(trim((string) APP_MAIL_GOOGLE_OAUTH_ALLOWED_EMAIL));
    if ($email === '') {
        return null;
    }
    if (strlen($email) > 320 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new AppMailGoogleOAuthException('Google OAuth allowed email is invalid.');
    }
    return $email;
}

function mail_google_oauth_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mail_google_oauth_mail_scope_granted(mixed $value): bool
{
    if (!is_string($value)) {
        return false;
    }
    return in_array('https://mail.google.com/', preg_split('/\s+/', trim($value)) ?: [], true);
}

/** @return array{ok:bool,status:int,body:string} */
function mail_google_oauth_http_request(
    string $url,
    string $method,
    array $form = [],
    ?string $bearerToken = null,
    ?callable $transport = null
): array {
    if ($transport !== null) {
        $result = $transport($url, $method, $form, $bearerToken);
        if (!is_array($result)) {
            throw new AppMailGoogleOAuthException('Google OAuth transport returned an invalid response.');
        }
        return [
            'ok' => ($result['ok'] ?? false) === true,
            'status' => (int) ($result['status'] ?? 0),
            'body' => is_string($result['body'] ?? null) ? $result['body'] : '',
        ];
    }

    if (!function_exists('curl_init')) {
        throw new AppMailGoogleOAuthException('cURL is unavailable.');
    }
    if (!in_array($url, [
        'https://oauth2.googleapis.com/token',
        'https://openidconnect.googleapis.com/v1/userinfo',
    ], true)) {
        throw new AppMailGoogleOAuthException('Google OAuth endpoint is invalid.');
    }

    $body = '';
    $headers = ['Accept: application/json'];
    if ($bearerToken !== null) {
        if ($bearerToken === '' || strlen($bearerToken) > 8192 || preg_match('/[\x00-\x20\x7F]/', $bearerToken) === 1) {
            throw new AppMailGoogleOAuthException('Google OAuth access token is invalid.');
        }
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $handle = curl_init($url);
    if ($handle === false) {
        throw new AppMailGoogleOAuthException('Google OAuth request could not be initialized.');
    }
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 65536) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($handle, $options);

    try {
        $executed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        return [
            'ok' => $executed === true && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
        ];
    } finally {
        curl_close($handle);
    }
}

/** @return array<string,mixed> */
function mail_google_oauth_json_response(array $response): array
{
    if (($response['ok'] ?? false) !== true || strlen((string) ($response['body'] ?? '')) > 65536) {
        throw new AppMailGoogleOAuthException('Google OAuth request failed.');
    }
    try {
        $decoded = json_decode((string) $response['body'], true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new AppMailGoogleOAuthException('Google OAuth response is invalid.', 0, $exception);
    }
    if (!is_array($decoded)) {
        throw new AppMailGoogleOAuthException('Google OAuth response is invalid.');
    }
    return $decoded;
}

/** @return array{authorization_url:string} */
function mail_google_oauth_begin(int $userId): array
{
    if ($userId <= 0 || !app_session_is_authenticated() || app_session_user_id() !== $userId) {
        throw new AppMailGoogleOAuthException('Authenticated owner is required.');
    }
    if (!mail_google_oauth_configured()) {
        throw new AppMailGoogleOAuthException('Google OAuth is not configured.');
    }

    $state = mail_google_oauth_base64url(random_bytes(32));
    $verifier = mail_google_oauth_base64url(random_bytes(48));
    $_SESSION['mail_google_oauth'] = [
        'owner_id' => $userId,
        'state_hash' => hash('sha256', $state),
        'code_verifier' => $verifier,
        'started_at' => time(),
    ];

    $query = http_build_query([
        'client_id' => (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_ID,
        'redirect_uri' => (string) APP_MAIL_GOOGLE_OAUTH_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => MAIL_GOOGLE_OAUTH_SCOPE,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'include_granted_scopes' => 'true',
        'state' => $state,
        'code_challenge' => mail_google_oauth_base64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
    ], '', '&', PHP_QUERY_RFC3986);

    return ['authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $query];
}

/**
 * Consume the one-time callback state and return verified Gmail authorization material.
 *
 * @return array{email:string,refresh_token:string}
 */
function mail_google_oauth_complete(
    int $userId,
    string $state,
    string $code,
    ?callable $transport = null,
    ?int $now = null
): array {
    $pending = $_SESSION['mail_google_oauth'] ?? null;
    unset($_SESSION['mail_google_oauth']);
    $timestamp = $now ?? time();

    if (!is_array($pending)
        || $userId <= 0
        || (int) ($pending['owner_id'] ?? 0) !== $userId
        || !is_string($pending['state_hash'] ?? null)
        || !is_string($pending['code_verifier'] ?? null)
        || (int) ($pending['started_at'] ?? 0) <= 0
        || ($timestamp - (int) $pending['started_at']) > MAIL_GOOGLE_OAUTH_STATE_TTL_SECONDS
        || (int) $pending['started_at'] > ($timestamp + 60)
        || $state === ''
        || !hash_equals((string) $pending['state_hash'], hash('sha256', $state))
        || preg_match('/\A[A-Za-z0-9._~\/-]{1,2048}\z/D', $code) !== 1) {
        throw new AppMailGoogleOAuthException('Google OAuth callback state is invalid or expired.');
    }
    if (!mail_google_oauth_configured()) {
        throw new AppMailGoogleOAuthException('Google OAuth is not configured.');
    }

    $tokens = mail_google_oauth_json_response(mail_google_oauth_http_request(
        'https://oauth2.googleapis.com/token',
        'POST',
        [
            'client_id' => (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_ID,
            'client_secret' => (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET,
            'code' => $code,
            'code_verifier' => (string) $pending['code_verifier'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => (string) APP_MAIL_GOOGLE_OAUTH_REDIRECT_URI,
        ],
        null,
        $transport
    ));
    $accessToken = $tokens['access_token'] ?? null;
    $refreshToken = $tokens['refresh_token'] ?? null;
    if (!is_string($accessToken) || $accessToken === '' || strlen($accessToken) > 8192
        || !is_string($refreshToken) || $refreshToken === '' || strlen($refreshToken) > 8192
        || !mail_google_oauth_mail_scope_granted($tokens['scope'] ?? null)) {
        throw new AppMailGoogleOAuthException('Google did not return the required offline authorization.');
    }

    $profile = mail_google_oauth_json_response(mail_google_oauth_http_request(
        'https://openidconnect.googleapis.com/v1/userinfo',
        'GET',
        [],
        $accessToken,
        $transport
    ));
    $email = is_string($profile['email'] ?? null) ? strtolower(trim($profile['email'])) : '';
    $verified = ($profile['email_verified'] ?? false) === true;
    if (!$verified || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 320) {
        throw new AppMailGoogleOAuthException('Google account email could not be verified.');
    }
    $allowedEmail = mail_google_oauth_allowed_email();
    if ($allowedEmail !== null && !hash_equals($allowedEmail, $email)) {
        throw new AppMailGoogleOAuthException('This Google account is not allowed.');
    }

    return ['email' => $email, 'refresh_token' => $refreshToken];
}

function mail_google_oauth_refresh_access_token(string $refreshToken, ?callable $transport = null): string
{
    if (!mail_google_oauth_configured() || $refreshToken === '' || strlen($refreshToken) > 8192) {
        throw new AppMailGoogleOAuthException('Google OAuth refresh credential is unavailable.');
    }
    $tokens = mail_google_oauth_json_response(mail_google_oauth_http_request(
        'https://oauth2.googleapis.com/token',
        'POST',
        [
            'client_id' => (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_ID,
            'client_secret' => (string) APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ],
        null,
        $transport
    ));
    $accessToken = $tokens['access_token'] ?? null;
    if (!is_string($accessToken) || $accessToken === '' || strlen($accessToken) > 8192
        || preg_match('/[\x00-\x20\x7F]/', $accessToken) === 1) {
        throw new AppMailGoogleOAuthException('Google OAuth access token is unavailable.');
    }
    return $accessToken;
}

/** @return array{username:string,credential:string,authentication:string} */
function mail_account_runtime_imap_auth(int $ownerId, int $accountId, array $account, ?callable $transport = null): array
{
    static $requestCache = [];
    $authType = (string) ($account['mail_account_auth_type'] ?? 'password');
    $username = (string) ($account['mail_account_username'] ?? '');
    $envelope = (string) ($account['mail_account_secret'] ?? '');
    $secret = mail_crypto_decrypt($ownerId, $accountId, $envelope);

    if ($authType === 'password') {
        return ['username' => $username, 'credential' => $secret, 'authentication' => 'plain'];
    }
    if ($authType !== 'google_oauth') {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        }
        throw new AppMailGoogleOAuthException('Mail account authentication type is unsupported.');
    }

    try {
        $cacheKey = $ownerId . ':' . $accountId . ':' . hash('sha256', $secret);
        if ($transport === null && isset($requestCache[$cacheKey]) && is_string($requestCache[$cacheKey])) {
            $accessToken = $requestCache[$cacheKey];
        } else {
            $accessToken = mail_google_oauth_refresh_access_token($secret, $transport);
            if ($transport === null) {
                $requestCache[$cacheKey] = $accessToken;
            }
        }
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        }
    }
    return ['username' => $username, 'credential' => $accessToken, 'authentication' => 'oauth'];
}
