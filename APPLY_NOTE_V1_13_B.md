# V1.13-B R2 Apply Note — Stock画面分離 + Extensionless URL

## Baseline

- 正式Baseline: RSS Reader Modernization 1.12.1
- Baseline commit: `f1a072b92c8ba65e6d38e355e7e6d6a4fe56c2c9`
- V1.13-BではApplication Version番号をまだ変更しません。V1.13正式化時に更新します。

## 変更内容

- `public/stock.php` を追加し、Stock一覧の実表示入口を分離。
- `public/index.php` からStock本体の描画処理とStock専用Task追加先DOMを削除。
- 旧URL `/?tab=stock` は302で `./stock` へ移行。
- 旧URLの `q` / `sort` / `page` / `tag` は既存Validationを通した値だけをRedirectへ引き継ぐ。
- Stock検索Form、Pageリンク、Clearリンク、DrawerのStockリンクを Extensionless URL `stock` へ変更。
- Stock解除、Tag操作、Stock→Task、Article ActionsのMutationは従来どおり `public/api_v1.php` を使用。
- Stock画面で従来利用出来たDrawer、Widget追加、表示設定、Account Settings、RSS Highlight等の既存DOMは維持。
- Asset削減はこの段階では行わず、既存Assetを維持。Performance再計測後にV1.13-Eで再判断。

## DB / Config

- DB Table変更: なし
- Column変更: なし
- Migration追加: なし
- `config/local.php` 変更: なし
- `.htaccess` 変更: あり（`/stock` → `public/stock.php` の内部Rewrite。`/stock.php` は302で `/stock` へ正規化）

## 更新時の注意

本番DB、`config/local.php`、Log、Session、Cache等のRuntime Dataは上書きしないでください。
V1.12.1適用済み環境からV1.13-Bへ進む場合、SQL実行は不要です。

## 主な確認

1. Dashboardの4タブが従来どおり表示出来ること。
2. Drawerの「Stock一覧」で `/stock` が開くこと。
3. 旧 `/?tab=stock` を開くと `/stock` へ移動すること。
4. Stock検索、並び順、Tag絞り込み、Paginationが動くこと。
5. Stock解除後のAjax更新、Page 2以降で最後のCardを解除した場合の前Page復帰。
6. Tag追加・解除・新規作成・名前変更・削除。
7. Stock→Task。Task Widget 1件時は直接追加、複数時は追加先選択。
8. Stock画面のDrawerから表示設定、Account Settings、RSS Highlight等が従来どおり開くこと。
9. LogoutとSession切れ後のLogin動作。
10. Browser Consoleに新しいJavaScript Errorがないこと。

## R2: Extensionless URL

- Browserに見せるCanonical URLは `/stock`。
- 実体ファイルは `public/stock.php` のまま維持する。
- `/stock?sort=oldest` や `/stock?q=AI&sort=oldest&page=2` のQuery Stringはそのまま利用出来る。
- `/stock.php?...` が直接指定された場合は、Query Stringを保持したまま302で `/stock?...` へ移動する。
- Application RootをDocumentRootにする現在のレンタルサーバー構成と、`public/`をDocumentRootにする構成の両方へRewriteを用意した。
- Subdirectory設置時もURL Prefixを維持する。
- 302はV1.13開発中のBrowser Cache影響を避けるため。V1.13正式化時にPermanent Redirectへ切り替えるか再判断する。
