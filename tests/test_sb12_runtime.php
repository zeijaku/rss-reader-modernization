<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$failures = 0;
function runtime_check(bool $condition, string $message): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

$root = dirname(__DIR__);
$tmpRoot = sys_get_temp_dir() . '/iguguru-sb12-' . bin2hex(random_bytes(6));
if (!mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
    throw new RuntimeException('Could not create temporary test directory.');
}

try {
    define('APP_LOG_ENABLED', true);
    define('APP_LOG_PATH', $tmpRoot . '/access.log');

    require_once $root . '/app/validation.php';
    require_once $root . '/app/common/common_func.php';
    require_once $root . '/app/common/common_db.php';

    runtime_check(rss_check_string(null) === 'invalid', 'rss_check_string accepts null without PHP 8 warning/type failure');
    runtime_check(rss_check_string('') === 'invalid', 'rss_check_string rejects empty input deterministically');
    runtime_check(rss_check_string('<rss version="2.0"></rss>') === 'rss', 'rss_check_string recognizes RSS root hint');

    runtime_check(rss_normalize_date(null) === null, 'rss_normalize_date handles null safely');
    runtime_check(rss_normalize_date(false) === null, 'rss_normalize_date handles false safely');
    runtime_check(rss_normalize_date('') === null, 'rss_normalize_date handles empty string safely');
    runtime_check(rss_normalize_date('definitely-not-a-date') === null, 'rss_normalize_date rejects malformed date without warning');
    runtime_check(rss_normalize_date('2026-07-30T12:34:56+09:00') === '2026-07-30 12:34:56', 'rss_normalize_date formats a valid feed date');

    $parser = new rss_parse();
    runtime_check($parser->parse_start(null) === [] && $parser->last_error !== null, 'feed parser handles null body without warning');
    runtime_check($parser->parse_start('') === [] && $parser->last_error !== null, 'feed parser handles empty body without warning');
    $plainResult = $parser->parse_start('plain text, not XML');
    runtime_check($plainResult === [] && $parser->last_error !== null, 'feed parser rejects non-feed input cleanly even when optional extensions are unavailable');

    runtime_check(app_selected_attr('dark', 'dark') === ' selected="selected"', 'selected helper renders exact stored value');
    runtime_check(app_selected_attr(null, 'dark') === '', 'selected helper handles null safely');
    runtime_check(app_checked_attr('home', 'home') === ' checked="checked"', 'checked helper renders exact stored value');
    runtime_check(app_checked_attr([], 'home') === '', 'checked helper handles non-string Legacy value safely');

    $originalServer = $_SERVER;
    $_SERVER = [];
    access_log();
    $_SERVER = $originalServer;
    $log = is_file(APP_LOG_PATH) ? (string) file_get_contents(APP_LOG_PATH) : '';
    runtime_check($log !== '' && str_contains($log, '"- - -"'), 'access log tolerates missing request metadata including User-Agent');

    $reflection = new ReflectionFunction('update_setting');
    $seenOptional = false;
    $requiredAfterOptional = false;
    foreach ($reflection->getParameters() as $parameter) {
        if ($parameter->isOptional()) {
            $seenOptional = true;
        } elseif ($seenOptional) {
            $requiredAfterOptional = true;
            break;
        }
    }
    runtime_check(!$requiredAfterOptional, 'update_setting has no optional parameter before a required parameter');

    runtime_check(PHP_VERSION_ID >= 80100, 'test runtime satisfies application PHP 8.1+ floor');
} finally {
    restore_error_handler();
    if (is_file($tmpRoot . '/access.log')) {
        unlink($tmpRoot . '/access.log');
    }
    @rmdir($tmpRoot);
}

if ($failures > 0) {
    exit(1);
}

echo "All SB-12 PHP 8 runtime checks passed." . PHP_EOL;
