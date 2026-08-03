# V1.1-K Test Report

## Target

- Application Version: `1.1.0`
- Application Label: `RSS Reader Modernization 1.1.0`
- Baseline: V1.1-J / R2 Feedタイトル高さ統一、V1.1-I / R3 Task日付欄調整を含む添付GitHub作業Folder

## Result summary

| Test group | PASS | FAIL | SKIP | Note |
|---|---:|---:|---:|---|
| Secure Baseline～M2、V1.1-B～K分割全回帰 | 3,857 | 0 | 9 | 一括Runnerの実行時間上限を避け、同じSectionを分割実行 |
| M4で現在も有効なLicense / Config / Healthcheck / Environment / CI | 260 | 0 | 0 | Version 1.0固定Hash Gateは履歴Testとして対象外 |
| DB / Schema / Migration集中確認 | 282 | 0 | 0 | Static / SQLite behavior / CLI help。実MySQL適用は未実施 |
| Secret / private file scan | 3 | 0 | 0 | 高Signal Pattern、設定Placeholder、禁止File |

同じTestを集中確認でも再実行しているため、上表は単純合算して総Test数とはしません。

## Syntax

- PHP: 92 Files PASS
- JavaScript: 18 Files PASS
- Python: 120 Files PASS
- Shell: 11 Files PASS

## Confirmed areas

- Authentication、Authorization、Session、CSRF、Password Hash、Login Throttle
- SSRF safe fetch、TLS、redirect、response size、XSS safe rendering、PDO / SQL binding、Validation
- RSS 2.0、RSS 1.0、Atom、Feed cache、conditional request、retry、stale fallback
- Tracking Parameter除去、Feed item NEW状態
- Dashboard Widget CRUD、Feed Widget移行、Drag & Drop、Keyboard並び替え
- Clock、Memo、Task、Calendar、Task期限Calendar連動
- Mobile 4タブ、Swipe、Loading Spinner、Task期限入力欄
- Account Settingsのメールアドレス変更、パスワード変更、Session / CSRF再生成
- 8 Theme、Frontend dependency、License、公開Asset inventory
- 新規Schema 9 Table、Migration 002～006、Prefix、再実行安全性、非破壊性
- Runtime Data、秘密情報、実DB、Log、Archiveの公開除外
- Documentation Link、Version表記、Release builder / verifier
- Playwright / ChromiumによるClock、Memo、Task、Calendar、Account Settings、Loading、タイトル高さ確認

## Fixed during finalization

1. V1.1-C MigrationだけDefault Prefixが`rss_`だったため、既存DB更新用の他Migrationと同じ`ig_`へ統一。
2. V1.1追加後も旧DOM断片やHandler数を固定していたM2 Testを現行実装へ同期。
3. V1.1-J / R2専用Testを全体Runnerへ追加。
4. 未参照のjQuery 3.3.1、Font Awesome旧形式、Font Awesome 5.3.1 Licenseを削除。
5. 添付Baselineに含まれていたSession、Feed Cache、Login Throttle生成Dataを除外。

## Skipped / environment limitations

この実行環境では`pdo_mysql`、PDO SQLite、cURL、SimpleXML、mbstringの一部が利用できず、次がSKIPまたは利用者環境確認です。

- 実MySQL Serverへの新規`schema.sql`適用
- 実MySQL既存DBへのMigration 002～006適用と再実行
- 一部SimpleXML / mbstring依存のLive Parser Matrix
- 実Feed提供元への到達性
- Hosting固有Apache / PHP / Directory permission
- BackupからのRestore

Static / Mock / SQLite behavior / Browser Testで可能な範囲は確認済みですが、上記を実運用Serverで確認してから公開してください。

## Historical M4 gate

`tests/test_m4d_public_surface.py`の一部はM2-G / Version 1.0.0のRuntime Hashが変わっていないことを確認する履歴Testです。V1.1-B～Jで対象Fileが意図的に変更されているため、Version 1.1.0の合否判定には使用していません。公開境界、Runtime Data除外、CIのread-only設定はV1.1-K Gateで再確認しています。

## Package verification

最終ZIPはBuilder / Verifierにより、SHA-256、内部Manifest、CRC、重複Path、Path Traversal、秘密情報Pattern、Runtime Data、Version表記を確認します。最終ArtifactのDigestはZIPと同じ場所の`.zip.sha256`および外部Release Reportを正本とします。
