# V1.33-H Finalization / Production RC Check

## 状態

- Application: `RSS Reader Modernization 1.33.0-RC2`
- Intended formal version: `1.33.0`
- Existing formal version: `1.32.0`
- RCは正式Releaseではなく、本番確認とGitHub Actions前の候補です。
- V1.33の機能ScopeはCalendar Enhancementで固定します。
- 日程コピーと日程Drag & Dropは次期以降へ延期します。

## 変更の要約

- 予定色を既存3色互換のまま5色へ拡張。
- Calendar range／Occurrence identityを共通化。
- 繰り返し予定の「この予定のみ」変更／削除／復元を追加。
- 月表示で複数日予定を週境界・月境界を含めて連結表示。
- 日／週／月表示と、時刻指定予定のhour lane配置を追加。
- Calendar上部をPC／Smartphone用のcompact toolbarへ整理。

## DB / Config / Security

- DB変更: あり。`025_v1_33_calendar_event_exception.sql`で例外Tableを加算追加。
- Config変更: なし。新規必須Secretもなし。
- 既存Data変更: なし。既存予定、繰り返しRule、3色Valueを保持。
- Security: Authentication、Owner Scope、CSRF、XSS escape、PDO、Validation、Session、Step-up、2FA、Recovery Code、Session Registry、Security Audit Logを維持。

## 本番確認手順

1. 現在のApplication、`config/local.php`、Database、private runtime dataをBackupし、V1.32.0へ戻せることを確認する。
2. RC ZIPとSHA-256 sidecarを同じ場所へ置き、SHA-256が一致することを確認する。
3. ZIPを本番Directoryの外へ展開し、Top-level配下に`app/`、`public/`、`database/`等があることを確認する。
4. 実環境の`DB_TABLE_PREFIX`を確認し、対応する`calendar_event_exception` Tableの有無を確認する。
5. Tableが無い場合だけ、Migration 025の`SET @table_prefix`を実環境へ合わせて1回適用する。既存DBへ`database/schema.sql`は実行しない。
6. Migration後、既存`calendar_event`件数が変わっていないことと、例外Tableが空で作成されたことを確認する。
7. RCの`app/`、`public/`、`database/`、`docs/`等を相対Pathで上書きする。`config/local.php`、実DB、`var/`の実Data、秘密鍵／Secretは上書きしない。
8. Browserを完全Reloadし、CSS／JavaScriptがHTTP 200かつ正しいMIME typeで返り、Console errorが無いことを確認する。
   - DevToolsのNetworkでCacheを無効にし、完全Reloadを10回行う。動的JavaScriptが既存の依存順で読み込まれ、Calendar、Mail、Camera、X等の初期化が欠けないことを確認する。
   - 通常Cacheへ戻してReloadを10回行い、503が再発しないことを確認する。一時失敗を再現できた場合は、同じ静的Assetの`asset_retry=1`が最大1回だけ発生し、その後HTTP 200で回復することを確認する。
   - `api_v1.php`等の登録・更新・削除リクエストに自動再送が発生していないことを確認する。
9. Footer等のVersion表示が`RSS Reader Modernization 1.33.0-RC2`であることを確認する。
10. 既存V1.32予定を開き、Title、Note、日付、時刻、URL、繰り返し、既存3色が変更されていないことを確認する。
11. 赤／青／緑／黄／紫を各1件作成し、Light Mode／Dark Modeで文字が読めること、編集後も色が維持されることを確認する。
12. 単日、2日、週をまたぐ、月末から翌月、表示月外から表示月内へ続く予定を作り、月表示で連結と端部が正しく見えることを確認する。
13. 同じ日に複数の複数日予定を作り、Laneが重ならず、翌週でも同じ予定の継続が識別できることを確認する。
14. 日表示で14:00–15:00等の予定が14時のhour laneへ配置され、最下部へ落ちないことを確認する。終日／複数日は上部の別領域に表示されることを確認する。
15. 週表示で時刻指定予定の曜日／時刻、終日／複数日領域、前週／次週／今日操作を確認する。
16. 月表示で前月／次月／今日、日／週／月Switch、追加Button、中央LabelがPC幅で1行に収まることを確認する。
17. 320px、375px、768px、992px、1280pxでtoolbar、Calendar grid、Modalを確認し、Smartphoneでは2段toolbarがCard幅からはみ出さないことを確認する。
18. 毎週繰り返し予定を作り、選択した1Occurrenceだけを変更する。前後OccurrenceとシリーズRuleが変わらないことを確認する。
19. 別Occurrenceを「この予定のみ」で削除し、その日だけ非表示になること、復元で再表示されることを確認する。
20. 「シリーズ全体を編集」「このシリーズを削除」が従来どおり動作することを確認する。「これ以降」は未実装であることを確認する。
21. Calendar title／noteへHTML風文字列を入力し、Scriptとして実行されずTextとして安全に表示されることを確認する。
22. 別UserでLoginし、他Userのevent／exceptionを取得・変更・削除できないことを確認する。
23. CSRF tokenなし／不正tokenのCalendar更新が拒否され、正しいtokenでは成功することを確認する。
24. Login、Remember Me、2FA、Recovery Code、Step-up Authentication、Session管理、Security Activityの主要Smoke Testを行う。
25. RSS、Stock、Task、Settings、Mail以外の主要WidgetをSmoke Testし、Calendar変更による画面全体のRegressionが無いことを確認する。
26. PHP 8.1とPHP 8.4で`tests/run-current.sh`と`tests/run-current-features.sh`を実行し、結果をPASS／FAIL／SKIPで保存する。
27. Error log、Browser Console、DB row countを確認し、異常がなければRC本番確認完了として報告する。

## 正式Releaseへ進む条件

- 上記本番確認に重大なFAILがない。
- PHP 8.1／8.4のcurrent suitesがPASSする。
- Migration 025の新規適用、再実行、別prefix、Data保持がPASSする。
- Runtime package、secret scan、Clean-roomがPASSする。
- Release branch／PRのGitHub ActionsがPASSする。
- その後だけVersionを`1.33.0`へ確定し、mainへMergeして共通Release workflowからimmutable `v1.33.0`を作成する。

## 既知の制限

- 「これ以降」のOccurrence編集／削除はV1.33対象外。
- 日程コピーと日程Drag & DropはV1.33対象外。
- Drag操作の代替となるTouch／Keyboard UIは将来実装時に必須。
