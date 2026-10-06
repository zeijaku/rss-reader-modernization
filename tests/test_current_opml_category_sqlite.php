<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite') || !function_exists('simplexml_load_string')) {
    echo "RESULT: PASS 0 / FAIL 0 / SKIP 1 (pdo_sqlite or SimpleXML unavailable)\n";
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function conn_db(): PDO { global $pdo; return $pdo; }
function db_table_identifier(string $name): string { return 'rss_' . $name; }
function app_now(): string { return '2026-10-06 15:00:00'; }
function api_success(array $data = [], int $status = 200): array { return ['status'=>$status,'body'=>['ok'=>true,'data'=>$data]]; }
function api_error(string $code, string $message, int $status): array { return ['status'=>$status,'body'=>['ok'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
function api_validation_error(string $message): array { return api_error('validation_error',$message,422); }

require_once dirname(__DIR__) . '/app/validation.php';
require_once dirname(__DIR__) . '/app/feed_metadata.php';
require_once dirname(__DIR__) . '/app/opml.php';

function dashboard_widget_create_feed(
    int $ownerId,
    string $feedUrl,
    string $style,
    int $location,
    int $width,
    int $height,
    string $itemLimit
): int {
    $pdo = conn_db();
    $stmt = $pdo->prepare(
        'INSERT INTO rss_content (content_owner,content_flag,content_value,content_location,content_style) '
        . 'VALUES (:owner,0,:url,:location,:style)'
    );
    $stmt->execute([
        ':owner'=>$ownerId,
        ':url'=>$feedUrl,
        ':location'=>$location,
        ':style'=>$style,
    ]);
    return (int) $pdo->lastInsertId();
}

require_once dirname(__DIR__) . '/app/api/opml.php';

$pdo->exec(
    'CREATE TABLE rss_content ('
    . 'content_id INTEGER PRIMARY KEY AUTOINCREMENT,'
    . 'content_owner INTEGER NOT NULL,'
    . 'content_flag INTEGER NOT NULL DEFAULT 0,'
    . 'content_value TEXT NOT NULL,'
    . 'content_location INTEGER NOT NULL DEFAULT 0,'
    . 'content_style TEXT NOT NULL DEFAULT "success"'
    . ')'
);
$pdo->exec(
    'CREATE TABLE rss_feed_metadata ('
    . 'metadata_content_id INTEGER PRIMARY KEY,'
    . 'feed_title TEXT NOT NULL DEFAULT "",'
    . 'site_url TEXT NOT NULL DEFAULT "",'
    . 'category_path TEXT NOT NULL DEFAULT "",'
    . 'created_at TEXT NOT NULL,'
    . 'updated_at TEXT NOT NULL'
    . ')'
);

$pdo->exec(
    "INSERT INTO rss_content (content_id,content_owner,content_flag,content_value,content_location,content_style) VALUES "
    . "(1,7,0,'https://example.test/existing.xml',0,'success'),"
    . "(2,8,0,'https://example.test/other-owner.xml',0,'success')"
);
$pdo->exec(
    "INSERT INTO rss_feed_metadata (metadata_content_id,feed_title,site_url,category_path,created_at,updated_at) VALUES "
    . "(1,'Existing','','Existing Category','2026-10-01','2026-10-01'),"
    . "(2,'Other owner','','Other Category','2026-10-01','2026-10-01')"
);

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Import test</title></head>
  <body>
    <outline text="Imported">
      <outline type="rss" text="Existing duplicate" xmlUrl="https://example.test/existing.xml" />
      <outline type="rss" text="New first" xmlUrl="https://example.test/new.xml" />
      <outline type="rss" text="New duplicate" xmlUrl="https://example.test/new.xml" category="/Ignored/Second" />
    </outline>
    <outline type="rss" text="Other owner same URL" xmlUrl="https://example.test/other-owner.xml" category="/Private/Copy" />
  </body>
</opml>
XML;

$tmp = tempnam(sys_get_temp_dir(), 'opml-v145d-');
if ($tmp === false) {
    throw new RuntimeException('tempnam failed');
}
file_put_contents($tmp, $xml);
$_FILES['opml_file'] = [
    'name' => 'test.opml',
    'type' => 'text/x-opml',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($xml),
];

$pass = 0;
$fail = 0;
function v145d_api_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) { $pass++; echo "PASS: {$message}\n"; }
    else { $fail++; echo "FAIL: {$message}\n"; }
}

try {
    $response = api_opml_import(7);
    $data = $response['body']['data'] ?? [];

    v145d_api_check(($response['status'] ?? 0) === 200 && ($response['body']['ok'] ?? false) === true, 'OPML import succeeds');
    v145d_api_check(($data['added'] ?? null) === 2, 'Import adds new URL and another-owner URL for current owner');
    v145d_api_check(($data['duplicate'] ?? null) === 2, 'Import counts existing owned URL and same-file repeated URL as duplicates');
    v145d_api_check(($data['failure'] ?? null) === 0, 'Valid import has no failures');

    $existing = $pdo->query("SELECT category_path FROM rss_feed_metadata WHERE metadata_content_id=1")->fetchColumn();
    v145d_api_check($existing === 'Existing Category', 'Existing owned Feed keeps its current Category on duplicate import');

    $newRow = $pdo->query(
        "SELECT c.content_id,m.feed_title,m.category_path FROM rss_content c "
        . "JOIN rss_feed_metadata m ON m.metadata_content_id=c.content_id "
        . "WHERE c.content_owner=7 AND c.content_value='https://example.test/new.xml'"
    )->fetch();
    v145d_api_check(is_array($newRow) && $newRow['category_path'] === 'Imported', 'First occurrence Category is applied to newly imported Feed');
    v145d_api_check(is_array($newRow) && $newRow['feed_title'] === 'New first', 'First occurrence title is retained for same-file duplicate');

    $otherOwnerCopy = $pdo->query(
        "SELECT m.category_path FROM rss_content c JOIN rss_feed_metadata m ON m.metadata_content_id=c.content_id "
        . "WHERE c.content_owner=7 AND c.content_value='https://example.test/other-owner.xml'"
    )->fetchColumn();
    v145d_api_check($otherOwnerCopy === 'Private / Copy', 'Other owner URL does not block current user import and flat Category is applied');

    $otherOwnerOriginal = $pdo->query(
        "SELECT m.category_path FROM rss_content c JOIN rss_feed_metadata m ON m.metadata_content_id=c.content_id "
        . "WHERE c.content_owner=8 AND c.content_value='https://example.test/other-owner.xml'"
    )->fetchColumn();
    v145d_api_check($otherOwnerOriginal === 'Other Category', 'Import never changes another owner metadata');

    $listResponse = api_opml_list(7);
    $listFeeds = $listResponse['body']['data']['feeds'] ?? [];
    $categories = array_column($listFeeds, 'category_path', 'feed_url');
    v145d_api_check(($categories['https://example.test/existing.xml'] ?? null) === 'Existing Category', 'OPML list returns preserved existing Category');
    v145d_api_check(($categories['https://example.test/new.xml'] ?? null) === 'Imported', 'OPML list exposes imported Category for A/B/C selectors');

    $exportResponse = api_opml_export(7);
    $exportData = $exportResponse['body']['data'] ?? [];
    v145d_api_check(($exportData['count'] ?? null) === 3, 'Export includes current user active Feeds only');
    $roundTrip = opml_parse((string) ($exportData['content'] ?? ''));
    $roundTripCategories = [];
    foreach ($roundTrip['feeds'] as $feed) {
        $roundTripCategories[$feed['feed_url']] = $feed['category_path'];
    }
    v145d_api_check(($roundTripCategories['https://example.test/existing.xml'] ?? null) === 'Existing Category', 'Export -> Parse keeps existing Category');
    v145d_api_check(($roundTripCategories['https://example.test/new.xml'] ?? null) === 'Imported', 'Export -> Parse keeps imported Category');
    v145d_api_check(($roundTripCategories['https://example.test/other-owner.xml'] ?? null) === 'Private / Copy', 'Export -> Parse keeps slash hierarchy Category');

    $foreignPresent = false;
    foreach ($listFeeds as $feed) {
        if (($feed['content_id'] ?? null) === 2) $foreignPresent = true;
    }
    v145d_api_check(!$foreignPresent, 'OPML list does not leak another owner Feed');
} finally {
    @unlink($tmp);
    unset($_FILES['opml_file']);
}

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $fail === 0 ? 'PASS' : 'FAIL', $pass, $fail);
exit($fail === 0 ? 0 : 1);
