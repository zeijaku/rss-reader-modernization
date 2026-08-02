# V1.1-C / R1 Test Report

## Result

Localで実行可能なSecure Baseline、M1、V1.1-B、V1.1-CのRegressionは完走した。

```text
PASS 1620
FAIL 0
SKIP 6
```

M2-A〜FのTest本体は今回のLocal Baseline ZIPに含まれていないためLocalではHOLD。GitHub mainの`tests/run.sh`では引き続き有効とし、Overlay適用後のGitHub Actionsで確認する。

M2-GおよびM4-A〜GはVersion 1.0.0のFinal Package、Release Notes、Version markerを検査する歴史的Release Gateである。V1.1開発中に1.0.0を装う変更は行わず、`tests/run.sh`で明示的なSKIPとして分離した。Version 1.0.0 Treeでは従来どおり実行される。

## V1.1-C dedicated checks

```text
Feed item state / owner / API       PASS 39
Architecture / Security / UI        PASS 26
SQL / Prefix / Migration            PASS 38
Runner / historical gate separation PASS 11
Total                                PASS 114
```

主な確認内容：

- 初回成功取得はBaselineでNEW 0。
- 2回目以降の初検出IdentityだけNEW。
- 未解除状態は再取得後もNEWを維持。
- 重複記事はIdentity単位で1件として扱う。
- 記事単位／Feed単位のNEW解除。
- 解除の重複送信は0件更新で安全に完了。
- 他UserのFeedは404扱いで操作不可。
- Clientのowner IDは無視し、Session Userからownerを決定。
- 不正content ID、不正Item Identity、HTML風Identityを拒否。
- API PayloadはCanonical Identityだけを返し、不正IdentityではNEWを無効化。
- Transaction、owned Feed lock、Unique Index。
- `ig_`、`rss_`等のPrefix解決。
- Migration再実行時に既存Tableを破壊しない。
- Foreign Keyを追加せずVersion 1.0.0方針を維持。
- RSS 2.0、RSS 1.0、Atomの既存Parser契約を維持。
- Cache hit、HTTP 304、stale-if-errorの内部Identity経路。
- NEW要素を`.text()`で生成し、ARIA LabelとFocus表示を維持。

## Syntax

```text
PHP syntax    PASS 77 files
JavaScript    PASS 23 files
Python compile PASS 42 files
Shell syntax  PASS
```

## Existing security/regression

Authentication、Session、CSRF、owner scope、SSRF、XSS、SQL/PDO、PHP 8、Item Identity、Cache、Retry、HTTP 304、stale-if-error、Repository secret scanを再実行し、Local実行範囲はFAIL 0だった。

## SKIP / HOLD

### SKIP 6

Local PHPに次がないため、既存Testが明示的にSKIPした。

- PDO SQLite driver
- PDO MySQL driver／実MySQL接続
- cURL
- SimpleXML
- mbstring

重複した環境条件をTest単位で数えた結果がSKIP 6であり、PASSへ読み替えていない。

### HOLD

- 実MySQL 5.6／8.x／MariaDBへのMigration適用、再実行、Rollback。
- 実FeedによるRSS 2.0、RSS 1.0、Atom、HTTP 304、stale-if-error。
- 実Browser／Mobile Touch／Keyboard／8テーマ。
- GitHub mainにのみ存在するM2-A〜F Test。
- GitHub Actions PHP 8.1／8.4。
- BackupからのRestore drill。

これらは利用者環境で確認する。

## Package verification

```text
Overlay payload files 51
ZIP entries           52（root directoryを含む）
Package checks        PASS 5
CRC / duplicate / path traversal / absolute path PASS
Private設定 / 実DB / Log / Session / Cache       混入なし
Nested ZIP / secret pattern                       混入なし
Manifest                                                一致
```

ZIPを別Directoryへ展開してV1.1-B Local Baselineへ適用後、`tests/run-local-v1-1-c.sh`を再実行した。

```text
PASS 1620
FAIL 0
SKIP 6
```
