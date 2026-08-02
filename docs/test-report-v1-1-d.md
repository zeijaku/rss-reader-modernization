# V1.1-D / R1 Test Report

## Result

Localで実行可能なSecure Baseline、M1、V1.1-B、V1.1-C、V1.1-DのRegressionを分割実行し、全区間を完走した。

```text
PASS 1815
FAIL 0
SKIP 6
```

一括RunnerはHTTP系Testを含み実行時間が長いため、この作業環境のCommand上限に合わせて、SB-00〜14、Repository scan〜V1.1-D、V1.1-D残区間へ分割した。各区間は同じ`run-local-v1-1-d.sh`の順序と内容で実行し、重複しない区間の結果を集計した。

## V1.1-D focused checks

```text
Dashboard Widget / transaction / owner       PASS 37
Architecture / Security / API / UI           PASS 28
SQL / Prefix / Migration / logical model     PASS 51
Dashboard actual render                      PASS 21
Runner / historical gate separation          PASS 22
M2-C accessibility render compatibility      PASS 19
M2-D responsive render compatibility         PASS 16
------------------------------------------------------
Total                                         PASS 194
```

主な確認内容：

- Widget type Allowlist、4タブ、幅1〜4、Style Allowlist。
- TEXT保存JSONのObject形状、深さ、Unicode、長さ、不正JSON。
- `DB_TABLE_PREFIX`による`ig_`、`rss_`等のTable解決。
- 既存active Feedだけを1WidgetずつBackfill。
- 初期順序が従来の`content_id`昇順を維持。
- Migration再実行でWidget重複を作らない。
- V1.1-Eで変更するsort orderをMigration再実行で戻さない。
- Feed追加、変更、削除時のContent / Widget Transaction。
- Widget作成失敗時にContent作成をRollback。
- owner / tab scope、別Userの表示・更新・削除拒否。
- Client指定owner IDを無視し、Login Userからownerを決定。
- `widget.list`がownerを返さず、選択tabだけを返す。
- Migration不足を構造化503で扱う。
- 4タブ、Feed Card、Stock Page、V1.1-C NEW表示の既存構造維持。
- Mobile / Tablet / Desktop幅、44px Stock列、coarse pointer、ARIA、Focus。
- Drag & DropをV1.1-Dへ混在させていない。

## Syntax

```text
PHP syntax       PASS 80 files
JavaScript       PASS 23 files
Python compile   PASS 49 files
Shell syntax     PASS
```

## Existing security / regression

Authentication、Session、Password、CSRF、owner scope、SSRF、XSS、SQL/PDO、PHP 8、Table Prefix、RSS 2.0 / RSS 1.0 / Atom fixture、Item Identity、Tracking Parameter、NEW状態、Cache、Retry、HTTP 304、stale-if-error、Repository secret scanを再実行し、Local実行範囲はFAIL 0だった。

## SKIP / HOLD

### SKIP 6

Local PHPに次がないため、既存Testが明示的にSKIPした。

- PDO SQLite driver
- PDO MySQL driver／実MySQL接続
- cURL
- SimpleXML
- mbstring

同じ環境条件を複数Testが個別に報告した合計がSKIP 6であり、PASSへ読み替えていない。

### HOLD

- 実MySQL 5.6 / 8.x / MariaDBへの003 Migration適用、再実行、Rollback。
- 実DB上の既存Feed全件Backfillとowner/location/style照合。
- 実FeedによるRSS 2.0、RSS 1.0、Atom、HTTP 304、stale-if-error。
- 実Browser、Mobile Touch、Keyboard、8テーマ。
- V1.1-E Drag & Drop（次工程）。
- GitHub Actions PHP 8.1 / 8.4。
- BackupからのRestore drill。

## GitHub main M2 tests

V1.1-Dで影響するGitHub mainのM2-C / M2-D Fake PDO render TestをWidget Queryへ追従させた。Accessibility、Responsive、Touch、Stock、ARIAの検査は残している。

M2-A、M2-B、M2-E、M2-FはRuntime契約を変更していない。GitHub ActionsではRepositoryの`tests/run.sh`から引き続き実行する。

M2-GおよびM4-A〜GはVersion 1.0.0のFinal Release Gateであり、V1.1開発中は明示的SKIPとする。Version 1.0.0 Treeでは従来どおり実行される。

## Package verification

```text
Overlay payload files  37
ZIP entries            38（root directoryを含む）
Checkpoint checks      PASS 5
CRC / duplicate / path traversal / absolute path PASS
Private設定 / 実DB / Log / Session / Cache       混入なし
Nested ZIP / secret pattern                       混入なし
Manifest                                           一致
Deterministic build                                一致
```

ZIPを別Directoryへ展開し、V1.1-C作業Copyへ適用後、V1.1-D専用Test、M2-C / M2-D render Test、全PHP / JavaScript / Python構文、Package検査を再実行した。Overlay 37 FileがBuild元と同じSHA-256であることも確認した。
