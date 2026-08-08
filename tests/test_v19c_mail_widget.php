<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=:memory:');
putenv('DB_TABLE_PREFIX=test_');
putenv('APP_MAIL_CREDENTIAL_KEY_ID=testkey');
putenv('APP_MAIL_CREDENTIAL_KEY_B64=' . base64_encode(str_repeat('M', 32)));

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/dashboard_widget.php';
require_once $root . '/app/http_fetch.php';
require_once $root . '/app/mail/mail_crypto.php';
require_once $root . '/app/mail/mail_target.php';
require_once $root . '/app/mail/mail_account.php';
require_once $root . '/app/mail/mail_client.php';
require_once $root . '/app/mail/mail_service.php';
require_once $root . '/app/mail/mail_widget.php';

$failures = [];
function v19c_check(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$condition) { $failures[] = $label; }
}

v19c_check(mail_widget_validate_item_limit('5') === 5, 'Mail Widget limit 5 accepted');
v19c_check(mail_widget_validate_item_limit('10') === 10, 'Mail Widget limit 10 accepted');
v19c_check(mail_widget_validate_item_limit('20') === null, 'Mail Widget unsupported limit rejected');
v19c_check(mail_widget_validate_title('Inbox') === 'Inbox', 'Mail Widget title accepted');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: pdo_sqlite unavailable\n";
    exit($failures === [] ? 0 : 1);
}

$pdo = conn_db();
$pdo->exec('CREATE TABLE test_mail_account (mail_account_id INTEGER PRIMARY KEY AUTOINCREMENT, mail_account_owner INTEGER NOT NULL, mail_account_display_name TEXT NOT NULL, mail_account_host TEXT NOT NULL, mail_account_port INTEGER NOT NULL, mail_account_encryption TEXT NOT NULL, mail_account_username TEXT NOT NULL, mail_account_secret TEXT NOT NULL, mail_account_enabled INTEGER NOT NULL DEFAULT 1, mail_account_flag INTEGER NOT NULL DEFAULT 0, mail_account_created_at TEXT NOT NULL, mail_account_updated_at TEXT NOT NULL)');
$pdo->exec("CREATE TABLE test_dashboard_widget (widget_id INTEGER PRIMARY KEY AUTOINCREMENT, widget_owner INTEGER NOT NULL, widget_location INTEGER NOT NULL DEFAULT 0, widget_type TEXT NOT NULL, widget_reference_id INTEGER NULL, widget_sort_order INTEGER NOT NULL DEFAULT 0, widget_width INTEGER NOT NULL DEFAULT 1, widget_height INTEGER NOT NULL DEFAULT 1, widget_style TEXT NOT NULL DEFAULT 'success', widget_config TEXT NULL, widget_flag INTEGER NOT NULL DEFAULT 0, widget_created_at TEXT NOT NULL, widget_updated_at TEXT NOT NULL, UNIQUE(widget_owner, widget_type, widget_reference_id))");

$account = mail_account_create(10, [
    'display_name' => 'Work Mail', 'host' => '93.184.216.34', 'port' => 993,
    'encryption' => 'ssl', 'username' => 'user@example.com', 'password' => 'mail-secret', 'enabled' => 1,
]);
$accountId = (int) ($account['mail_account_id'] ?? 0);
$widgetId = mail_widget_create(10, $accountId, 2, 'primary', 2, 1, ['schema' => 1, 'title' => 'Inbox', 'item_limit' => 5]);
v19c_check($widgetId > 0, 'Mail Widget create');

$list = mail_widget_list(10, 2);
v19c_check(count($list) === 1, 'Mail Widget list');
v19c_check(($list[0]['mail_account_id'] ?? 0) === $accountId, 'Mail Widget account reference');
v19c_check(($list[0]['widget_config']['title'] ?? '') === 'Inbox', 'Mail Widget config round trip');
v19c_check(mail_widget_list(11, 2) === [], 'Cross-user Mail Widget list denied');
v19c_check(mail_widget_find_owned(11, $widgetId) === null, 'Cross-user Mail Widget read denied');

v19c_check(mail_widget_update(11, $widgetId, $accountId, 'dark', 1, 1, ['schema' => 1, 'title' => 'Other', 'item_limit' => 5]) === false, 'Cross-user Mail Widget update denied');
v19c_check(mail_widget_update(10, $widgetId, $accountId, 'dark', 1, 2, ['schema' => 1, 'title' => 'Updated Inbox', 'item_limit' => 10]) === true, 'Owned Mail Widget update');
$updated = mail_widget_list(10, 2);
v19c_check(($updated[0]['widget_height'] ?? 0) === 2 && ($updated[0]['widget_config']['item_limit'] ?? 0) === 10, 'Mail Widget size and limit update');
v19c_check(mail_widget_delete(11, $widgetId) === false, 'Cross-user Mail Widget delete denied');
v19c_check(mail_widget_delete(10, $widgetId) === true, 'Owned Mail Widget soft delete');
v19c_check(mail_widget_list(10, 2) === [], 'Deleted Mail Widget hidden');

$revivedId = mail_widget_create(10, $accountId, 1, 'info', 1, 1, ['schema' => 1, 'title' => 'Revived', 'item_limit' => 5]);
v19c_check($revivedId === $widgetId, 'Soft-deleted Mail Widget revived without duplicate');
v19c_check(mail_widget_list(10, 1)[0]['widget_id'] === $widgetId, 'Revived Mail Widget moved to requested tab');

if ($failures !== []) {
    fwrite(STDERR, 'V1.9-C failures: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: V1.9-C Mail Widget targeted tests\n";
