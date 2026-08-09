<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (in_array('--help', $argv, true)) {
    echo "V1.9-B IMAP connection probe\n";
    echo "Required environment variables:\n";
    echo "  V19B_MAIL_HOST\n  V19B_MAIL_PORT (993 or 143)\n  V19B_MAIL_ENCRYPTION (ssl or starttls)\n";
    echo "  V19B_MAIL_USERNAME\n  V19B_MAIL_PASSWORD\n";
    echo "The password is never printed or logged by this tool.\n";
    exit(0);
}

$password = getenv('V19B_MAIL_PASSWORD');
$credentials = [
    'host' => getenv('V19B_MAIL_HOST'),
    'port' => getenv('V19B_MAIL_PORT'),
    'encryption' => getenv('V19B_MAIL_ENCRYPTION'),
    'username' => getenv('V19B_MAIL_USERNAME'),
];

if (!is_string($password) || $password === '') {
    fwrite(STDERR, "ERROR: V19B_MAIL_PASSWORD is not set.\n");
    exit(2);
}

try {
    $result = mail_client_test_credentials($credentials, $password);
} catch (Throwable $exception) {
    // Intentionally do not print the exception message or credential/endpoint data.
    fwrite(STDERR, 'ERROR: mail probe failed [' . $exception::class . "]\n");
    exit(1);
} finally {
    if (function_exists('sodium_memzero')) {
        sodium_memzero($password);
    }
}

if ($result['ok']) {
    echo "PASS: IMAP TLS/authentication/INBOX EXAMINE succeeded.\n";
    exit(0);
}

fwrite(STDERR, 'FAIL: ' . (string) $result['code'] . "\n");
exit(1);
