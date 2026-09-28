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

## 6. Schemaを投入

Fresh Installでは `database/schema.sql` だけを1回実行します。

Current `schema.sql` は、過去Migration 001〜031で追加された現在必要なTable / Column / IndexをFresh Install用の完成形として統合しています。Fresh InstallではMigrationを追加実行しません。

まず `database/schema.sql` 冒頭の値を、`DB_TABLE_PREFIX` と同じにします。

```sql
SET @table_prefix = 'rss_';
```

MySQL CLI例:

```powershell
mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\database\schema.sql
```

phpMyAdminを使用する場合も、空Databaseへ `database/schema.sql` を1回Importします。

Prefixが `rss_` の場合、Current Fresh Installでは最終的に次の27 tableが存在します。

```text
rss_user_info
rss_user_conf
rss_content
rss_content_stock
rss_feed_item_state
rss_memo
rss_task
rss_calendar_event
rss_calendar_event_exception
rss_dashboard_widget
rss_notification
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
rss_user_file
rss_remote_connection
rss_auth_totp
rss_auth_recovery_code
rss_auth_session
rss_auth_audit_log
```

`database/migrations/` は既存Databaseを古いVersionから更新するために保持しています。Fresh Installでこれらを重ねて実行しません。

**既存Databaseへ `schema.sql` を再実行しないでください。** 既存環境の更新は [Update Guide](update.md) を使用し、Version固有Migrationの名前・適用順・注意点は [Historical Update / Migration History](update-history.md) を参照してください。

## 7. Runtime directory

PHP processから次へ書込みできるようにします。

```text
var/session/
var/security/login-throttle/
var/cache/
var/log/                 Logを使う場合
var/db-migration/         Legacy migrationを行う場合
```

`var/cache/` 配下ではFeed Cacheに加え、Reader Full Text / Reader Image等のCurrent機能が必要なPrivate Cacheを使用します。機能側でSubdirectoryを作成する場合があるため、PHP processが必要範囲を書き込めるようにします。

Remote Filesを使用する場合は、`APP_REMOTE_TEMP_DIR` で指定したPrivate temporary directoryも `public/` 外で書込み可能にしてください。

これらは `public/` 外に置きます。Hostingごとに実行userが異なるため、無条件に `777` へする手順は採用しません。Owner / groupを確認し、必要最小限の書込み権限を設定してください。

## 8. CLI確認

Production Runtime ZIPで新規設置した場合は、Runtime Packageに含まれるToolで次を確認します。

```powershell
php -v
php tools/healthcheck.php
php tools/db_sb13.php verify
```

`tools/healthcheck.php` はPHP拡張、設定、Runtime directory、Public Assetを確認しますが、DatabaseへLoginしません。Database接続とSchemaは `php tools/db_sb13.php verify` またはApplication実動作で確認します。

Repository cloneまたはComplete Source Packageを使用していて `tests/` が存在する場合は、追加でCurrent Gateを実行できます。

```powershell
bash tests/run-ci.sh
```

Production Runtime ZIPには `tests/` を含めないため、Runtime ZIPだけを配置したServerで `tests/run-ci.sh` を必須手順にしません。Package構成は [Release Package](release-package.md) を参照してください。

Historical Version固有の確認が必要な場合だけ、Complete Source / Git履歴上の `tests/run.sh` / `tests/run-v*.sh` を参照します。

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
- Calendarの終日／時刻／関連URL、赤／青／緑／黄／紫の5色、毎日／毎週／毎月／毎年の繰り返し
- CalendarのToday、14日以内の直近予定、3件＋もっと見る、月切替時の表示安定性
- Calendar ReminderとNotification Center
- RSS / Stock記事の「Calendarへ追加」でTitle／URLが登録Modalへ引き継がれる
- Calendar Modalを背景／×／閉じる／Escで閉じてもConsoleへ新しいFocus／`aria-hidden`警告が出ない
- RSS 2.0 / RSS 1.0 / Atom
- Reader Mode / Full Textを利用する場合は本文表示とImage Proxy経路
- Stock保存と一覧
- Stockの未処理 / 処理済み、通常 / 重要、Archive状態とFilter / 一括更新
- File LibraryのUpload／Preview／Download／Delete
- Remote Filesの接続確認、Directory操作、複数Upload／Download、File Library相互転送、Text Editor、Permission
- MailのPassword / Gmail OAuth2、受信／本文表示／検索／送信／返信／Sent保存／添付送信／受信添付Download
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
