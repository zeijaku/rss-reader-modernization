<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_error.php';

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
        throw new AppMailCredentialException('key_id_invalid');
    }
    return $keyId;
}

function mail_crypto_decode_key(string $encoded): string
{
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        throw new AppMailCredentialException('crypto_unavailable');
    }

    $encoded = trim($encoded);
    if ($encoded === '') {
        throw new AppMailCredentialException('key_missing');
    }

    $key = base64_decode($encoded, true);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
        throw new AppMailCredentialException('key_invalid');
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
        throw new AppMailCredentialException('context_invalid');
    }
    return 'rss-reader:mail-account:' . $ownerId . ':' . $accountId . ':v1';
}

function mail_crypto_encrypt(int $ownerId, int $accountId, string $plaintext): string
{
    if ($plaintext === '' || strlen($plaintext) > 8192 || str_contains($plaintext, "\0")) {
        throw new AppMailCredentialException('value_invalid');
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
        throw new AppMailCredentialException('envelope_invalid');
    }

    $expectedKeyId = mail_crypto_key_id();
    if (!hash_equals($expectedKeyId, $parts[1])) {
        throw new AppMailCredentialException('key_mismatch');
    }

    $nonce = mail_crypto_base64url_decode($parts[2]);
    $ciphertext = mail_crypto_base64url_decode($parts[3]);
    if (!is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || !is_string($ciphertext)) {
        throw new AppMailCredentialException('envelope_invalid');
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
            throw new AppMailCredentialException('decrypt_failed');
        }
        return $plaintext;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}

// V1.34-B keeps the existing IMAP credential format untouched and gives an
// independently stored SMTP password a separate AEAD associated-data domain.
function mail_crypto_smtp_aad(int $ownerId, int $accountId): string
{
    if ($ownerId <= 0 || $accountId <= 0) {
        throw new AppMailCredentialException('smtp_context_invalid');
    }
    return 'rss-reader:mail-account-smtp:' . $ownerId . ':' . $accountId . ':v1';
}

function mail_crypto_encrypt_smtp(int $ownerId, int $accountId, string $plaintext): string
{
    if ($plaintext === '' || strlen($plaintext) > 8192 || str_contains($plaintext, "\0")) {
        throw new AppMailCredentialException('smtp_value_invalid');
    }

    $key = mail_crypto_key();
    try {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            mail_crypto_smtp_aad($ownerId, $accountId),
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

function mail_crypto_decrypt_smtp(int $ownerId, int $accountId, string $envelope): string
{
    $parts = explode('.', $envelope);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        throw new AppMailCredentialException('smtp_envelope_invalid');
    }

    $expectedKeyId = mail_crypto_key_id();
    if (!hash_equals($expectedKeyId, $parts[1])) {
        throw new AppMailCredentialException('smtp_key_mismatch');
    }

    $nonce = mail_crypto_base64url_decode($parts[2]);
    $ciphertext = mail_crypto_base64url_decode($parts[3]);
    if (!is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || !is_string($ciphertext)) {
        throw new AppMailCredentialException('smtp_envelope_invalid');
    }

    $key = mail_crypto_key();
    try {
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            mail_crypto_smtp_aad($ownerId, $accountId),
            $nonce,
            $key
        );
        if (!is_string($plaintext) || $plaintext === '' || strlen($plaintext) > 8192 || str_contains($plaintext, "\0")) {
            throw new AppMailCredentialException('smtp_decrypt_failed');
        }
        return $plaintext;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}
