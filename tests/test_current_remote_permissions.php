<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/remote_file/remote_exception.php';
require_once $root . '/app/remote_file/remote_path.php';
require_once $root . '/app/remote_file/remote_permission_provider.php';
require_once $root . '/app/remote_file/remote_listing.php';

$pass = 0;
$fail = 0;

function check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
        return;
    }
    $fail++;
    echo "FAIL: {$message}\n";
}

check(remote_permission_normalize_mode('000') === '000', 'minimum chmod mode is accepted');
check(remote_permission_normalize_mode('600') === '600', 'file preset 600 is accepted');
check(remote_permission_normalize_mode('640') === '640', 'file preset 640 is accepted');
check(remote_permission_normalize_mode('644') === '644', 'file preset 644 is accepted');
check(remote_permission_normalize_mode('700') === '700', 'directory preset 700 is accepted');
check(remote_permission_normalize_mode('750') === '750', 'directory preset 750 is accepted');
check(remote_permission_normalize_mode('755') === '755', 'directory preset 755 is accepted');
check(remote_permission_normalize_mode('777') === '777', 'maximum chmod mode is accepted');

foreach (['0644', '64', '6444', '888', '64x', ' 644', '644 ', '', "64\n"] as $invalid) {
    check(remote_permission_normalize_mode($invalid) === null, 'invalid chmod mode is rejected: ' . json_encode($invalid));
}
check(remote_permission_normalize_mode(644) === null, 'non-string chmod mode is rejected');

$mlsd = remote_listing_parse(
    "type=file;size=123;modify=20260903120000;UNIX.mode=0640; sample file.php\r\n"
    . "type=dir;modify=20260903120100;unix.mode=755; assets\r\n",
    'mlsd'
);
check(count($mlsd) === 2, 'MLSD parser keeps file and directory entries');
check(($mlsd[0]['name'] ?? null) === 'sample file.php', 'MLSD parser preserves spaces in file names');
check(($mlsd[0]['permission_mode'] ?? null) === '640', 'MLSD UNIX.mode 0640 becomes 640');
check(($mlsd[1]['type'] ?? null) === 'directory', 'MLSD directory type is preserved');
check(($mlsd[1]['permission_mode'] ?? null) === '755', 'MLSD UNIX.mode 755 is accepted');

$mlsdSpecial = remote_listing_parse_mlsd_line('type=file;size=1;UNIX.mode=4755; special.php');
check(is_array($mlsdSpecial), 'MLSD entry with special bits remains listable');
check(!array_key_exists('permission_mode', $mlsdSpecial), 'MLSD special-bit mode is not reduced to a three-digit chmod mode');

$unix = remote_listing_parse(
    "-rw-r----- 1 user group 123 Sep 3 12:00 sample file.php\n"
    . "drwxr-xr-x 2 user group 4096 Sep 3 12:01 assets\n"
    . "lrwxrwxrwx 1 user group 10 Sep 3 12:02 current -> releases/v1\n",
    'unix'
);
check(count($unix) === 3, 'Unix LIST parser keeps file, directory and symlink entries');
check(($unix[0]['permission_symbolic'] ?? null) === 'rw-r-----', 'Unix LIST symbolic permission is preserved');
check(($unix[0]['permission_mode'] ?? null) === '640', 'Unix LIST symbolic permission becomes numeric mode');
check(($unix[1]['permission_mode'] ?? null) === '755', 'Unix LIST directory mode is parsed');
check(($unix[2]['type'] ?? null) === 'symlink', 'Unix LIST symlink remains identifiable');
check(($unix[2]['name'] ?? null) === 'current', 'Unix LIST symlink target is removed from display name');

$special = remote_listing_parse_unix_line('-rwsr-xr-x 1 user group 10 Sep 3 12:03 special.php');
check(is_array($special), 'Unix LIST special-bit entry remains listable');
check(($special['permission_symbolic'] ?? null) === 'rwsr-xr-x', 'Unix LIST special bits remain symbolic');
check(array_key_exists('permission_mode', $special) && $special['permission_mode'] === null, 'Unix LIST special bits do not invent a numeric mode');

check(remote_path_normalize_relative('/safe/path') === '/safe/path', 'normal remote relative path remains valid');
check(remote_path_normalize_relative('/safe/../escape') === null, 'remote traversal path is rejected');
check(remote_path_normalize_relative("/safe/line\nbreak") === null, 'remote path with control characters is rejected');

printf("RESULT: PASS %d / FAIL %d / SKIP 0\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
