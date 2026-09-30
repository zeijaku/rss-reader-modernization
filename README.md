# RSS Reader Modernization

[![CI](https://github.com/zeijaku/rss-reader-modernization/actions/workflows/ci.yml/badge.svg)](https://github.com/zeijaku/rss-reader-modernization/actions/workflows/ci.yml)

10年以上前に作成したPHP製RSS Readerを、既存機能・既存データをできるだけ維持しながら、PHP 8系、現行Browser、安全な運用へ段階的にModernizationしている個人Projectです。

全面的な作り直しではなく、RSS閲覧を中心とした既存資産を残しつつ、Security、Dashboard、Productivity、Mail、File管理、Release運用を小さなRelease単位で改善しています。

## Stable Release

**Development checkpoint:** V1.40-A / `1.40.0-dev.2` — Maze Chase and the minimal shared Game lifecycle. Existing game engines and database schema remain compatible. See [V1.40-A verification](docs/v1.40-a-game-widget.md).

**Stable release:** `RSS Reader Modernization 1.39.2`  
Release tag: `v1.39.2`

Version 1.39.2は、Fresh Installを`database/schema.sql` 1本へ統合し、Current Schema verifier、導入Documentation、Release package、CI/Test境界を整理したMaintenance / Correction Releaseです。

Application Versionの正本は `app/version.php`、Versionごとの変更履歴は [`CHANGELOG.md`](CHANGELOG.md)、現在の正式Release詳細は [`RELEASE_NOTES.md`](RELEASE_NOTES.md) と [GitHub Releases](https://github.com/zeijaku/rss-reader-modernization/releases) を参照してください。

## Main Features

### RSS / Reader

- RSS 2.0 / RSS 1.0 / Atomの表示
- ユーザーごとのFeed URL登録・変更・論理削除
- 4タブへのFeed配置
- Feed Card単位の個別更新
- Search Feedによる登録RSS横断検索
- 全RSS新着による所有RSS横断の新着記事集約
- RSS Management / OPML Import・Export
- Feed Health
- RSS RulesによるHighlight / Hide / Stock / Task action
- Keyword Highlight
- Tracking Parameter除去
- NEW表示と手動解除
- ETag / Last-Modified / HTTP 304
- Server-side Feed Cache、同一URLの重複Fetch抑制
- Backoff / Retry-After / bounded stale-if-error
- Reader Modeによる記事本文表示
- 必要な記事だけを取得するFull Text処理
- Reader本文画像の認証済みsame-origin Image Proxy

### Dashboard / Productivity

- Dashboard Widgetの追加・編集・削除・並び替え・サイズ変更
- Clock
- Memo
- Task
- Calendarの日／週／月表示
- Calendarの終日／時刻／URL／5色予定
- 複数日予定
- 毎日／毎週／毎月／毎年の繰り返し
- Occurrence単位の変更／削除
- 直近予定表示
- RSS / StockからCalendar予定作成
- Calendar Reminder
- Dashboard Notification Center
- Links
- Information Board
- Bootstrap Theme、Navbar Link、Tab名設定
- PC / Smartphone対応

### Information / Media / Game

- Weather
- Earthquake
- Sun / Moon
- Air Quality / UV
- Connection Monitor
- Camera / Video（Snapshot / YouTube / Video File / MJPEG / HLS）
- X Timeline（Optional / X Developer Platformが必要）
- RSS Typing
- Wire Defense
- 2048
- Reversi

### Stock

- 記事のStock保存
- 未処理／処理済み
- 通常／重要
- Archive
- Search / Tag / Sort / Pagination
- Filterと一括状態更新

### File Library / Remote Files

- 認証済みOwner専用File Library
- JPEG / PNG / GIF / WebP / PDF / TXT / CSV / ZIP
- Image / PDF / TXT / CSV Preview
- Upload / Download / Delete
- FTP / Explicit FTPS / SFTP / HTTPS WebDAV
- Remote Directory操作
- Remote Upload / Download
- File Libraryとの相互転送
- Remote Text Editor
- SHA-256競合検出
- LF / CRLF・UTF-8 BOM維持
- Unix Permission表示とpreset chmod
- Remote Files複数Upload

### Mail

- Password / Gmail OAuth2
- IMAP受信・Folder切替・検索
- SMTP Plain Text送信
- Reply
- Sent保存
- 送信時複数添付
- 受信／Sent添付Fileの表示・Download
- Mail / RSSの安全なError分類

### Account Security

- User registration / Login / Logout
- Account Settings
- `password_hash()` / `password_verify()`
- Session管理
- Remember Me
- TOTP 2FA
- Recovery Code
- Step-up Authentication
- Authentication Security Audit Log
- 2FA成功後24時間のtrusted-browser behavior

## Runtime Requirements

- PHP 8.1+
- PDO / `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQL / MariaDB
- Web ServerのDocumentRootをProject内の `public/` に設定できる構成

新規構築の確認基準はMySQL 8系です。Runtime directory、Extension、Configの詳細は [`docs/installation.md`](docs/installation.md) と [`docs/configuration.md`](docs/configuration.md) を参照してください。

## Quick Start

詳細な設置手順の正本は [`docs/installation.md`](docs/installation.md) です。

新規の空Databaseへ設置する場合の概要:

1. GitHub ReleaseのRuntime ZIPとSHA-256を取得し、別Directoryへ展開する。
2. Web ServerのDocumentRootを `<project>/public` に設定する。
3. `config/local.php.example` を参考に、Git管理外の `config/local.php` を作成する。
4. MySQL / MariaDBに空Databaseと専用Userを作成する。
5. `DB_TABLE_PREFIX` と `database/schema.sql` の `@table_prefix` を一致させる。
6. `database/schema.sql` を1回実行してCurrent Schemaを作成する。Fresh Installでは追加Migrationは不要。
7. `var/` 以下の必要DirectoryへPHP Processの書込み権限を設定する。
8. Runtime ZIPでは `php tools/healthcheck.php` と `php tools/db_current.php verify` を実行する。Complete Sourceでは必要に応じてCurrent Testも実行する。
9. BrowserでRegistration、Login、RSS、主要Widgetを確認する。Mail / Remote Files / X / 2FA等は利用する場合だけ追加確認する。

`database/schema.sql` はFresh Install用の完成形です。`database/migrations/` は既存DatabaseのUpgrade用として保持します。既存Databaseへ `schema.sql` を再実行しないでください。

## Updating

更新手順の正本は [`docs/update.md`](docs/update.md) です。

更新時は次を基本とします。

1. 現在Version / Commitを記録する。
2. Application Code、`config/local.php`、Database、必要なRuntime DataをBackupする。
3. 対象Releaseの [`RELEASE_NOTES.md`](RELEASE_NOTES.md) と [`CHANGELOG.md`](CHANGELOG.md) を確認する。
4. 未適用Migrationと必須Config変更の有無を確認する。
5. Runtime ZIPとSHA-256を確認し、本番Directory外へ展開する。
6. Private Config / Database / Runtime Dataを維持したままCodeを更新する。
7. Current TestとBrowser Smoke Testを行う。
8. 問題がある場合は [`docs/rollback.md`](docs/rollback.md) に従う。

Backup / Restoreは [`docs/backup-and-restore.md`](docs/backup-and-restore.md) を参照してください。

## Tests / CI

通常の変更で使用するCurrent Gate:

```bash
bash tests/run-ci.sh
```

GitHub ActionsのCIは `main` push / Pull Requestで同じCurrent GateをPHP 8.1 / 8.4に対して実行します。

過去Version固有の挙動を調査する場合だけ、Historical testを含む `bash tests/run.sh` または対応する `tests/run-v*.sh` を追加実行します。

CIとRelease Gateの詳細は [`docs/ci.md`](docs/ci.md) を参照してください。

## Security

主なSecurity Boundary:

- 認証済みSessionの `user_id` をOwner scopeの基準にする
- Mutation APIはPOST + explicit action + CSRF
- SQLはPDO parameter binding / native prepare
- Passwordは `password_hash()` / `password_verify()`
- Login throttle
- Feed / Reader / Remote接続は用途ごとのOutbound Security Boundaryを維持
- Feed / DB由来Dataをvalidate / escapeして描画
- Credential / Token / Session / Runtime Dataを公開領域とRepositoryから分離
- `config/local.php`、実DB、Backup、Log、Session、Cache、SecretをGitへ含めない

Security Designは [`docs/security.md`](docs/security.md)、脆弱性報告方法は [`SECURITY.md`](SECURITY.md) を参照してください。

## Documentation

現在の操作・運用では、READMEへ詳細を重複させず次のDocumentationを正本とします。

- [Installation](docs/installation.md) — 新規設置、Schema、Fresh Install、Legacy DB移行
- [Update](docs/update.md) — Current更新手順、Migration判断、更新後確認
- [Historical Update / Migration History](docs/update-history.md) — 過去Version固有Migration / Config / Update履歴
- [Configuration](docs/configuration.md) — Production Config、Session、HTTP、Mail、X、Remote Files
- [Deployment Checklist](docs/deployment-checklist.md) — 配置前後の確認
- [Backup and Restore](docs/backup-and-restore.md) — DB / Config / CodeのBackupと復旧
- [Rollback](docs/rollback.md) — Code / Config / DBを分けたRollback
- [Security Design](docs/security.md) — Authentication / CSRF / SSRF / XSS / DB等のSecurity Boundary
- [CI](docs/ci.md) — Current CIとRelease Workflow
- [Release Package](docs/release-package.md) — Runtime / Complete Source / SHA-256 / Attestation
- [Tag and GitHub Release](docs/tag-and-github-release.md) — Formal Release手順
- [Dependencies](docs/dependencies.md) — Runtime DependencyとLicense
- [Historical Modernization Roadmap](docs/roadmap.md) — Secure Baseline / M1 / M2 / M4等の開発履歴
- [CHANGELOG](CHANGELOG.md) — Versionごとの変更履歴
- [Release Notes](RELEASE_NOTES.md) — 現在の正式Release詳細
- [Contributing](CONTRIBUTING.md) — 変更時のCurrent Test / PR方針

過去Version固有のimplementation document / test reportは、Historical Evidenceとして `docs/` に保持しています。

## Release Packages

Formal Releaseでは共通 `.github/workflows/release.yml` が次を生成します。

- `rss-reader-modernization-X.Y.Z.zip` — Production Runtime
- `rss-reader-modernization-X.Y.Z.zip.sha256`
- `rss-reader-modernization-X.Y.Z-complete.zip` — Complete Source
- `rss-reader-modernization-X.Y.Z-complete.zip.sha256`

Release WorkflowはCurrent regression、secret scan、Package verify、clean-room check、SHA-256、GitHub Artifact Attestationを検証してからimmutable Tag / GitHub Releaseを公開します。

Productionへの自動Deployは行いません。

詳細は [`docs/release-package.md`](docs/release-package.md) と [`docs/tag-and-github-release.md`](docs/tag-and-github-release.md) を参照してください。

## Legacy / Modernization Policy

Legacy版は比較・解析対象として保持し、現在のRuntimeへ混在させません。

既存機能・既存Data・主要URLとの互換性を重視し、大規模な全面Rewriteより小さな変更を優先します。古いCredential形式や不明なDataを推測で自動移行しません。

初期Modernizationの設計・変更履歴は [`docs/modernization.md`](docs/modernization.md)、[`docs/change-map.md`](docs/change-map.md)、[`docs/legacy-analysis.md`](docs/legacy-analysis.md) を参照してください。

## License

Project独自CodeとModernizationで追加・変更した部分は [`LICENSE`](LICENSE) のMIT Licenseで公開します。

同梱Frontend / Backend Libraryには各上流Licenseが適用されます。詳細は [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md) と [`docs/dependencies.md`](docs/dependencies.md) を参照してください。
