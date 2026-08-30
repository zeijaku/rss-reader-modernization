<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$calendar = file_get_contents($root . '/public/js/calendar.js');
$drawer = file_get_contents($root . '/public/js/drawer-categories.js');
$version = file_get_contents($root . '/app/version.php');

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label) use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "PASS: {$label}\n";
        return;
    }
    $fail++;
    echo "FAIL: {$label}\n";
};

$check(is_string($calendar), 'calendar loader is readable');
$check(is_string($drawer), 'drawer organizer is readable');
$check(is_string($version), 'version marker is readable');
$check(str_contains((string) $version, "APP_ASSET_REVISION = '1.27.0-dev-f1'"), 'asset revision is F1 while E2 Drawer cache key remains valid');
$check(str_contains((string) $calendar, "drawer-categories.js?v=1.27.0-dev-e2"), 'Dashboard loader requests E2 Drawer script');
$check(!str_contains((string) $calendar, "drawer-categories.js?v=1.26.0"), 'stale V1.26 Drawer cache key removed');
$check(str_contains((string) $drawer, "text('File Library')"), 'Drawer organizer contains File Library label');
$check(str_contains((string) $drawer, ".attr('href', './file-library')"), 'Drawer organizer contains File Library route');
$check(str_contains((string) $drawer, 'ensureFileLibraryItem($menu);'), 'Drawer organizer executes File Library insertion');
$check(str_contains((string) $drawer, "data-drawer-categories', 'v1.27-e1"), 'E1 Drawer organizer payload retained');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
