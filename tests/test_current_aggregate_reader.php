<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP: Aggregate Reader requires pdo_sqlite.\n";
    exit(0);
}

$cacheRoot = sys_get_temp_dir() . '/rss-aggregate-reader-' . bin2hex(random_bytes(8));
define('APP_FEED_CACHE_DIR', $cacheRoot . '/feed');
define('APP_FEED_CACHE_ENABLED', true);
define('APP_READER_FULL_TEXT_CACHE_DIR', $cacheRoot . '/reader');
define('APP_READER_FULL_TEXT_CACHE_ENABLED', true);

register_shutdown_function(static function () use ($cacheRoot): void {
    foreach ([APP_FEED_CACHE_DIR, APP_READER_FULL_TEXT_CACHE_DIR] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
        if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($cacheRoot)) rmdir($cacheRoot);
});

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/api.php';
require dirname(__DIR__) . '/app/all_rss_recent.php';
restore_exception_handler();

function aggregate_reader_check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
set_db_connection_for_testing($db);
$contentTable = db_table_identifier('content');
$metadataTable = db_table_identifier('feed_metadata');
$widgetTable = db_table_identifier('dashboard_widget');
$db->exec("CREATE TABLE {$contentTable} (content_id INTEGER, content_owner INTEGER, content_flag INTEGER,
    content_value TEXT, content_location INTEGER, content_style TEXT)");
$db->exec("CREATE TABLE {$metadataTable} (metadata_content_id INTEGER, feed_title TEXT, site_url TEXT, category_path TEXT)");
$db->exec("CREATE TABLE {$widgetTable} (widget_id INTEGER, widget_owner INTEGER, widget_type TEXT, widget_flag INTEGER, widget_config TEXT)");

$ownedUrl = 'https://example.test/feed.xml';
$db->prepare("INSERT INTO {$contentTable} VALUES (11, 7, 0, ?, 0, 'info')")->execute([$ownedUrl]);
$widgetInsert = $db->prepare("INSERT INTO {$widgetTable} VALUES (?,7,'search',0,?)");
$search = array_replace(search_feed_defaults(), ['query' => 'Reader', 'scope' => 'both', 'category' => 'セキュリティ']);
$widgetInsert->execute([21, dashboard_widget_encode_config($search)]);
$widgetInsert->execute([22, dashboard_widget_encode_config(all_rss_recent_config(10))]);

$common = search_feed_common_sources(7, $search['category'])[0];
$cache = new FeedCache(APP_FEED_CACHE_DIR, APP_FEED_CACHE_TTL_SECONDS, APP_HTTP_MAX_BYTES);
foreach ([[$ownedUrl, 11, 'Owned'], [$common['url'], $common['source_id'], 'Common']] as [$url, $sourceId, $title]) {
    $articleUrl = 'https://example.test/' . strtolower($title);
    $rss = "<rss version='2.0'><channel><title>{$title}</title><link>https://example.test</link>"
        . "<description>Fixture</description><item><title>Reader article</title><link>{$articleUrl}</link>"
        . "<description>{$title} RSS body</description></item></channel></rss>";
    aggregate_reader_check($cache->writeSuccessfulFetch(FeedSource::fromValidatedValues($sourceId, 7, $url),
        ['ok' => true, 'status' => 200, 'body' => $rss]), "Seed {$title} RSS cache");
}

$searchItems = array_column(search_feed_execute(7, 21)['items'], null, 'source_title');
$recentItems = all_rss_recent_execute(7, 22)['items'];
aggregate_reader_check(count($searchItems) === 2 && count($recentItems) === 1, 'Aggregators retain their source and item scopes');

foreach ([[$searchItems['Owned'], 'Owned'], [$searchItems['Common'], 'Common'], [$recentItems[0], 'Owned']] as [$item, $title]) {
    $input = $item['reader_source'] + ['item_identity' => $item['item_identity'], 'article_url' => 'https://ignored.test/'];
    $result = api_dispatch('feed.reader', 7, $input);
    aggregate_reader_check($result['status'] === 200 && $result['body']['data']['reader']['body'] === "{$title} RSS body",
        "Reader resolves {$title} article from the originating feed");
    aggregate_reader_check(api_dispatch('feed.reader', 8, $input)['status'] === 404, "Other users cannot open {$title} article context");
}

$item = $searchItems['Common'];
$commonInput = $item['reader_source'] + ['item_identity' => $item['item_identity'], 'article_url' => 'https://ignored.test/'];
$fullTextCache = new ReaderFullTextCache(APP_READER_FULL_TEXT_CACHE_DIR, APP_READER_FULL_TEXT_CACHE_TTL_SECONDS,
    APP_READER_FULL_TEXT_STALE_MAX_AGE_SECONDS, APP_HTTP_MAX_BYTES);
$articleUrl = 'https://example.test/common';
$fullText = str_repeat('The common article contains substantive text that the Reader can extract. ', 15);
aggregate_reader_check($fullTextCache->writeSuccessfulFetch($articleUrl, ['ok' => true, 'status' => 200,
    'url' => $articleUrl, 'content_type' => 'text/html', 'body' => '<article><p>' . $fullText . '</p></article>']), 'Seed common Full Text cache');
$result = api_dispatch('feed.reader.fulltext', 7, $commonInput);
aggregate_reader_check($result['status'] === 200 && str_contains($result['body']['data']['full_text']['html'], trim($fullText)),
    'Full Text resolves the common article URL from RSS');

$wrongCategory = search_feed_common_sources(7, '技術')[0]['common_source_key'];
aggregate_reader_check(api_dispatch('feed.reader', 7, array_replace($commonInput, ['common_source_key' => $wrongCategory]))['status'] === 404,
    'Common Reader rejects sources outside the current Search Feed category');
aggregate_reader_check(api_dispatch('feed.reader', 7, array_replace($commonInput, ['search_widget_id' => 22]))['status'] === 404,
    'Common Reader rejects an owned-only aggregator as a common source');
aggregate_reader_check(api_dispatch('feed.reader', 7, $commonInput + ['content_id' => 11])['status'] === 422,
    'Reader rejects an ambiguous source');
