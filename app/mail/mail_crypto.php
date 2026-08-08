<?php

declare(strict_types=1);

final class AppMailCredentialException extends RuntimeException
{
}

function mail_crypto_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mail_crypto_base64url_decode(string $value): ?string
{
    if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
        return null;
    }

    $padding = (4 - (strlen($value) % 4)) % 4;
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
    if (!is_string($decoded) || mail_crypto_base64url_encode($decoded) !== $value) {
        return null;
    }
    return $decoded;
}

function mail_crypto_key_id(): string
{
    $keyId = (string) APP_MAIL_CREDENTIAL_KEY_ID;
    if (preg_match('/\A[A-Za-z0-9_-]{1,32}\z/D', $keyId) !== 1) {
        throw new AppMailCredentialException('Mail credential key ID is invalid.');
    }
    return $keyId;
}

function mail_crypto_decode_key(string $encoded): string
{
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        throw new AppMailCredentialException('Sodium extension is unavailable.');
    }

    $encoded = trim($encoded);
    if ($encoded === '') {
        throw new AppMailCredentialException('Mail credential key is not configured.');
    }

    $key = base64_decode($encoded, true);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
        throw new AppMailCredentialException('Mail credential key is invalid.');
    }

    return $key;
}

function mail_crypto_key(): string
{
    return mail_crypto_decode_key((string) APP_MAIL_CREDENTIAL_KEY_B64);
}

function mail_crypto_aad(int $ownerId, int $accountId): string
{
    if ($ownerId <= 0 || $accountId <= 0) {
        throw new AppMailCredentialException('Mail credential context is invalid.');
    }
    return 'rss-reader:mail-account:' . $ownerId . ':' . $accountId . ':v1';
}

function mail_crypto_encrypt(int $ownerId, int $accountId, string $plaintext): string
{
    if ($plaintext === '' || strlen($plaintext) > 8192 || str_contains($plaintext, "\0")) {
        throw new AppMailCredentialException('Mail credential value is invalid.');
    }

    $key = mail_crypto_key();
    try {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            mail_crypto_aad($ownerId, $accountId),
            $nonce,
            $key
        );
        return 'v1.' . mail_crypto_key_id() . '.'
            . mail_crypto_base64url_encode($nonce) . '.'
            . mail_crypto_base64url_encode($ciphertext);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}

function mail_crypto_decrypt(int $ownerId, int $accountId, string $envelope): string
{
    $parts = explode('.', $envelope);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        throw new AppMailCredentialException('Mail credential envelope is invalid.');
    }

    $expectedKeyId = mail_crypto_key_id();
    if (!hash_equals($expectedKeyId, $parts[1])) {
        throw new AppMailCredentialException('Mail credential key ID does not match.');
    }

    $nonce = mail_crypto_base64url_decode($parts[2]);
    $ciphertext = mail_crypto_base64url_decode($parts[3]);
    if (!is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || !is_string($ciphertext)) {
        throw new AppMailCredentialException('Mail credential envelope is invalid.');
    }

    $key = mail_crypto_key();
    try {
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            mail_crypto_aad($ownerId, $accountId),
            $nonce,
            $key
        );
        if (!is_string($plaintext) || $plaintext === '' || strlen($plaintext) > 8192 || str_contains($plaintext, "\0")) {
            throw new AppMailCredentialException('Mail credential could not be decrypted.');
        }
        return $plaintext;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}
