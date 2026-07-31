<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('REGISTRATION_ENABLED=false');
require $root . '/app/common/common_conf.php';
require $root . '/app/auth.php';
$result = auth_register('new@example.com', 'correct horse battery staple');
if (($result['ok'] ?? true) !== false || ($result['reason'] ?? '') !== 'registration_disabled') {
    fwrite(STDERR, "FAIL: registration disabled switch\n");
    exit(1);
}
echo "PASS: registration disabled switch rejects registration before DB access\n";
