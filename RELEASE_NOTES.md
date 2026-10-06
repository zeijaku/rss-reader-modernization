# RSS Reader Modernization 1.45.0

V1.45.0は、RSS Feed単位のCategory管理をRSS管理、全RSS新着、Search Feed、OPMLへ一貫して接続するFeature Releaseです。Application Version / Asset Revision は `1.45.0`。既存の `feed_metadata.category_path` を使用するため、V1.44.0からのDatabase Migrationと必須Config変更はありません。

## Feed Category management

- Categoryは記事ではなくRSS Feedへ紐付けます。
- RSS管理でFeedごとにCategoryを設定・解除出来ます。
- 既存Categoryの選択に加えて、新しいCategory pathをその場で入力出来ます。
- 登録RSS一覧を `すべて` / `未分類` / 任意Categoryで絞り込めます。
- Category名のRenameは、そのCategoryを持つ認証Userのactive Feedへまとめて反映します。
- Category削除はFeed自体を削除せず、該当Feedを未分類へ戻します。
- Category専用Tableは追加せず、既存の `feed_metadata.category_path` を正本として使用します。

## All RSS Recent

- 全RSS新着Widgetで `すべて` / `未分類` / 任意のFeed Categoryを選択出来ます。
- Category指定時は、記事取得後に捨てるのではなく、対象Feedを絞ってからRSS取得を行います。
- V1.45.0より前に作成したWidgetはCategory設定を持たないため、自動的に `すべて` として動作します。
- 実Category名が `all` の場合も内部tokenを分離し、`すべて` と衝突しません。
- 保存済みCategoryが後からRename/Deleteされた場合は、設定を勝手に全Feedへ広げず、該当Feedなしの旧選択として表示します。

## Search Feed

- Search Feedへ「自分のRSS Category」を追加します。
- 既存の「共通RSSカテゴリー」は別の条件として維持します。
- 検索範囲が「自分の登録RSS」の場合は、自分のRSS Categoryだけを使用します。
- 検索範囲が「共通RSS」の場合は、自分のRSS Categoryに影響されません。
- 検索範囲が「両方」の場合は、自分のRSSと共通RSSをそれぞれ独立したCategory条件で絞ってから検索します。
- 旧Search Feed Widgetは「自分のRSS Category＝すべて」として互換動作します。
- Browser cacheで旧 `dashboard.js` が残る環境でもCategory保存が欠落しないよう、専用の `search-feed-owned-category.js` でcreate/update payloadを補完します。

## OPML compatibility

- OPML Importは既存のCategory hierarchyを維持します。
- nested outline hierarchyがあるOPMLでは、そのフォルダ階層をFeed Categoryとして優先します。
- flat OPMLの `category` 属性は、OPML 2.0形式のカンマ区切りCategory・スラッシュ区切り階層として解釈し、本Applicationの1 Feed = 1 Categoryに合わせて先頭Category pathを採用します。
- OPML Exportは従来のnested outline hierarchyを維持し、Category設定済みFeedには `category="/技術/Cloud"` のような属性も出力します。
- 未分類FeedにはCategory属性を付けません。
- 同じURLのFeedが既に現在Userへ登録済みの場合はDuplicate扱いとし、現在のCategoryをOPML値で上書きしません。
- 同一OPML内の同じURLは、最初に成功した登録を維持します。
- 別Userが同じURLを登録していても、現在UserのImportを妨げません。

## Database / configuration

- Database Migration: なし。
- 新規Table / Column: なし。
- 必須Config変更: なし。
- Fresh Installは従来どおりCurrent `database/schema.sql` を使用します。
- 既存Databaseへ `schema.sql` を上書き実行しません。

## Compatibility

- PHP 8.1以上。
- MySQL / MariaDB。
- V1.44.0のMultiple Calendar / LocationとMigration 033 / 034の状態を変更しません。
- Feed Categoryのmutationは認証Userのactive Feedへowner scopeを適用します。
- OPMLの既存UTF-8、control character、DOCTYPE / ENTITY拒否、`LIBXML_NONET`、512 KiB、500 Feed、depth limitを維持します。
- RSS取得、Feed Health、RSS Rules、Stock、Calendar、Mail、Remote Files、Game、Account Securityの既存動作を維持します。

## Verification limits

- PHP 8.1 / PHP 8.4 Current regressionをA〜Dの各Phase、各main merge後、Release-ready sourceで通過させます。
- Feed Category Backend/API/UI、SQLite実DB、All RSS Recent、Search Feed、OPML Import/List/Export、Export→Import round-trip、owner boundary、inactive Feed、Uncategorized、hierarchical Category、実Category名 `all`、OPML security/size/count/depth limitをFocused Testで確認しています。
- MariaDB server toolを利用出来ないGitHub Actions環境では、一部のMariaDB実Server試験はSKIPし、既存のMySQL/MariaDB SQL contract、SQLite integration、PHP runtime regressionで補完します。
- User Browser TestではRSS管理のCategory設定・絞り込み、全RSS新着のCategory絞り込み、Search FeedのCategory保存・検索動作を確認済みです。
- OPML DではExport内容とDuplicate時の既存Category維持を確認対象とし、Focused Testでround-tripとowner boundaryを検証しています。
- Production DeploymentはGitHub Release公開とは別工程です。

## Release

Tag: `v1.45.0`

Runtime package: `rss-reader-modernization-1.45.0.zip`

Complete Source package: `rss-reader-modernization-1.45.0-complete.zip`
