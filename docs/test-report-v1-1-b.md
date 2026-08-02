# V1.1-B / R1 Test Report

## Automated regression

```text
PASS 1504
FAIL 0
SKIP 6
```

主な確認範囲:

- PHP syntax
- Authentication / Session / CSRF / owner scope
- SSRF / XSS / Validation / PDO SQL contract
- RSS Engine M1-A〜M1-G
- Cache / ETag / Last-Modified / HTTP 304
- Retry / Backoff / stale-if-error
- Concurrent cache/state operation
- Item Identity
- Tracking Parameter全11種類
- Parameter位置、大小文字、encoded name、重複、空値
- 一般Query Parameter、Fragment、日本語encoded valueの維持
- Feed表示前、Stock保存前、Identity生成前
- Feed URLを変更しないこと
- Repository secret/runtime artifact scan

## Environment

```text
PHP    8.4.16 CLI
Python 3.13.5
Node   22.16.0
```

## SKIP / HOLD

次はこの実行環境に必要なPHP extensionまたは接続先がないためSKIP / HOLDです。

- PDO SQLite integrationの一部
- SimpleXML / mbstringを使うLive parser matrix
- pdo_mysqlを使う実MySQL接続
- cURLを使う実Feed取得
- 実Hosting上のRSS 2.0 / RSS 1.0 / Atom
- 実BrowserでのStock保存後DB値確認

SKIPをPASSにはしていません。

## Database

V1.1-BではDB schemaとMigrationを変更していないため、Migration testは対象外です。既存`database/schema.sql`のSHA-256がBaselineから変わっていないことを専用Testで確認しています。

## Baseline package limitation

この作業環境ではGitHub mainの主要Runtimeと正式配布ZIPは確認できましたが、M4-G Checkpoint ZIP本体をLocal fileとして展開できませんでした。

そのためLocal自動Regressionの`PASS 1504 / SKIP 6`はSB〜M1-GとV1.1-Bの範囲です。GitHub mainに存在するM2/M4歴史Testは削除せず、Overlay適用後の利用者環境またはGitHub Actionsで実行してください。この未実施範囲をPASSにはしていません。
