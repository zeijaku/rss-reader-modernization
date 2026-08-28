# 新規設置手順

## 対象

空のMySQL DatabaseへRSS Reader Modernizationを新規設置する手順です。既存Legacy DBを保持して移行する場合は、この手順で `schema.sql` を上書き実行せず、後半のLegacy DB migrationを使用します。

## 1. 必要な環境

- PHP 8.1以上
- PDO / `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQLまたはMariaDB。新規構築の確認基準はMySQL 8系
- Web DocumentRootをProject内の `public/` に設定できること

`app/`、`config/`、`database/`、`var/` をWeb公開しないことが前提です。

## 2. 配置前に決めるもの

- Database名
- Database userと必要最小限の権限
- Table prefix。例: `rss_`
- Registrationを開けるか
- `APP_HASH_KEY`
- Logを有効にするか

`APP_HASH_KEY` はLogin identityのHMACに使用します。運用開始後に変更すると、同じEmailから同じIdentityを生成できなくなるため、最初に決めて安全にBackupします。

PHP CLIが使える場合の生成例:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

生成値はGit、Documentation、Screenshot、Ticketへ貼らないでください。

## 3. Projectを配置

配布ZIPは既存公開folderへ直接解凍せず、別folderへ展開して内容を確認します。

確認するもの:

- Top-level directoryが1つ
- `config/local.php`、実DB、Log、Session、Cache、入れ子ZIPがない
- `LICENSE`、`THIRD_PARTY_NOTICES.md`、`licenses/` がある
- SHA-256がRelease記載値と一致する

Web serverのDocumentRootは次へ向けます。

```text
<project>/public
```

Project root自体をDocumentRootにしないでください。

## 4. Private設定を作る

推奨は `config/local.php.example` をコピーして `config/local.php` を作る方法です。

```powershell
Copy-Item .\config\local.php.example .\config\local.php
```

`config/local.php` はGit管理外で、配布ZIPにも含めません。

環境変数を使う場合は `config/.env.example` の名前を参考に、Hosting control panel、Web server、PHP-FPM等へ設定します。このApplicationは `.env` fileを自動読込しません。`.env.example` を `.env` へコピーするだけでは設定されません。

設定の優先順位:

```text
Environment variable
    ↓
config/local.php
    ↓
Application default
```

詳細は [`configuration.md`](configuration.md) を参照してください。

## 5. Databaseを作る

空Databaseと専用userを作成します。Database passwordをCommand lineへ直接書かず、`-p` でPrompt入力します。

例:

```text
Database: rss_reader
User:     rss_user
Prefix:   rss_
```

`config/local.php` の次を設定します。

```php
'DB_DRIVER' => 'mysql',
'DB_HOST' => 'db-host',
'DB_PORT' => '3306',
'DB_NAME' => 'rss_reader',
'DB_USER' => 'rss_user',
'DB_PASSWORD' => 'replace-with-your-db-password',
'DB_TABLE_PREFIX' => 'rss_',
```

## 6. Schemaと現行Migrationを投入

`database/schema.sql` は、Migration `008_v1_7_widget_height.sql` までのBase schemaに加え、V1.20.1の`calendar_event_color`（Migration 013）、V1.24のStock状態Column（Migration 017）、V1.25のCalendar終日／時刻／URL（Migration 018）と繰り返し（Migration 019）を取り込んでいます。Mail / Links / Stock Tags / RSS Highlightに加え、V1.22のFeed Metadata / Feed Health / RSS Rulesは009〜012、014〜016を番号順に適用します。

まず `database/schema.sql` 冒頭の値を、`DB_TABLE_PREFIX` と同じにします。

```sql
SET @table_prefix = 'rss_';
```

MySQL CLI例:

```powershell
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\schema.sql
```

その後、現行機能に必要なMigrationを**番号順**に適用します。各SQLの `SET @table_prefix` も `DB_TABLE_PREFIX` と同じ値へ変更してください。

```text
009_v1_9_mail_account.sql
010_v1_10_links.sql
011_v1_11_stock_tags.sql
012_v1_12_feed_keywords.sql
014_v1_22_opml_feed_metadata.sql
015_v1_22_feed_health.sql
016_v1_22_rss_rules.sql
```

CLI例:

```powershell
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\009_v1_9_mail_account.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\010_v1_10_links.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\011_v1_11_stock_tags.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\012_v1_12_feed_keywords.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\014_v1_22_opml_feed_metadata.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\015_v1_22_feed_health.sql
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\migrations\016_v1_22_rss_rules.sql
```

phpMyAdminを使用する場合も、空Databaseへ `schema.sql` をImportした後、009〜012、014〜016を同じ順番でImportします。V1.20.1のCalendar色Column（013）、V1.24のStock状態Column（017）、V1.25のCalendar終日／時刻／URL／繰り返しColumn（018 / 019）は`schema.sql`へ統合済みのため、新規Installで013 / 017 / 018 / 019を追加実行する必要はありません。

Prefixが `rss_` の場合、最終的に次の19 tableが存在します。

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
rss_remember_token
rss_mail_account
rss_link_item
rss_stock_tag
rss_stock_tag_map
rss_feed_keyword
rss_feed_metadata
rss_feed_health
rss_rss_rule
rss_rss_rule_condition
```

**既存Databaseへ `schema.sql` を再実行しないでください。** 既存環境はBackupを取得し、未適用Migrationだけを順番に適用します。

V1.23.0からV1.24.0へ更新する既存Databaseでは、Backup取得後に `017_v1_24_stock_state.sql` の `SET @table_prefix` を環境へ合わせて適用します。Migration 017は既存Stockを保持したまま `stock_processed` / `stock_important` / `stock_archived` をDefault 0で追加し、Archive検索用Indexを追加します。`stock_flag` は従来どおりStock解除用で、Archiveとは別状態です。

V1.24.0からV1.25.0へ更新する既存Databaseでは、Backup取得後に次を**この順番で1回ずつ**適用します。

```text
018_v1_25_calendar_event_time_url.sql
→ 019_v1_25_calendar_recurrence.sql
```

Migration 018は既存`calendar_event`へ終日Flag、開始／終了時刻、関連URLを追加します。既存予定はDefaultで終日となり、時刻とURLはNULLのままです。Migration 019は繰り返し種別と任意の繰り返し終了日を追加し、既存予定は`none`のまま維持します。両Migrationとも `SET @table_prefix` を実環境の `DB_TABLE_PREFIX` と同じ値へ合わせてから実行してください。V1.25-F R3までの本番確認ですでに018 / 019を適用済みの場合は、正式V1.25.0化で再実行しません。

## 7. Runtime directory

PHP processから次へ書込みできるようにします。

```text
var/session/
var/security/login-throttle/
var/cache/feed/
var/log/                 Logを使う場合
var/db-migration/         Legacy migrationを行う場合
```

これらは `public/` 外に置きます。Hostingごとに実行userが異なるため、無条件に `777` へする手順は採用しません。Owner / groupを確認し、必要最小限の書込み権限を設定してください。

## 8. CLI確認

```powershell
php -v
php tools/healthcheck.php
php tools/db_sb13.php verify
bash tests/run.sh
```

`tools/healthcheck.php` はPHP拡張、設定、Runtime directory、Public Assetを確認しますが、DatabaseへLoginしません。Database接続とSchemaは `php tools/db_sb13.php verify` またはApplication実動作で確認します。

CLIが使えないHostingでは、Control panelでPHP Version / Extensionを確認し、BrowserからRegistration、Login、Feed CRUD、Stock、Settingsを確認します。

## 9. Browser確認

- HTTPSでAccessできる
- Registration方針どおりの表示
- 新規user作成
- Login / Logout
- 4タブ
- Feed追加、変更、削除、再読込
- Clock、Memo、Task、Calendarの追加、変更、削除
- Taskの完了切替、期限、優先度
- Calendarの月移動、通常予定、Task期限表示
- Calendarの終日／時刻／関連URL、赤／青／緑、毎日／毎週／毎月／毎年の繰り返し
- CalendarのToday、14日以内の直近予定、3件＋もっと見る、月切替時の表示安定性
- RSS / Stock記事の「Calendarへ追加」でTitle／URLが登録Modalへ引き継がれる
- Calendar Modalを背景／×／閉じる／Escで閉じてもConsoleへ新しいFocus／`aria-hidden`警告が出ない
- RSS 2.0 / RSS 1.0 / Atom
- Stock保存と一覧
- Stockの未処理 / 処理済み、通常 / 重要、Archive状態とFilter / 一括更新
- Settings保存
- Drawer / Modal / Keyboard / Focus
- JavaScript Console errorなし
- FooterのVersionが配布物と一致

確認完了まではDNS切替や一般公開を行わない方が安全です。

## Legacy DBを保持する場合

新規Schemaではなく、次の順で扱います。

```text
Database全体のBackupと検証
    ↓
database/audit/preflight.sql または php tools/db_sb13.php audit
    ↓
結果を確認
    ↓
database/migrations/001_sb13_integrity.sql
または php tools/db_sb13.php apply --backup-confirmed
    ↓
database/audit/postflight.sql または php tools/db_sb13.php verify
```

Duplicate identity、orphan、unexpected index等がある場合は自動削除・統合しません。停止して内容を確認します。
