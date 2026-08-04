<?php

declare(strict_types=1);

/**
 * Search Feedで利用出来る共通RSS。
 * URLは実行時にも既存のFeed URL検証・SSRF対策を通す。
 * @return list<array{name:string,category:string,url:string}>
 */
function app_common_feed_list(): array
{
    return [
        ['name' => 'PHP.net Releases', 'category' => 'PHP・Web開発', 'url' => 'https://www.php.net/releases/feed.php'],
        ['name' => 'GitHub Blog', 'category' => '技術', 'url' => 'https://github.blog/feed/'],
        ['name' => 'MDN Blog', 'category' => 'PHP・Web開発', 'url' => 'https://developer.mozilla.org/en-US/blog/rss.xml'],
        ['name' => 'Publickey', 'category' => '技術', 'url' => 'https://www.publickey1.jp/atom.xml'],
        ['name' => 'JVN 新着情報', 'category' => 'セキュリティ', 'url' => 'https://jvn.jp/rss/jvn.rdf'],
    ];
}
