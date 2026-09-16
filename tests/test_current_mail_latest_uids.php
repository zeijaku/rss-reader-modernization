<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/mail/mail_client.php';

final class CurrentMailUidToken
{
    public function __construct(public string $value)
    {
    }
}

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$tokens = [
    new CurrentMailUidToken('99999'),
    new CurrentMailUidToken('100000'),
    new CurrentMailUidToken('100001'),
    new CurrentMailUidToken('100000'),
    new CurrentMailUidToken('7'),
    new CurrentMailUidToken('0'),
    new CurrentMailUidToken('not-a-uid'),
    new CurrentMailUidToken('4294967296'),
];

$check(
    mail_client_latest_uid_values($tokens, 3) === [100001, 100000, 99999],
    'UIDs are de-duplicated and sorted numerically before applying the display limit.'
);
$check(
    mail_client_latest_uid_values([3, '12', 2], 10) === [12, 3, 2],
    'Integer and numeric-string UID values are accepted.'
);
$check(mail_client_latest_uid_values($tokens, 0) === [], 'A non-positive limit returns no UIDs.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS: current Mail latest UID ordering\n";
