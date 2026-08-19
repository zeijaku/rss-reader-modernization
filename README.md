# RSS Reader Modernization

[![CI](https://github.com/zeijaku/rss-reader-modernization/actions/workflows/ci.yml/badge.svg)](https://github.com/zeijaku/rss-reader-modernization/actions/workflows/ci.yml)

**Stable release:** `RSS Reader Modernization 1.17.2`
Release tag: `v1.17.2`

Version 1.17.2では、Dashboardへ上級者向けのX Timeline Widgetを追加しました。Server側のX API Bearer Tokenを使い、明示した公開Accountの最近の投稿をRead Onlyで表示します。表示件数3／5／10件、Reply／Repostの含有設定、短時間Cacheと期限付きstale fallbackに対応し、Bearer TokenはBrowserへ渡しません。追加ModalではX Developer Platform、Pay Per Use、`APP_X_BEARER_TOKEN`が必要なことを案内し、Tokenの未設定／形式不正／未確認／確認済み／認証失敗を区別して表示します。X本体の「おすすめ / For You」Feedは公式APIで同じものを取得出来ないため対象外とし、将来課題へ分離しています。DB schemaとMigrationの追加変更はありません。

Version 1.17.1では、Version 1.17.0で追加したCamera / Video Widgetと、Mail／Information Widget／各種設定変更の安定性を改善しました。通常API Actionでは認証・CSRF・Action validation後にSession lockを早期解放し、Camera / Video、Mail、Earthquake、Sun / Moon、Air QualityへClient-side watchdogを追加しています。Widget設定保存は対象Card中心の更新へ変更し、他Widgetの設定変更で再生中のYouTubeが停止する問題や、通知が残り続ける問題、hls.js 1.6.16のSRI不一致も修正しました。DB schema、Migration、必須configの追加変更はありません。

Version 1.17.0では、DashboardへCamera / Video Widgetを追加しました。Snapshot、YouTube、Browser標準Video、MJPEG、HLSに対応し、URLから安全に判断出来る場合はAutoでSource Typeを選択します。MediaはServer proxyを経由せずBrowserから配信元へ直接接続し、曖昧なAuto URLはSnapshotへ決め打ちせず手動指定を促します。長期`immutable` Cache環境向けに`APP_ASSET_REVISION`を追加し、正式ReleaseではApplication Versionと同じ`1.17.0`へ確定しました。DB schema、Migration、必須configの追加変更はありません。

Version 1.16.0では、UtilityへCalculator Widgetを追加し、InformationへBlind Spot / Discovery Widgetを追加しました。Blind Spotは国内向け40 Feedを20カテゴリに分け、直前カテゴリ回避と24時間・最大18件の最近記事履歴で同じ内容の連続表示を抑えます。記事概要の展開とStock／URL Copy／X／Taskの既存Article Actionsも再利用します。Dashboard Widget HeaderとDrag Handleの操作領域も共通化しました。DB schema、Migration、必須configの追加変更はありません。

Version 1.15.0では、DrawerのWidget追加をCatalog化し、InformationカテゴリへEarthquake、Sun / Moon、Air Quality / UVを追加しました。Earthquakeは気象庁防災情報XML、Sun / Moonは既存Weatherの地域検索とPHP標準の日照計算、Air Quality / UVはOpen-Meteo Air Quality APIを利用します。Weatherを含むInformation WidgetのLocation・保存・Cache・UI共通処理を整理し、PC／Smartphone、Height 1／2、Solar／Slateを含むTheme表示も調整しました。DB schema、Migration、必須configの追加変更はありません。

Version 1.14.1では、Bootstrap / Bootswatch Themeに合わせて通常RSS／Search Feed、Task、Stock、Calendar、Mail、Links、Weather、Clock、Mini Game等の中立Surface・本文色・補助色をTheme変数へ追従させました。Keyword Highlight、休日、Timer終了、Game状態色など意味を持つ色は従来仕様を維持しています。DB schema、Migration、必須configの変更はありません。

Version 1.14.0では、Frontend dependencyをBootstrap / Bootswatch 5.3.8へ更新し、Bootstrap 4時代のmarkup / Data APIを5系へ移行しました。右DrawerはBootstrap Offcanvasへ置換し、jquery-drawer / iScroll / standalone Popperと旧Bootstrap 4配布Assetを削除しています。PC / Smartphoneと全8 Themeの表示を調整し、Card見出しは`text-bg-*`で背景色に応じた文字色へ自動追従します。DB schema、Migration、必須configの追加変更はありません。

Version 1.13.0では、Dashboard構造整理を中心に、Stock一覧を`/stock`、表示設定・タブ名・RSS Highlight設定を`/settings`へ分離し、Dashboard本体も内部Viewへ分割しました。既存の`/?tab=stock`互換、Account Settings、Stock／Settingsの既存動作は維持しています。Performance再計測では追加最適化を行う根拠となる劣化を確認せず、Security／新規設置DocumentationとHealthcheckを整理しました。新しいDB Migration／config追加はありません。

Version 1.12.1では、V1.12.0後の互換性・回帰修正として、Stock解除のAjax部分更新とStockからTaskへの追加先選択を復元し、履歴Regressionを現行V1.12へ整合しました。DB schema／Migration／configの追加変更はありません。

Version 1.12.0では、RSS HighlightとMail Widget Phase 2を追加しました。RSS Highlightはユーザー登録Keywordを通常RSS／Search Feedで強調表示し、Mail Widgetは未読件数、未読のみ表示、件名／From検索、送信者Filter、IMAP Folder切替に対応します。

約10年前に作成されたPHP製RSSリーダーを、Legacy版を解析資料として凍結したまま段階的に近代化するProjectです。Security / Authentication / Session / CSRF / SSRF / XSS / PDO / Validation / PHP 8 / DB integrity / regression testは `Secure Baseline SB-15 / R3` で確立し、Initial Commitとして公開済みです。

M1: Source / RSS Engine ModernizationはM1-Gまで完了し、**M2: Frontend Modernization** もM2-Gまで完了しました。M2-A〜M2-DでFrontend構造、Feed表示、Accessibility、Responsive、UI / UXを整理し、M2-Eで未使用Frontend配布物を削除、M2-FでjQueryを3.7.1、Font Awesome Freeを6.7.2へ更新しています。M2-GではSecure Baseline、M1、M2を横断する最終回帰、配布物・Asset・Documentationの整合確認を行いました。M2時点ではBootstrap / Bootswatch 4.1.3、Drawer 3.2.2、iScroll 5.2.0-snapshotを維持していましたが、Version 1.14.0でBootstrap / Bootswatch 5.3.8へ移行し、DrawerはBootstrap Offcanvasへ置換、旧Frontend dependencyは配布物から整理しました。Navbar、4タブ、Feed CRUD、Stock、Settings、公開API、DB、M1 RSS Engineの契約は維持しています。

## 現在できること

- ユーザー登録 / ログイン / ログアウト
- Account Settingsからのメールアドレス変更・パスワード変更
- ユーザーごとのFeed URL登録・変更・論理削除
- 4タブ（location 0〜3）へのFeed配置
- RSS 2.0 / RSS 1.0 / Atomの表示
- 省略された記事タイトルのHover／Keyboard Focus全文表示
- RSS内の`content`／`description`を記事単位で安全に展開
- Feedカード単位の個別更新（現在の記事を保持、既存Cache／Retry経路を再利用）
- Search Feed Widgetによる検索語句ベースの記事表示
- 記事ActionsからStock保存、URL Copy、X投稿画面、Task追加
- 正常Feed本文の短時間Server-side cache（初期TTL 60秒）
- 同一Feed URLへの同時Fetch抑制
- ETag / Last-Modified / HTTP 304による本文再送抑制
- 一時障害時の段階的BackoffとRetry-After対応
- 最後の正常確認から24時間以内に限るstale-if-error
- 記事URLから既知のTracking Parameterを除去
- 2回目以降に検出した記事のNEW表示と手動解除
- Dashboard WidgetのタイトルバーDrag & Drop／Keyboard並び替え
- Clock Widgetの追加・変更・削除、12／24時間、日付・秒表示
- Memo Widgetの追加・変更・削除、改行を保持した本文表示
- Task Widgetの追加・変更・削除、完了切替、期限、優先度
- Calendar Widgetの月表示、通常予定、Task期限連動
- Weather Widgetの地域別天気表示
- Earthquake Widgetの気象庁最新地震情報表示
- Sun / Moon Widgetの日の出・日の入り・月齢・月相表示
- Air Quality / UV WidgetのUS AQI、PM2.5、PM10、UV表示
- Camera / Video WidgetのSnapshot、YouTube、Video File、MJPEG、HLS表示
- X Timeline Widgetによる指定した公開X Accountの最近の投稿表示（上級者向け、X Developer Platform／Pay Per Use／Server-side Bearer Tokenが必要）
- スマートフォンでの左右スワイプによるタブ切り替え
- Feed／Calendar読込中のSpinner表示
- 記事リンクのStock保存と一覧表示
- Bootstrapテーマ、Navbarリンク、タブ名のユーザー設定
- MySQL 8系での新規DB構築
- configurable table prefix（例: `rss_`）

Feed item本文はDBへ永続化せず、登録されたFeed URLから表示時に取得します。

Version 1.1.0では、記事URLのTracking Parameter除去、Item Identityを使った新着表示、Dashboard Widget配置基盤、タイトルバーからの並び替え、Clock Widget、Memo Widget、Task Widget、Calendar Widgetを追加しています。Feed本体は従来の`content`、Memo本文は`memo`、Task項目は`task`、通常予定は`calendar_event`を正本とし、各Widgetの配置・表示設定は`dashboard_widget`へ保存します。Calendar上のTask期限は`task`を直接参照し、予定Tableへ複製しません。V1.1-I / R2ではスマートフォン幅に限って左右スワイプによるタブ切り替えを追加し、Calendar、入力欄、Button、Link、Modal、Drawer、Widget並び替えHandleでは誤操作を避けるため無効にしています。FeedとCalendarの読込中は文字に加えてSpinnerを表示します。V1.1-I / R3ではスマートフォンのTask期限入力だけを2段配置へ調整しました。V1.1-JではAccount Settingsを追加し、現在のパスワード確認後にメールアドレスまたはパスワードを変更できます。

## Version 1.1 progress

| Work unit | 内容 | 状態 |
|---|---|---|
| V1.1-A | Baseline・DB・工程分析 | 完了 |
| V1.1-B | Tracking Parameter除去 | 完了 |
| V1.1-C | 新着NEW表示・Feed item state | 完了 |
| V1.1-D | Dashboard Widget配置基盤・既存Feed移行 | 完了 |
| V1.1-E | タイトルバーのDrag & Drop・並び順保存 | 完了 |
| V1.1-F | Clock Widget | 完了 |
| V1.1-G | Memo Widget | 完了 |
| V1.1-H | Task Widget | 完了 |
| V1.1-I | Calendar Widget／R2操作性改善／R3 Task期限欄調整 | 完了 |
| V1.1-J | Account Settings | 完了 |
| V1.1-K | 統合回帰・Version 1.1.0 Release | 完了 |

V1.1-Cの仕様は[`docs/v1-1-c-implementation.md`](docs/v1-1-c-implementation.md)、V1.1-DのWidget基盤は[`docs/v1-1-d-implementation.md`](docs/v1-1-d-implementation.md)、Migrationは[`docs/v1-1-d-migration.md`](docs/v1-1-d-migration.md)、V1.1-Eの並び替えは[`docs/v1-1-e-implementation.md`](docs/v1-1-e-implementation.md)、V1.1-FのClockは[`docs/v1-1-f-implementation.md`](docs/v1-1-f-implementation.md)、V1.1-GのMemoは[`docs/v1-1-g-implementation.md`](docs/v1-1-g-implementation.md)、Migrationは[`docs/v1-1-g-migration.md`](docs/v1-1-g-migration.md)、V1.1-HのTaskは[`docs/v1-1-h-implementation.md`](docs/v1-1-h-implementation.md)、Migrationは[`docs/v1-1-h-migration.md`](docs/v1-1-h-migration.md)、V1.1-IのCalendarは[`docs/v1-1-i-implementation.md`](docs/v1-1-i-implementation.md)、Migrationは[`docs/v1-1-i-migration.md`](docs/v1-1-i-migration.md)、R2のスワイプ／Spinnerは[`docs/v1-1-i-r2-implementation.md`](docs/v1-1-i-r2-implementation.md)、Account Settingsは[`docs/v1-1-j-implementation.md`](docs/v1-1-j-implementation.md)、Version 1.1.0最終化は[`docs/v1-1-k-implementation.md`](docs/v1-1-k-implementation.md)を参照してください。


## Version 1.2 progress

| Stage | 内容 | 状態 |
|---|---|---|
| V1.2-A／第1段 | Login・Registration近代化、Honeypot、Logout／Session expiry通知、403／404／500／503共通Error | 完了 |
| V1.2-B／第2段 | 記事Title表示、全文Tooltip、RSS概要Accordion、Feed Card個別更新 | 完了 |
| V1.2-C／第3段 | Search Feed、見出し・概要・通知の調整 | 完了 |
| V1.2-D／第4段 | 共通記事Actions、Stock、URL Copy、X投稿、Task追加、操作領域調整 | 完了 |
| Version 1.2 Release | 統合回帰、Documentation、Package、Version 1.2.0確定 | 完了 |

Version 1.2.0では、認証画面を専用UIへ更新し、Honeypot、Logout／Session expiry通知、403／404／500／503の共通Error画面を追加しました。記事表示では、最大2行Title、全文Tooltip、Plain Textの概要Accordion、Feed Card単位の個別更新を追加しています。

Search Feedは既存のDashboard Widget基盤とFeed取得経路を再利用し、通常RSSと共通の記事描画を使用します。記事Actionsは画面内で1つの共通Menuを使い、Stock保存、記事URLのCopy、X投稿画面、記事TitleのみのTask追加へ対応しました。三点リーダー、概要「＋」、新着Bellの操作性を維持しながら、記事Titleの表示領域も調整しています。

Version 1.2ではDB Table／Column、Migration、SQL、必須設定、外部Library、Build環境の追加はありません。Version 1.1.0からの更新はCode差し替えとBrowser Cache更新が中心です。詳細は[`RELEASE_NOTES.md`](RELEASE_NOTES.md)、[`docs/v1-2-release-implementation.md`](docs/v1-2-release-implementation.md)、[`docs/test-report-v1-2-release.md`](docs/test-report-v1-2-release.md)を参照してください。

## Secure Baselineで完了した範囲

| Work unit | 内容 | 状態 |
|---|---|---|
| SB-00 | Legacy evidence freeze | 完了 |
| SB-01 | Public/private boundary・秘密情報分離 | 完了 |
| SB-02 | PDO / DB access foundation | 完了 |
| SB-03 | Session foundation | 完了 |
| SB-04 | Authentication / password | 完了 |
| SB-05 | API contract / dispatcher | 完了 |
| SB-06 | Authorization / ownership | 完了 |
| SB-07 | CSRF | 完了 |
| SB-08 | Validation | 完了 |
| SB-09 | SSRF-safe outbound fetch / TLS | 完了 |
| SB-10 | XSS-safe output | 完了 |
| SB-11 | Legacy functional bug fixes | 完了 |
| SB-12 | PHP 8 runtime stabilization / Atom link fix | 完了 |
| SB-13 | Schema / integrity / table prefix | 完了 |
| SB-14 | Final security / regression matrix | 完了 |
| SB-15 | Documentation / Initial Commit gate | 完了 |

詳細は [`docs/modernization.md`](docs/modernization.md) と [`docs/change-map.md`](docs/change-map.md) を参照してください。


## M1 progress

M1 completion checkpoint: `RSS Engine M1-G / R1`

| Work unit | 内容 | 状態 |
|---|---|---|
| M1-A | Fetcher / Parser責務分離 + Normalized Item | 完了 |
| M1-B | Feed Source model | 完了 |
| M1-C | RSS 2.0 / RSS 1.0 / Atom Adapter整理 + Date normalization | 完了 |
| M1-D | Item identity | 完了 |
| M1-E | Server-side cache + 重複Fetch抑制 | 完了 |
| M1-F | ETag / Last-Modified / HTTP 304 | 完了 |
| M1-G | Fetch state + Retry / stale-if-error | 完了 |

M1-Aの詳細は [`docs/m1-a-implementation.md`](docs/m1-a-implementation.md)、M1-Bは [`docs/m1-b-implementation.md`](docs/m1-b-implementation.md)、M1-Cは [`docs/m1-c-implementation.md`](docs/m1-c-implementation.md)、M1-Dは [`docs/m1-d-implementation.md`](docs/m1-d-implementation.md)、M1-Eは [`docs/m1-e-implementation.md`](docs/m1-e-implementation.md)、M1-Fは [`docs/m1-f-implementation.md`](docs/m1-f-implementation.md)、M1-Gは [`docs/m1-g-implementation.md`](docs/m1-g-implementation.md) を参照してください。

## M2 progress

| Work unit | 内容 | 状態 |
|---|---|---|
| M2-A | Frontend基盤整理 | 完了 |
| M2-B | Feed表示処理整理 | 完了 |
| M2-C | HTML構造・Accessibility | 完了 |
| M2-D | Responsive・UI / UX | 完了 |
| M2-E | 不要Frontend Asset整理 | 完了 |
| M2-F | Frontend依存関係更新 | 完了 |
| M2-G | 最終回帰・Documentation | 完了 |

M2-Aの詳細は [`docs/m2-a-implementation.md`](docs/m2-a-implementation.md)、M2-Bは [`docs/m2-b-implementation.md`](docs/m2-b-implementation.md)、M2-Cは [`docs/m2-c-implementation.md`](docs/m2-c-implementation.md)、M2-Dは [`docs/m2-d-implementation.md`](docs/m2-d-implementation.md)、M2-Eは [`docs/m2-e-implementation.md`](docs/m2-e-implementation.md)、M2-Fは [`docs/m2-f-implementation.md`](docs/m2-f-implementation.md)、M2-Gは [`docs/m2-g-implementation.md`](docs/m2-g-implementation.md) を参照してください。M2全体の要約は [`docs/m2-completion-summary.md`](docs/m2-completion-summary.md)、test結果は [`docs/test-report-m2-a.md`](docs/test-report-m2-a.md) から [`docs/test-report-m2-g.md`](docs/test-report-m2-g.md) に記録しています。

## M4 progress

M4は新機能追加ではなく、Version 1.0.0の正式公開準備です。M4-AではM2-GをRelease Baselineとして固定し、M4-BではREADME、CHANGELOG、Project License、Third-party noticeを実際の配布Assetへ合わせました。M4-Cでは新規設置、更新、設定、Backup、Restore、Rollbackを実コードと設定Defaultに合わせて整理しました。M4-DではGitHub Actionsの最小CI、Security reporting、Contribution方針、Repository設定Checklist、Portfolio掲載用メモを追加しました。M4-EではCheckpoint ZIPとRuntime Release ZIPを分離し、deterministic build、内部Manifest、外部SHA-256、Release Notes、Tag / GitHub Release手順を追加しました。Application機能、DB、公開API、Security境界、Frontend Runtime Assetは変更していません。M4-Fでは`1.0.0-rc1`を作成し、実MySQL、Feed、Browser、Restore結果をPrivate Evidenceへ記録するGateを追加しました。M4-GではRC1からApplication Runtimeを変更せず、Version、Release Notes、Final Package、Tag / GitHub Release手順を`1.0.0`へ確定しました。自動RegressionとPackage検証はPASSしていますが、Privateな実環境EvidenceはRepositoryへ収録していません。

| Work unit | 内容 | 状態 |
|---|---|---|
| M4-A | Release基準・公開物・残課題の棚卸し | 完了 |
| M4-B | README・CHANGELOG・License・Third-party notice | 完了 |
| M4-C | 設置・更新・Backup・復旧手順 | 完了 |
| M4-D | GitHub公開状態・Repository・Portfolio・最小CI | 完了 |
| M4-E | 配布ZIP・Release Notes・SHA-256・Tag手順 | 完了 |
| M4-F | Release Candidate全回帰・実環境確認 | RC作成・自動検証完了 / 実環境Evidence未収録 |
| M4-G | 最終Quality Gate・Version 1.0.0確定 | 完了 |

詳細は [`docs/m4-f-implementation.md`](docs/m4-f-implementation.md)、[`docs/m4-f-validation.md`](docs/m4-f-validation.md)、[`docs/m4-e-implementation.md`](docs/m4-e-implementation.md)、[`docs/release-package.md`](docs/release-package.md)、[`docs/tag-and-github-release.md`](docs/tag-and-github-release.md)、[`RELEASE_NOTES.md`](RELEASE_NOTES.md)、[`docs/m4-d-implementation.md`](docs/m4-d-implementation.md)、[`docs/ci.md`](docs/ci.md)、[`docs/github-publication.md`](docs/github-publication.md)、[`docs/portfolio.md`](docs/portfolio.md)、[`docs/installation.md`](docs/installation.md)、[`docs/update.md`](docs/update.md)、[`docs/configuration.md`](docs/configuration.md)、[`docs/backup-and-restore.md`](docs/backup-and-restore.md)、[`docs/rollback.md`](docs/rollback.md)、[`docs/release-gate-v1.0.0.md`](docs/release-gate-v1.0.0.md) を参照してください。

## Version 1.2.0 release package

GitHub作業Folder相当の完全統合ZIPと、Server配置用Runtime ZIPを分けて生成します。

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.2.0-complete.zip \
  ../release-output/rss-reader-modernization-1.2.0-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.2.0.zip \
  ../release-output/rss-reader-modernization-1.2.0.zip.sha256
```

Package範囲は[`docs/release-package.md`](docs/release-package.md)、Tag / GitHub Release手順は[`docs/tag-and-github-release.md`](docs/tag-and-github-release.md)、検証限界は[`RELEASE_NOTES.md`](RELEASE_NOTES.md)を参照してください。

## Runtime requirements

- PHP 8.1+
- PDO + `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQL / MariaDB（新規環境ではMySQL 8系で確認）
- WebサーバーのDocumentRootを `public/` に設定できる構成

`tools/healthcheck.php` はCLI専用です。PHP拡張、設定、Runtime directory、Public Assetを確認しますが、DatabaseへLoginはしません。DB接続とSchemaは `php tools/db_sb13.php verify` または実動作で確認してください。コマンドを利用できない環境では、Hosting control panelとBrowserで確認します。

## Installation — new empty database

詳細手順: [`docs/installation.md`](docs/installation.md)


データ保全が不要な新規環境では、Legacy DBをALTERするより新しい空DBを作る方法を推奨します。

1. 配布物を配置する。
2. WebサーバーのDocumentRootを `public/` にする。
3. `config/local.php.example` を参考に、公開領域外の `config/local.php` を作成する。
4. MySQL 8側で空DBを作成する。
5. `DB_NAME` と `DB_TABLE_PREFIX` を設定する。
6. `database/schema.sql` 冒頭の `@table_prefix` を同じ接頭辞にする。
7. phpMyAdminで新DBを選択し `database/schema.sql` を実行する。
8. アプリから新規ユーザー登録し、ログインして動作確認する。
9. 必要なら `database/audit/postflight.sql` でSchemaを確認する。

例:

```php
return [
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,
    'APP_HASH_KEY' => 'replace-with-a-long-random-secret',

    'DB_DRIVER' => 'mysql',
    'DB_HOST' => 'db-host',
    'DB_PORT' => '3306',
    'DB_NAME' => 'rss_reader',
    'DB_USER' => 'rss_user',
    'DB_PASSWORD' => 'replace-with-a-strong-password',
    'DB_TABLE_PREFIX' => 'rss_',
];
```

`schema.sql`:

```sql
SET @table_prefix = 'rss_';
```

Prefix `rss_` の場合、V1.1-I時点では次の9テーブルを作成します。

```text
rss_user_info
rss_user_conf
rss_content
rss_content_stock
rss_feed_item_state
rss_memo
rss_task
rss_calendar_event
rss_dashboard_widget
```

Clock専用Tableは作成せず、表示設定は`rss_dashboard_widget.widget_config`へ保存します。Memo本文は`rss_memo`、Task項目は`rss_task`、通常予定は`rss_calendar_event`、配置は`rss_dashboard_widget`へ分けて保存します。Task期限はCalendar表示時に`rss_task`から直接読みます。

SQLファイルはPHP設定を直接参照できないため、**`DB_TABLE_PREFIX` と `@table_prefix` は同じ値にしてください。**

## Existing Legacy DB migration

更新・Backup・Restore・Rollbackは [`docs/update.md`](docs/update.md)、[`docs/backup-and-restore.md`](docs/backup-and-restore.md)、[`docs/rollback.md`](docs/rollback.md) を参照してください。


既存DBを保持して移行する場合だけ、次の順序を使用します。

```text
database/audit/preflight.sql
→ 結果確認
→ database/migrations/001_sb13_integrity.sql
→ database/audit/postflight.sql
```

Version 1.1のTableを既存DBへ追加する場合は、Backup後に次も順番に適用します。

```text
database/migrations/002_v1_1_feed_item_state.sql
→ php tools/db_v11c.php verify
→ database/migrations/003_v1_1_dashboard_widget.sql
→ php tools/db_v11d.php verify
→ database/migrations/004_v1_1_memo.sql
→ php tools/db_v11g.php verify
→ database/migrations/005_v1_1_task.sql
→ php tools/db_v11h.php verify
→ database/migrations/006_v1_1_calendar_event.sql
→ php tools/db_v11i.php verify
```

V1.1-Gでは`memo`Table、V1.1-Hでは`task`Table、V1.1-Iでは`calendar_event`TableのMigrationが必要です。CLIを利用できる場合は、各段階のBackup確認後に`db_v11g.php`、`db_v11h.php`、`db_v11i.php`を順番にapply / verifyします。

Migration前に必ずDB全体をバックアップしてください。Duplicate identityやorphan等を自動削除・統合する設計にはしていません。

新DBから開始する場合、`preflight.sql` と `001_sb13_integrity.sql` は不要です。

## Production configuration

設定の全項目とDefaultは [`docs/configuration.md`](docs/configuration.md)、配置確認は [`docs/deployment-checklist.md`](docs/deployment-checklist.md) を参照してください。


実環境では少なくとも次を確認してください。

- `APP_DEBUG=false`
- `APP_HASH_KEY` は十分に長いランダム値を使用し、運用開始後は安易に変更しない
- `APP_HASH_KEY` は既存ユーザーのログインIdentity生成に必要なため、紛失しないよう安全にバックアップする
- `config/local.php` はGit管理外・DocumentRoot外
- `REGISTRATION_ENABLED` は運用方針に合わせて設定
- `var/session/`、`var/security/login-throttle/`、`var/cache/feed/` がPHPから書込み可能
- X Timelineを利用する場合は`APP_X_BEARER_TOKEN`をServer側Secretとして設定し、`var/cache/x/`をPHPから書込み可能にする。利用しない場合は空のままでよい
- Feed cacheは `APP_FEED_CACHE_ENABLED=true`、`APP_FEED_CONDITIONAL_REQUEST_ENABLED=true`、`APP_FEED_CACHE_TTL_SECONDS=60`、`APP_FEED_CACHE_LOCK_TIMEOUT_MS=9000` が初期値
- Retryは `APP_FEED_RETRY_ENABLED=true`、最大待機 `3600` 秒、stale-if-errorは有効・最大 `86400` 秒が初期値
- `var/log/` を利用する場合もDocumentRoot外
- HTTPSを使用

詳細: [`docs/security.md`](docs/security.md)

## Tests

```bash
bash tests/run.sh
```

GitHub Actionsでも同じRunnerをPHP 8.1 / 8.4で実行します。CIの範囲と初回確認は [`docs/ci.md`](docs/ci.md) を参照してください。

SB-14の最終Matrixでは、Authentication、Authorization/IDOR、CSRF、SSRF、XSS、Parser、4タブ、DB integrity、table prefix、repository leak scan、PHP 8 runtimeを横断して検証しています。

Build環境では `pdo_mysql` / cURL / SimpleXML / mbstringが揃わないため、実MySQL/cURL/SimpleXML E2Eはローカルでは完全実行できません。代替としてFake PDO/transport、fixture、static invariantを使用し、M1-AではFetcher境界・Normalized Item・API contract・Security ordering、M1-BではFeedSource/Mapper、owner再検証、異常DB rowのfail-closed、SSRF継承、M1-CではAdapter dispatch、Date normalization、Atom `published` fallback、namespace/link/content/date fixture、XML network禁止、M1-DではGUID / `rdf:about` / Atom `id`抽出、link/fingerprint fallback、Feed URL scope、identity安定性・非公開API契約、M1-EではTTL境界、破損Cache復旧、atomic write、権限・symlink拒否、Cache無効化、5 process同時実行時の単一Fetchを確認し、M1-FではETag / Last-Modified検証、redirect時の非漏えい、HTTP 304本文再利用、schema 1互換、5 process同時revalidationを確認し、M1-GではRetry-After、エラー分類、Fetch state、Backoff境界、stale age境界、Security error非隠蔽、5 process同時障害時の単一Fetchと単一失敗記録を専用testで確認しています。M2-Cではdoctype / lang / landmark / Form / Button / Label / fieldset、Feedのaria-busy / live region、DrawerのEscape / Tab循環 / focus return、ModalとPage Topのfocus移動を確認しています。M2-Dではresponsive class、長いURL、Touch target、明示的な削除、画面内通知、Feed再読込、Feed / Stockの実描画をstatic test、Node runtime、Fake PDO renderで確認しています。M2-Eでは直接参照Asset、Theme、CSS内Font参照、Icon定義、削除対象、License header、HTTP 200を確認し、M2-FではjQuery full build、Bootstrap plugin互換、8テーマ、Font Awesome alias / WebFont、script読込順、旧Asset不存在を専用testで確認しています。M2-GではM2-A〜GのDocumentation、現在Version、Asset allowlist、主要Frontend invariant、Runtime公開面、配布手順を横断して再確認しています。headless browser smokeはharnessを用意していますが、Build環境ではruntime不足のためSKIPします。配置先ではMySQL 8のCRUDと実RSS/Atomを手動確認してください。

詳細: [`docs/test-report-sb14.md`](docs/test-report-sb14.md) / [`docs/test-report-sb15.md`](docs/test-report-sb15.md) / [`docs/test-report-m1-a.md`](docs/test-report-m1-a.md) / [`docs/test-report-m1-b.md`](docs/test-report-m1-b.md) / [`docs/test-report-m1-c.md`](docs/test-report-m1-c.md) / [`docs/test-report-m1-d.md`](docs/test-report-m1-d.md) / [`docs/test-report-m1-e.md`](docs/test-report-m1-e.md) / [`docs/test-report-m1-f.md`](docs/test-report-m1-f.md) / [`docs/test-report-m1-g.md`](docs/test-report-m1-g.md) / [`docs/test-report-m2-a.md`](docs/test-report-m2-a.md) / [`docs/test-report-m2-b.md`](docs/test-report-m2-b.md) / [`docs/test-report-m2-c.md`](docs/test-report-m2-c.md) / [`docs/test-report-m2-d.md`](docs/test-report-m2-d.md) / [`docs/test-report-m2-e.md`](docs/test-report-m2-e.md) / [`docs/test-report-m2-f.md`](docs/test-report-m2-f.md) / [`docs/test-report-m2-g.md`](docs/test-report-m2-g.md)

## Security model

主な境界は以下です。

- 認証済みSessionの `user_id` を所有者の唯一の根拠にする
- APIはPOST + explicit action + CSRF
- SQLはPDO parameter binding
- Passwordは `password_hash()` / `password_verify()`
- Login throttle
- Feed fetchはHTTP/HTTPSのみ、DNS/IP/redirect/TLS/size/timeoutを検証
- Feed/DB由来データはvalidate/escapeして描画
- Stock作成時に記事ページを再Fetchしない
- Runtime/session/log/secrets/DB dumpを公開物から分離

詳細: [`docs/security.md`](docs/security.md)

## Legacy and data policy

Legacy版は比較・解析対象として保持し、Secure BaselineのRuntimeへ混在させません。旧DB dumpには運用データやcredential情報が含まれていたため、GitHub対象から除外します。

既存ユーザーcredentialの互換性は要件から外し、不明なLegacy形式を推測して移行しません。Secure Baselineでは新規 `password_hash()` 形式を基準とします。

詳細: [`docs/legacy-analysis.md`](docs/legacy-analysis.md)

## Current limitations / deferred modernization

Secure Baseline以降も、M2-G完了時点では次を残しています。

- Server-side cacheは固定TTL + ETag / Last-Modified + 最大24時間のstale-if-error。FrontendはLoading / Empty / Errorを表示するが、Cache / Retryの内部状態は公開しない
- Feed提供元がValidatorを返さない場合はTTL経過後に通常のHTTP 200取得
- Feed取得は表示時の同期処理
- Foreign Key未導入
- Dashboard固有JS/CSS、Feed描画、semantic HTML、Keyboard / Focus / ARIA、Responsive layout、基本的な表示文言と通知は整理済み
- 未使用Frontend配布物はM2-Eで整理済み。jQueryとFont AwesomeはM2-Fで互換更新済み。Bootstrap / Bootswatch 4.1.3、Popper 1系、Drawer、iScrollのmajor migrationと、全Themeのcontrast最終調整は別途検討
- Source abstractionはFetcher / FeedSource / Parser dispatcher / RSS 2.0・RSS 1.0・Atom Adapter / Normalized Item / deterministic Item identity / cache-aware Feed serviceまで導入済み
- X Timelineは公開Accountの最近の投稿をRead Onlyで表示する範囲に限定。X本体の「おすすめ / For You」Feedの再現と、User Context OAuthを使うHome Timelineは将来課題

これらはM2完了後の別工程へ意図的に分離しています。

## Roadmap

```text
Secure Baseline SB-15 / R3
  ↓
M1 Source / RSS Engine (M1-G complete)
  ↓
M2 Frontend (M2-G complete)
  ↓
M4 Release preparation (M4-F RC prepared / real environment HOLD)
  ↓
Version 1.0.0 / Portfolio
```

M1ではRSS専用処理に固定しすぎず、将来のJSON Feed、REST API、HTML等も同じItemモデルへ正規化できるSource / Fetcher / Parser(Adapter)構成へ段階的に移行します。M1-AでFetcher / Parser分離と共通Itemモデル、M1-Bでowner-scoped contentからFeedSourceへの変換境界、M1-Cで形式別Adapterと共通Date normalizer、M1-DでFeed URL scope付きの決定的Item identity、M1-Eで正常Feed本文のServer-side cacheとURL単位の重複Fetch抑制、M1-FでETag / Last-Modified / HTTP 304による再確認、M1-GでFetch state、Retry-After、Backoff、期限付きstale-if-errorまで完了しています。

詳細: [`docs/roadmap.md`](docs/roadmap.md)

## GitHub repository / Portfolio

公開Repositoryには、読取専用のGitHub Actions CI、Security reporting、Contribution方針、Bug report templateを収録しています。WorkflowはDeployやReleaseを行わず、`main`へのpush / Pull Requestで既存Regressionを実行します。

- CI: [`.github/workflows/ci.yml`](.github/workflows/ci.yml) / [`docs/ci.md`](docs/ci.md)
- Security report: [`SECURITY.md`](SECURITY.md)
- Contribution: [`CONTRIBUTING.md`](CONTRIBUTING.md)
- GitHub設定: [`docs/github-publication.md`](docs/github-publication.md)
- Portfolio掲載用メモ: [`docs/portfolio.md`](docs/portfolio.md)

M4-D以降のpush後、GitHub hosted runnerのPHP 8.1 / 8.4 Job、Private vulnerability reporting、Repository Description / Topicsを画面で確認してください。GitHub hosted runnerのPHP 8.1 / 8.4結果は、M4-F Evidenceへrun番号またはURLを記録します。Connectorからstatusを取得できない場合はActions画面で確認します。

## License and third-party components

Project独自codeとModernizationで追加・変更した部分は [`LICENSE`](LICENSE) のMIT Licenseで公開します。同梱するFrontend libraryには各上流Licenseが適用され、ProjectのMIT Licenseで再Licenseしません。

現在の主な同梱VersionはjQuery 3.7.1、Font Awesome Free 6.7.2、Bootstrap / Bootswatch 4.1.3、jquery-drawer 3.2.2、iScroll 5.2.0-snapshotです。詳細は [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md) と [`docs/dependencies.md`](docs/dependencies.md) を参照してください。

## Repository safety

Gitへ入れないもの:

- `config/local.php`
- real `.env`
- production DB dump / backup
- Legacy `rss.sql`, `rss.zip`
- logs
- PHP session files
- login throttle state
- migration snapshots
- runtime Feed cache / lock / fetch state files
- private keys / API keys

Sanitizedされた `database/` のschema/audit/migration/fake fixtureだけを例外としてVersion管理します。

詳細: [`docs/sensitive-data-manifest.md`](docs/sensitive-data-manifest.md)

## Initial Commit status

SB-15のInitial Commit gateは合格と判定しています。根拠と公開前に残る作業は [`docs/initial-commit-gate.md`](docs/initial-commit-gate.md) を参照してください。

**注意:** Initial Commit可能と「公開GitHub Release可能」は同義ではありません。M4-Fで`1.0.0-rc1`と検証Gateを作成しましたが、RC ZIPは`publishable=no`です。実MySQL / Browser / Feed / RestoreのEvidenceが全項目PASSになった後、M4-GでVersion 1.0.0とTagを確定します。


## V1.2-C
Search Feed（登録RSS横断検索、共通RSS、AND/OR、カード個別更新）を追加。DB Schema変更なし。
