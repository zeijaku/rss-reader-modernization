<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "FAIL: vendor/autoload.php is missing. Run composer install.\n");
    exit(1);
}
require_once $autoload;

putenv('APP_MAIL_IMAP_TIMEOUT_SECONDS=5');
require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/mail/mail_client.php';

$checks = [
    'Mailbox class' => class_exists(DirectoryTree\ImapEngine\Mailbox::class),
    'ImapConnection class' => class_exists(DirectoryTree\ImapEngine\Connection\ImapConnection::class),
    'ImapStream class' => class_exists(DirectoryTree\ImapEngine\Connection\Streams\ImapStream::class),
    'Pinned stream adapter' => class_exists('AppMailPinnedImapStream'),
    'mail_client_available' => mail_client_available(),
];

if (class_exists(Composer\InstalledVersions::class)) {
    $pretty = Composer\InstalledVersions::getPrettyVersion('directorytree/imapengine');
    $checks['ImapEngine 1.25.3 installed'] = in_array($pretty, ['1.25.3', 'v1.25.3'], true);
} else {
    $checks['Composer InstalledVersions'] = false;
}

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) {
        $failed[] = $label;
    }
}
if ($failed !== []) {
    exit(1);
}
echo "PASS: V1.9-B ImapEngine dependency check\n";
