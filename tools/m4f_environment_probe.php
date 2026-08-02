<?php

declare(strict_types=1);

/**
 * M4-F Release Candidate environment probe.
 *
 * This command prints only non-secret runtime capability information.
 * It does not print DB credentials, APP_HASH_KEY, Cookie, Session ID or Feed URL.
 */

require_once dirname(__DIR__) . '/app/version.php';

$requiredExtensions = [
    'pdo',
    'pdo_mysql',
    'curl',
    'simplexml',
    'mbstring',
];
$runtimeDirectories = [
    'var/session',
    'var/log',
    'var/cache/feed',
    'var/db-migration',
    'var/security/login-throttle',
];

$extensions = [];
foreach ($requiredExtensions as $extension) {
    $extensions[$extension] = extension_loaded($extension);
}

$directories = [];
$root = dirname(__DIR__);
foreach ($runtimeDirectories as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $directories[$relative] = [
        'exists' => is_dir($path),
        'writable' => is_dir($path) && is_writable($path),
    ];
}

$pdoDrivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
sort($pdoDrivers);

$phpReady = version_compare(PHP_VERSION, '8.1.0', '>=');
$extensionsReady = !in_array(false, $extensions, true);
$directoriesReady = true;
foreach ($directories as $state) {
    if (!$state['exists'] || !$state['writable']) {
        $directoriesReady = false;
        break;
    }
}

$report = [
    'schema_version' => 1,
    'checkpoint' => APP_VERSION,
    'label' => APP_VERSION_LABEL,
    'status' => ($phpReady && $extensionsReady && $directoriesReady) ? 'PASS' : 'HOLD',
    'php' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'minimum_8_1' => $phpReady,
    ],
    'required_extensions' => $extensions,
    'pdo_drivers' => $pdoDrivers,
    'runtime_directories' => $directories,
    'notes' => [
        'Database connection is not attempted by this probe.',
        'Run php tools/db_sb13.php verify separately.',
        'No credential, Cookie, Session ID or Feed URL is included.',
    ],
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "ERROR: failed to encode M4-F environment report\n");
    exit(1);
}

echo $json . PHP_EOL;

if (in_array('--require-ready', $argv, true) && $report['status'] !== 'PASS') {
    exit(2);
}
