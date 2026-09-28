# Historical Update / Migration History

この文書は、過去Releaseで必要だったVersion固有のUpdate / Migration / Config手順をHistorical Evidenceとして保持します。

現在の更新作業は [Update Guide](update.md) を使用し、最初に `../app/version.php`、[Release Notes](../RELEASE_NOTES.md)、[CHANGELOG](../CHANGELOG.md) を確認してください。

## 使い方

- 古いVersionから段階Upgradeする場合に、経由するReleaseのMigration名と注意点を確認する。
- すでに適用済みのMigrationを、正式Release化や再配置だけを理由に再実行しない。
- 既存Databaseへ `schema.sql` を再実行しない。
- Historical記述と現在のCode / Release Notesが食い違う場合は、GitHub `main` とCurrent Release情報を優先する。
- Productionへ適用する前にBackupを取得する。

## Release固有の履歴

## Historical: Version 1.36.0 update

## Version 1.35.4からVersion 1.36.0

V1.36.0はDashboard Notification CenterとCalendar Reminderを追加し、Calendar ModalとNavbarの操作性を改善するReleaseです。既存DatabaseではMigration 030 / 031を番号順に適用します。

1. Application code、`config/local.php`、Database、private runtime dataをBackupする。
2. `database/migrations/030_v1_36_notification_center.sql` と `031_v1_36_calendar_reminder.sql` の `@table_prefix` を実環境の `DB_TABLE_PREFIX` と同じ値へ合わせる。
3. 未適用の場合だけ030→031の順で各1回適用する。V1.36 checkpointで適用済みなら再実行しない。
4. 正式Runtime ZIPとSHA-256を確認し、本番Directory外へ展開する。
5. `config/local.php`、実DB、生成済み`var/` Data、Secretを維持したままApplication codeを更新する。
6. BrowserをReloadし、Footerが`RSS Reader Modernization 1.36.0`であることを確認する。
7. Notification CenterのBell / unread / read / mark-all-read / hide、通常Calendar Reminder、繰り返しOccurrence Reminderを確認する。
8. Calendarの開始日時変更で終了日時が同じDurationを保って追従すること、PC / SmartphoneのCalendar Modal、sticky Navbarを確認する。
9. Login / Logout、RSS、Stock、Task、Mail、File Library、Remote Files、SettingsのSmoke TestとConsole errorなしを確認する。
10. 問題があればSourceとDatabaseを同じBackup時点へ戻す。030 / 031適用後に旧Codeへ戻す場合は、旧Codeが新Table / Columnを参照しないことを確認し、DB rollbackの要否はBackup方針に従う。

```text
DB Migration                030_v1_36_notification_center.sql
                            031_v1_36_calendar_reminder.sql
New table                   notification
Existing table change       calendar_event_reminder Column追加
必須設定                    追加なし
Background delivery         追加なし（in-app通知のみ）
Browser Cache               APP_ASSET_REVISION=1.36.0
正式Tag / GitHub Release    v1.36.0（Release workflow全Gate通過後のみ）
```

## Version 1.33.0 update

## Version 1.32.0からVersion 1.33.0

V1.33.0はCalendar Enhancementです。既存予定を保持したままOccurrence単位編集／削除を追加するため、加算型Migration 025が必要です。正式版のVersion表示は`1.33.0`です。

1. Application、`config/local.php`、Database、private runtime dataをBackupする。
2. 実環境の`DB_TABLE_PREFIX`と`calendar_event_exception` Tableの有無を確認する。
3. Tableが無い場合だけ`database/migrations/025_v1_33_calendar_event_exception.sql`の`@table_prefix`を合わせて1回適用する。既存DBへ`schema.sql`は実行しない。
4. ZIPとSHA-256を確認し、本番Directory外へ展開する。
5. `config/local.php`、実DB、生成済み`var/`Data、Secretを維持したまま、配布物をApplication Rootへ相対Pathで上書きする。
6. Browserを完全Reloadし、Version、CSS／JavaScriptのHTTP 200とMIME type、Console errorなしを確認する。
7. 既存予定、5色、複数日連結、日／週／月、Occurrence単位編集／削除／復元、シリーズ全体操作を確認する。
8. Owner Scope、CSRF、XSS、Login／2FA／Step-up／SessionのSmoke Testを行う。
9. 問題があれば前Sourceへ戻し、Migration 025適用済みTableは空のまま残してもV1.32は参照しない。DBを戻す場合は事前BackupからApplicationと同じ時点へ戻す。

```text
DB Migration                025_v1_33_calendar_event_exception.sql
New table                   calendar_event_exception
必須設定                    追加なし
Browser Cache               APP_ASSET_REVISION=1.33.0
正式Tag / GitHub Release    v1.33.0（Release workflow全Gate通過後のみ）
```

詳細な順序は [v1-33-i-final-release.md](v1-33-i-final-release.md) を参照してください。日程コピーと日程Drag & DropはV1.33対象外です。

## Version 1.29.0 update

## Version 1.28.0からVersion 1.29.0

V1.29.0はRemote File Managerを追加するため、既存DBへMigration 021とprivate設定の追加が必要です。Codeより先にBackupとCredential keyの保管方法を確定してください。

1. Application code、`config/local.php`、Database、File Library storage、必要な`var/`DataをBackupする。
2. `database/migrations/021_v1_29_remote_connection.sql`の`@table_prefix`を実環境の`DB_TABLE_PREFIX`へ合わせて1回適用する。
3. `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`で専用Keyを生成し、`APP_REMOTE_CREDENTIAL_KEY_B64`へprivate設定する。
4. `APP_REMOTE_TEMP_DIR`を`public/`外のwritable directoryへ設定する。
5. 使用するProtocolに必要なPortだけ`APP_REMOTE_ALLOWED_PORTS`へ設定する。SFTP利用時は検証済みknown_hostsも設定する。
6. Private/LAN接続が必要な場合だけ、`APP_REMOTE_PRIVATE_NETWORK_ENABLED`と最小CIDR allowlistを設定する。
7. `php tools/remote_file_env_check.php`を実行し、必要なcURL protocol／Extension／Key／Temporary directoryがOKであることを確認する。
8. Runtime ZIPのSHA-256を確認して別Folderへ展開し、`config/local.php`、実DB、File Library upload、known_hosts/private key等を上書きせずCodeを更新する。
9. BrowserをReloadし、Footerが`RSS Reader Modernization 1.29.0`であることを確認する。
10. `/remote-files`でTest Connectionを登録し、Connection Test、Directory操作、Upload／Preview／Download、Rename／Move、File Library相互転送、Deleteを確認する。
11. 問題があればCodeだけでなく、021適用前のDatabase BackupとCredential設定を含む同じ時点へ戻す。

```text
DB Migration                021_v1_29_remote_connection.sql
New table                   remote_connection
必須設定                    APP_REMOTE_CREDENTIAL_KEY_B64 / APP_REMOTE_TEMP_DIR
Protocol別設定              allowed ports / SFTP known_hosts / optional private CIDRs
Browser Cache               APP_ASSET_REVISION=1.29.0
正式Tag / GitHub Release    v1.29.0
```

Plain FTPは通信内容を暗号化しません。利用可能ならSFTP／FTPS／HTTPS WebDAVを優先してください。

## Version 1.22.0 update

## Version 1.21.0からVersion 1.22.0

V1.22.0はRSS Management / OPML、Feed Health、RSS Rulesを追加するため、既存DBではMigration 014〜016が必要です。Codeより先にBackupと未適用Migrationを確認します。

1. Application code、`config/local.php`、実DB、必要な`var/`DataをBackupする。
2. `014_v1_22_opml_feed_metadata.sql`、`015_v1_22_feed_health.sql`、`016_v1_22_rss_rules.sql`の`@table_prefix`を実環境へ合わせる。
3. 未適用のMigrationだけを014→015→016の番号順で実行する。V1.22 checkpointですでに適用済みのものは再実行しない。
4. Runtime ZIPのSHA-256を確認し、別Folderへ展開する。
5. `config/local.php`、実DB、生成済み`var/`Dataを上書きせずCodeをApplication Rootへ相対Pathで配置する。
6. BrowserをReloadし、Footerが`RSS Reader Modernization 1.22.0`であることを確認する。
7. Login、通常RSS更新、RSS Management / OPML、Feed Health、RSS Rules、Stock、Task、Settings、Logoutを確認する。
8. 問題があればCodeとDBを同じBackup時点へ戻す。DB Migrationを伴うためCodeだけをV1.21へ戻すRollbackは行わない。

```text
DB Migration                014 / 015 / 016
New tables                  feed_metadata / feed_health / rss_rule / rss_rule_condition
必須設定                    追加なし
Browser Cache               APP_ASSET_REVISION=1.22.0
正式Tag / GitHub Release    v1.22.0
```

## Version 1.20.0から1.20.1

V1.20.1はCalendar予定色のため、既存`calendar_event`TableへColumnを1つ追加します。Codeだけ先に更新すると色APIが503を返すため、Backup後にMigrationを先に適用します。

1. Code / `config/local.php` / Database / 必要な`var/`DataをBackupする。
2. `database/migrations/013_v1_20_1_calendar_event_color.sql`の`@table_prefix`を実環境と合わせる。
3. Migrationを実行し、`calendar_event_color`が存在することを確認する。
4. V1.20.1 Production ZIPを相対Pathで上書きする。
5. Calendar色、Memo Refresh、Block Collapse、Dashboard操作を確認する。

C段階ですでに013を実行済みの場合は再実行不要です。

## Version 1.18.0からVersion 1.19.0

V1.19.0はArchitecture / Security / Documentation中心のMaintenance Releaseです。DB Migration、SQL、新規必須Config / Secretはありません。

1. 現在のApplication codeと`config/local.php`をBackupする。
2. 正式Runtime ZIPのSHA-256を確認し、別Folderへ展開する。
3. Runtime ZIPのCodeをApplication Rootへ相対Pathで上書きする。
4. `config/local.php`、実DB、生成済み`var/`Dataは維持する。
5. BrowserをReloadし、Footerが`RSS Reader Modernization 1.19.0`であることを確認する。
6. Login / Dashboard / Stock / Settings / Logoutと主要Widgetを確認する。
7. Camera / Videoを使用する場合はConsoleにhls.js SRI Errorがなく、Asset URLが`?v=1.19.0`になっていることを確認する。
8. 問題があればV1.18.0 BackupへCodeを戻す。DB MigrationがないためDB rollbackは不要。

## Version 1.19.0からVersion 1.20.0

V1.20.0はCard Header Compact、RSS Typing、Wire Defense、全RSS新着を統合した正式Releaseです。DB構造変更、Migration、SQL、新規必須Config／Secretはありません。

1. Application code、`config/local.php`、実DB、必要な`var/`DataをBackupする。
2. Production ZIPのSHA-256を確認し、別Folderへ展開する。
3. `config/local.php`、実DB、生成済み`var/`Dataを上書きせずCodeをApplication Rootへ相対Pathで配置する。
4. SQL、Migration、`schema.sql`は実行しない。
5. BrowserをReloadし、Footerが`RSS Reader Modernization 1.20.0`であることを確認する。
6. Login／Dashboard／Stock／Settings／Logoutと通常RSS／Search Feedを確認する。
7. RSS Typing、Wire Defense、全RSS新着を各1回確認する。
8. SmartphoneまたはDevice modeで40px Header、Drawer、Game、全RSS新着を確認する。
9. 問題があればV1.19.0 BackupへCodeを戻す。DB MigrationがないためDB rollbackは不要。

```text
DB schema / Migration       変更なし
Public API                  widget.allrss.create / update / delete / fetch を追加
必須設定                    追加なし
Browser Cache               APP_ASSET_REVISION=1.20.0
正式Tag / GitHub Release    v1.20.0
```


- 変更file
- 新規file
- 削除file
- DB migrationの有無
- 必須設定追加の有無
- Runtime cache削除の要否
- Release NotesとSHA-256



## Version 1.17.2からVersion 1.18.0

Version 1.18.0はConnection Monitor追加とFrontend表示の更新で、DB構造変更、Migration、SQL、必須設定追加はありません。

1. Code、`config/local.php`、実DB、`var/`をBackupする。
2. Runtime ZIPを別Folderへ展開し、SHA-256を確認する。
3. `config/local.php`、実DB、`var/`の生成Dataを上書きせずCodeを更新する。
4. SQL、Migration、`schema.sql`は実行しない。
5. BrowserをReloadする。`APP_ASSET_REVISION=1.18.0-r2`により旧1.18.0候補とはAsset URLが変わるため、通常Reloadで新Assetを取得出来る。Hard ReloadはTroubleshooting時のみでよい。
6. Add Widget → Information → Connection Monitorを追加し、Online／Latency／History／Qualityを確認する。
7. DevTools Offline等でOffline→Recovery、Downtimeを確認する。
8. 複数Connection Monitorでも`connection_probe.php`がPage全体で約5秒に1回であることを確認する。

```text
DB schema / Migration       変更なし
Public API                  widget.healthprobe.create / update / delete
必須設定                    追加なし
外部API Key                 追加なし
Browser Cache               Hard Reload推奨
削除file                    なし
```

## Version 1.1.0からVersion 1.2.0

Version 1.2.0はCodeとFrontendの更新で、DB構造変更、Migration、SQL、必須設定追加はありません。

1. Code、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Folderへ展開し、SHA-256と変更Fileを確認する。
3. `config/local.php`、実DB、`var/`の生成Dataを上書きせずCodeを更新する。
4. SQL、Migration、`schema.sql`は実行しない。
5. Browser Cacheを更新する。
6. Login、通常RSS、Search Feed、概要、個別更新、新着Bell、記事Actionsを確認する。

```text
DB schema / Migration       変更なし
Public API                  Search Feed／記事Actionsで既存DispatcherへAction追加済み
必須設定                    追加なし
Feed Cache削除              不要
Browser Cache               Hard Reload推奨
削除file                    Release Notesの変更一覧を参照
```

## Version 1.0系からVersion 1.1.0

Version 1.1.0ではFeed item state、Dashboard Widget、Memo、Task、Calendar eventのTableを追加します。CodeとDBを同じMaintenance内で更新してください。

1. Code、`config/local.php`、実DB、`var/`をBackupする。
2. Migration 002～006の`@table_prefix`を実DBへ合わせる。
3. preflightを確認し、Migrationを番号順に実行する。
4. postflightまたは各`tools/db_v11*.php verify`を実行する。
5. Codeを入れ替え、Browser Cacheを更新する。
6. Login、Feed、NEW、Widget、Task / Calendar、Account Settingsを確認する。

## V1.1-J / R2適用済み環境からVersion 1.1.0

追加Migrationはありません。Code、Documentation、Version、Test、配布物だけを更新します。`config/local.php`、実DB、Session、Cache、Log、Throttle Dataを上書きしないでください。

## V1.1-I / R3からV1.1-J / R1

V1.1-JはAccount Settingsを追加します。メールアドレスとパスワードは既存`user_info`のColumnを更新するため、DB構造変更はありません。

```text
DB schema / Migration       変更なし
Public API                  account.email.update / account.password.update
必須設定                    追加なし
Browser Cache               Ctrl + F5を推奨
削除file                    なし
```

Overlayを上書きした後、SQLやMigrationは実行しません。現在のパスワードを確認して変更し、成功後はSession IDとCSRF Tokenが自動的に更新されます。現在のメールアドレスはKeyed Identityで保存されているため画面へ表示しません。

確認時は、メールアドレス変更後にLogoutして新しいメールアドレスでLoginし、パスワード変更後に旧パスワードが拒否され新パスワードでLoginできることを確認してください。

## V1.1-I / R1からV1.1-I / R2

V1.1-I / R2はFrontendの操作性改善です。スマートフォン幅での左右スワイプによるタブ切り替えと、Feed／Calendar読込中のSpinnerを追加します。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Cache clear                 Browser Cache更新のみ
削除file                    なし
```

V1.1-I / R1適用済みProjectへOverlayを上書きし、Browserで`Ctrl + F5`を実行します。SQL、`db_v11i.php apply`、`schema.sql`の再実行は不要です。

スワイプはスマートフォン幅だけで有効です。Calendar、入力欄、Button、Link、Modal、Drawer、Widget並び替えHandle、画面端から始まる操作では動作しません。

## V1.1-H / R1からV1.1-I / R1

V1.1-IはCalendar Widgetと`calendar_event`Tableを追加します。Task期限は既存の`task`Tableを直接参照します。Codeだけ先に切り替えるとCalendar操作時に`calendar_event`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    calendar_eventを追加
既存Column                  変更なし
Public API                  widget.calendar.create / update / delete
                            calendar.month.list
                            calendar.event.create / update / delete
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11i.php apply --backup-confirmed
php tools/db_v11i.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、次の順で実行します。各SQL冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してください。

```text
database/audit/v1_1_i_preflight.sql
database/migrations/006_v1_1_calendar_event.sql
database/audit/v1_1_i_postflight.sql
```

DB変更に必須なのは`006_v1_1_calendar_event.sql`です。preflightとpostflightは読取専用の確認SQLです。Rollback時はCodeとDBを同じBackup時点へ戻します。

## V1.1-G / R1からV1.1-H / R1

V1.1-HはTask Widgetと`task`Tableを追加します。Codeだけ先に切り替えるとDashboard queryが`task`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    taskを追加
Column / Index              task Table内に追加
Public API                  widget.task.create / update / delete
                            task.item.create / update / toggle / delete
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11h.php apply --backup-confirmed
php tools/db_v11h.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、`database/migrations/005_v1_1_task.sql`冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してから実行します。その後、`database/audit/v1_1_h_postflight.sql`またはCLI verifyで確認します。

Rollback時はCodeとDBを同じBackup時点へ戻します。V1.1-Hで作成したTaskを保持したままCodeだけV1.1-Gへ戻す運用は行いません。

## V1.1-F / R1からV1.1-G / R1

V1.1-GはMemo Widgetと`memo`Tableを追加します。Codeだけ先に切り替えるとDashboard queryが`memo`Tableを参照するため、Backup後にMigrationを同じMaintenance内で適用してください。

```text
DB Table                    memoを追加
Column / Index              memo Table内に追加
Public API                  widget.memo.create / update / deleteを追加
必須設定                    追加なし
Cache clear                 不要
削除file                    なし
```

CLIを利用できる場合:

```powershell
php tools/db_v11g.php apply --backup-confirmed
php tools/db_v11g.php verify
```

phpMyAdminを利用する場合は、RSS Readerの実Databaseを選択し、`database/migrations/004_v1_1_memo.sql`冒頭の`@table_prefix`を`DB_TABLE_PREFIX`と同じ値へ変更してから実行します。その後、`database/audit/v1_1_g_postflight.sql`またはCLI verifyで確認します。

Rollback時はCodeとDBを同じBackup時点へ戻します。Migrationは既存Tableを変更しませんが、V1.1-Gで作成したMemoを保持したままCodeだけV1.1-Fへ戻す運用は行いません。

## M4-F / R1からM4-G / R1

M4-GはVersion、Release Notes、Final Package、Tag / GitHub Release手順の確定です。Application RuntimeはRC1から変更していません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存`config/local.php`と実DBはそのまま使用できます。`schema.sql`やMigrationは実行しません。

## M4-D / R1からM4-E / R1

M4-EはRelease package builder、Verifier、Release Notes、Tag / GitHub Release手順、Version marker、Testの追加です。Application Runtimeは変更していません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存 `config/local.php` と実DBはそのまま使用できます。M4-E適用時に `schema.sql` やMigrationを実行しません。

M4-EのPreview Release ZIPはPackaging確認用です。本番更新やGitHub Release公開には使用せず、M4-F / M4-Gで作り直します。

## M4-C / R1からM4-D / R1

M4-DはGitHub公開資料、Security / Contribution文書、Issue template、GitHub Actions CI、Version marker、Testの追加です。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定                    追加なし
Frontend Runtime Asset      変更なし
Cache clear                 不要
削除file                    なし
```

既存 `config/local.php` と実DBはそのまま使用できます。M4-D適用時に `schema.sql` やMigrationを実行しません。

GitHubへpushした後、ActionsのPHP 8.1 / 8.4 JobとRepository Settingsを確認します。

## M4-B / R1からM4-C / R1

この更新では次の変更はありません。

```text
DB schema / Migration       変更なし
Public API                  変更なし
必須設定項目                追加なし
Runtime Cache format        変更なし
削除file                    なし
```

`config/local.php.example` と `config/.env.example` は、既にRuntimeが対応していた設定を一覧として補完しています。実環境の `config/local.php` へ新しい項目を追加しなくても従来のDefaultで動作します。

M4-Cで `schema.sql`、`001_sb13_integrity.sql` を実行しないでください。Cache clearも不要です。

## 追加の既存DB Migration記録

以下は旧Installation文書に重複していた既存Database向けMigration記録を、このHistorical文書へ集約したものです。

### Version 1.23.0からVersion 1.24.0

V1.23.0からV1.24.0へ更新する既存Databaseでは、Backup取得後に `017_v1_24_stock_state.sql` の `SET @table_prefix` を環境へ合わせて適用します。Migration 017は既存Stockを保持したまま `stock_processed` / `stock_important` / `stock_archived` をDefault 0で追加し、Archive検索用Indexを追加します。`stock_flag` は従来どおりStock解除用で、Archiveとは別状態です。

### Version 1.24.0からVersion 1.25.0

V1.24.0からV1.25.0へ更新する既存Databaseでは、Backup取得後に次を**この順番で1回ずつ**適用します。

```text
018_v1_25_calendar_event_time_url.sql
→ 019_v1_25_calendar_recurrence.sql
```

Migration 018は既存`calendar_event`へ終日Flag、開始／終了時刻、関連URLを追加します。既存予定はDefaultで終日となり、時刻とURLはNULLのままです。Migration 019は繰り返し種別と任意の繰り返し終了日を追加し、既存予定は`none`のまま維持します。両Migrationとも `SET @table_prefix` を実環境の `DB_TABLE_PREFIX` と同じ値へ合わせてから実行してください。V1.25-F R3までの本番確認ですでに018 / 019を適用済みの場合は、正式V1.25.0化で再実行しません。

### Version 1.26以前からVersion 1.27以降

V1.26以前からV1.27以降へ更新する既存Databaseでは、Backup取得後に `020_v1_27_user_files.sql` を1回適用してFile Library metadata tableを追加します。V1.28では追加Migrationはありません。

### Version 1.31.0からVersion 1.32.0

V1.31.0からV1.32.0へ更新する既存Databaseでは、Backup取得後に次を**この順番で、未適用のものだけ1回ずつ**適用します。各Migrationの `SET @table_prefix` は実環境の `DB_TABLE_PREFIX` と同じ値へ変更してください。

```text
022_v1_32_auth_2fa.sql
→ 023_v1_32_auth_session.sql
→ 024_v1_32_auth_audit_log.sql
```

Migration 022は`auth_totp`と`auth_recovery_code`、023は`auth_session`、024は`auth_audit_log`を追加します。いずれも既存tableを削除しない加算型です。対象tableが既に存在する本番環境では、RC/正式版への更新だけを理由に再実行しません。2FAを既に使用している環境では`APP_TOTP_SECRET_KEY_B64`を変更しないでください。

### Version 1.33.1からVersion 1.34.0

V1.33.1からV1.34.0へ更新する既存Databaseでは、Backup取得後に次を**この順番で、未適用のものだけ1回ずつ**適用します。

```text
026_v1_34_mail_smtp.sql
→ 027_v1_34_mail_sent_save_mode.sql
```

Migration 026は既存Mail AccountへSMTP送信設定を追加し、027はSent保存方式を追加します。どちらも既存IMAP設定やCredentialを削除しない加算型です。V1.34の本番確認で026 / 027を適用済みの場合は、正式Releaseへの更新だけを理由に再実行しません。

### Version 1.34.2からVersion 1.35.0

V1.34.2からV1.35.0へ更新する既存Databaseでは、Backup取得後に`028_v1_35_mail_google_oauth.sql`、続けて`029_v1_35_remember_2fa_trust.sql`を各1回適用します。028は既存Mail Accountを`password`方式のまま保って認証方式Columnを追加し、029は既存Remember Tokenを未信頼のまま保って2FA確認時刻Columnを追加します。既存のPassword、暗号化Credential、Remember Tokenの有効期限は変更しません。
