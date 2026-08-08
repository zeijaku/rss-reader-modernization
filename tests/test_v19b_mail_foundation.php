<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$key = base64_encode(str_repeat("K", 32));
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('DB_TABLE_PREFIX=test_');
putenv('APP_MAIL_CREDENTIAL_KEY_ID=testkey');
putenv('APP_MAIL_CREDENTIAL_KEY_B64=' . $key);
putenv('APP_MAIL_IMAP_TIMEOUT_SECONDS=5');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/http_fetch.php';
require_once $root . '/app/mail/mail_crypto.php';
require_once $root . '/app/mail/mail_target.php';
require_once $root . '/app/mail/mail_account.php';
require_once $root . '/app/mail/mail_client.php';
require_once $root . '/app/mail/mail_service.php';

$failures = [];
function v19b_check(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$condition) {
        $failures[] = $label;
    }
}

v19b_check(function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt'), 'Sodium XChaCha20-Poly1305 available');

$encrypted = mail_crypto_encrypt(7, 11, 'secret-password');
v19b_check(str_starts_with($encrypted, 'v1.testkey.'), 'Credential envelope version and key id');
v19b_check(!str_contains($encrypted, 'secret-password'), 'Credential envelope does not contain plaintext');
v19b_check(mail_crypto_decrypt(7, 11, $encrypted) === 'secret-password', 'Credential decrypt round trip');

try {
    mail_crypto_decode_key('');
    v19b_check(false, 'Missing credential key rejected');
} catch (AppMailCredentialException) {
    v19b_check(true, 'Missing credential key rejected');
}
try {
    mail_crypto_decode_key(base64_encode(str_repeat('X', 31)));
    v19b_check(false, 'Wrong-length credential key rejected');
} catch (AppMailCredentialException) {
    v19b_check(true, 'Wrong-length credential key rejected');
}
$wrongKeyIdEnvelope = preg_replace('/\Av1\.testkey\./', 'v1.otherkey.', $encrypted);
try {
    mail_crypto_decrypt(7, 11, is_string($wrongKeyIdEnvelope) ? $wrongKeyIdEnvelope : '');
    v19b_check(false, 'Credential key-id mismatch rejected');
} catch (AppMailCredentialException) {
    v19b_check(true, 'Credential key-id mismatch rejected');
}

try {
    mail_crypto_decrypt(8, 11, $encrypted);
    v19b_check(false, 'Credential owner AAD binding');
} catch (AppMailCredentialException) {
    v19b_check(true, 'Credential owner AAD binding');
}

$tampered = substr($encrypted, 0, -1) . (substr($encrypted, -1) === 'A' ? 'B' : 'A');
try {
    mail_crypto_decrypt(7, 11, $tampered);
    v19b_check(false, 'Credential tamper rejection');
} catch (AppMailCredentialException) {
    v19b_check(true, 'Credential tamper rejection');
}

$publicResolver = static fn (string $host): array => ['93.184.216.34'];
$privateResolver = static fn (string $host): array => ['127.0.0.1'];
$mixedResolver = static fn (string $host): array => ['93.184.216.34', '10.0.0.1'];

$ssl = mail_validate_target('imap.example.com', 993, 'ssl', $publicResolver);
v19b_check($ssl['ok'] && $ssl['port'] === 993 && $ssl['encryption'] === 'ssl', 'SSL/993 target accepted');
$starttls = mail_validate_target('imap.example.com', 143, 'starttls', $publicResolver);
v19b_check($starttls['ok'] && $starttls['port'] === 143, 'STARTTLS/143 target accepted');
v19b_check(!mail_validate_target('localhost', 993, 'ssl', $publicResolver)['ok'], 'Single-label localhost rejected');
v19b_check(mail_validate_target('imap.example.com', 993, 'ssl', $privateResolver)['error_code'] === 'non_public_address', 'Private IP rejected');
v19b_check(mail_validate_target('imap.example.com', 993, 'ssl', $mixedResolver)['error_code'] === 'non_public_address', 'Mixed public/private DNS rejected');
v19b_check(mail_validate_target('imap.example.com', 143, 'ssl', $publicResolver)['error_code'] === 'invalid_transport', 'Wrong SSL port rejected');
v19b_check(mail_validate_target('imap.example.com', 993, 'starttls', $publicResolver)['error_code'] === 'invalid_transport', 'Wrong STARTTLS port rejected');

$context = mail_client_tls_context_options('imap.example.com');
v19b_check(($context['ssl']['verify_peer'] ?? false) === true, 'TLS peer verification forced');
v19b_check(($context['ssl']['verify_peer_name'] ?? false) === true, 'TLS peer-name verification forced');
v19b_check(($context['ssl']['allow_self_signed'] ?? true) === false, 'Self-signed certificates rejected');
v19b_check(($context['ssl']['peer_name'] ?? '') === 'imap.example.com', 'Original hostname retained as TLS peer_name');
v19b_check(mail_client_socket_address('ssl', '93.184.216.34', 993) === 'ssl://93.184.216.34:993', 'Pinned IPv4 socket address');
v19b_check(mail_client_socket_address('tcp', '2606:2800:220:1:248:1893:25c8:1946', 143) === 'tcp://[2606:2800:220:1:248:1893:25c8:1946]:143', 'Pinned IPv6 socket address');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = conn_db();
    $pdo->exec(
        'CREATE TABLE test_mail_account ('
        . 'mail_account_id INTEGER PRIMARY KEY AUTOINCREMENT,'
        . 'mail_account_owner INTEGER NOT NULL,'
        . 'mail_account_display_name TEXT NOT NULL,'
        . 'mail_account_host TEXT NOT NULL,'
        . 'mail_account_port INTEGER NOT NULL,'
        . 'mail_account_encryption TEXT NOT NULL,'
        . 'mail_account_username TEXT NOT NULL,'
        . 'mail_account_secret TEXT NOT NULL,'
        . 'mail_account_enabled INTEGER NOT NULL DEFAULT 1,'
        . 'mail_account_flag INTEGER NOT NULL DEFAULT 0,'
        . 'mail_account_created_at TEXT NOT NULL,'
        . 'mail_account_updated_at TEXT NOT NULL'
        . ')'
    );

    // Avoid real DNS in CRUD test by using a public literal address. TLS connection is not attempted.
    $created = mail_account_create(10, [
        'display_name' => 'Test Mail',
        'host' => '93.184.216.34',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'user@example.com',
        'password' => 'db-secret',
        'enabled' => 1,
    ]);
    $accountId = (int) ($created['mail_account_id'] ?? 0);
    v19b_check($accountId > 0, 'Mail account create');
    v19b_check(!array_key_exists('mail_account_secret', $created) && !array_key_exists('password', $created), 'Create response excludes credential');

    $stored = mail_account_find_owned(10, $accountId, true, false);
    v19b_check(is_array($stored) && (string) $stored['mail_account_secret'] !== 'db-secret', 'Database stores encrypted credential');
    v19b_check(is_array($stored) && mail_crypto_decrypt(10, $accountId, (string) $stored['mail_account_secret']) === 'db-secret', 'Stored credential decrypts for owner/account');
    v19b_check(mail_account_find_owned(11, $accountId, true, false) === null, 'Cross-user account read denied');

    $updated = mail_account_update(10, $accountId, [
        'display_name' => 'Updated Mail',
        'host' => '93.184.216.34',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'user@example.com',
        'password' => '',
        'enabled' => 1,
    ]);
    v19b_check(is_array($updated) && ($updated['display_name'] ?? '') === 'Updated Mail', 'Mail account update without password');
    $storedAfter = mail_account_find_owned(10, $accountId, true, false);
    v19b_check(is_array($storedAfter) && mail_crypto_decrypt(10, $accountId, (string) $storedAfter['mail_account_secret']) === 'db-secret', 'Blank update password preserves credential');
    v19b_check(mail_account_update(11, $accountId, [
        'display_name' => 'Other', 'host' => '93.184.216.34', 'port' => 993,
        'encryption' => 'ssl', 'username' => 'other@example.com', 'password' => '', 'enabled' => 1,
    ]) === null, 'Cross-user account update denied');
    v19b_check(mail_account_delete(11, $accountId) === false, 'Cross-user account delete denied');
    v19b_check(mail_account_delete(10, $accountId) === true, 'Owned account soft delete');
    v19b_check(mail_account_find_owned(10, $accountId, false, false) === null, 'Soft-deleted account hidden');
} else {
    echo "SKIP: pdo_sqlite unavailable; DB ownership CRUD is exercised in CI.\n";
}

if ($failures !== []) {
    fwrite(STDERR, 'V1.9-B foundation failures: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo "PASS: V1.9-B Mail foundation targeted tests\n";
