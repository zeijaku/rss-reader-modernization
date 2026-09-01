<?php

declare(strict_types=1);

function remote_crypto_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function remote_crypto_base64url_decode(string $value): ?string
{
    if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
        return null;
    }
    $padding = (4 - (strlen($value) % 4)) % 4;
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
    if (!is_string($decoded) || remote_crypto_base64url_encode($decoded) !== $value) {
        return null;
    }
    return $decoded;
}

function remote_crypto_key_id(): string
{
    $keyId = defined('APP_REMOTE_CREDENTIAL_KEY_ID') ? (string) APP_REMOTE_CREDENTIAL_KEY_ID : '';
    if (preg_match('/\A[A-Za-z0-9_-]{1,32}\z/D', $keyId) !== 1) {
        throw new AppRemoteCredentialException('Remote credential key ID is invalid.');
    }
    return $keyId;
}

function remote_crypto_key(): string
{
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        throw new AppRemoteCredentialException('Sodium extension is unavailable.');
    }
    $encoded = defined('APP_REMOTE_CREDENTIAL_KEY_B64') ? trim((string) APP_REMOTE_CREDENTIAL_KEY_B64) : '';
    $key = $encoded !== '' ? base64_decode($encoded, true) : false;
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
        throw new AppRemoteCredentialException('Remote credential key is not configured.');
    }
    return $key;
}

function remote_crypto_aad(int $ownerId, int $connectionId): string
{
    if ($ownerId <= 0 || $connectionId <= 0) {
        throw new AppRemoteCredentialException('Remote credential context is invalid.');
    }
    return 'rss-reader:remote-connection:' . $ownerId . ':' . $connectionId . ':v1';
}

/** @param array<string,string> $credentials */
function remote_crypto_validate_credentials(array $credentials): array
{
    $allowed = ['password', 'private_key', 'passphrase'];
    $clean = [];
    foreach ($credentials as $name => $value) {
        if (!in_array($name, $allowed, true) || !is_string($value) || str_contains($value, "\0")) {
            throw new AppRemoteCredentialException('Remote credential payload is invalid.');
        }
        $max = $name === 'private_key' ? 65536 : 8192;
        if ($value === '' || strlen($value) > $max) {
            throw new AppRemoteCredentialException('Remote credential payload is invalid.');
        }
        $clean[$name] = $value;
    }
    if ($clean === []) {
        throw new AppRemoteCredentialException('Remote credential payload is empty.');
    }
    return $clean;
}

/** @param array<string,string> $credentials */
function remote_crypto_encrypt(int $ownerId, int $connectionId, array $credentials): string
{
    $credentials = remote_crypto_validate_credentials($credentials);
    $json = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_string($json) || strlen($json) > 70000) {
        throw new AppRemoteCredentialException('Remote credential payload is too large.');
    }

    $key = remote_crypto_key();
    try {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $json,
            remote_crypto_aad($ownerId, $connectionId),
            $nonce,
            $key
        );
        return 'v1.' . remote_crypto_key_id() . '.'
            . remote_crypto_base64url_encode($nonce) . '.'
            . remote_crypto_base64url_encode($ciphertext);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
            sodium_memzero($json);
        }
    }
}

/** @return array<string,string> */
function remote_crypto_decrypt(int $ownerId, int $connectionId, string $envelope): array
{
    $parts = explode('.', $envelope);
    if (count($parts) !== 4 || $parts[0] !== 'v1' || !hash_equals(remote_crypto_key_id(), $parts[1])) {
        throw new AppRemoteCredentialException('Remote credential envelope is invalid.');
    }
    $nonce = remote_crypto_base64url_decode($parts[2]);
    $ciphertext = remote_crypto_base64url_decode($parts[3]);
    if (!is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || !is_string($ciphertext)) {
        throw new AppRemoteCredentialException('Remote credential envelope is invalid.');
    }

    $key = remote_crypto_key();
    $plaintext = '';
    try {
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            remote_crypto_aad($ownerId, $connectionId),
            $nonce,
            $key
        );
        if (!is_string($plaintext) || $plaintext === '' || strlen($plaintext) > 70000) {
            throw new AppRemoteCredentialException('Remote credential could not be decrypted.');
        }
        $decoded = json_decode($plaintext, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new AppRemoteCredentialException('Remote credential payload is invalid.');
        }
        return remote_crypto_validate_credentials($decoded);
    } catch (JsonException $exception) {
        throw new AppRemoteCredentialException('Remote credential payload is invalid.', 0, $exception);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
            if (is_string($plaintext) && $plaintext !== '') {
                sodium_memzero($plaintext);
            }
        }
    }
}
