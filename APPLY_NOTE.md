# Version 1.1.0 Apply Note

- Version 1.1.0の完全統合成果物です。
- V1.1-J / R2まで適用済みの場合、追加DB Migrationはありません。
- Version 1.0系DBから更新する場合は、Backup後にMigration 002～006を順番に適用します。
- `config/local.php`、実DB、Log、Session、Cache、Login Throttle Dataは上書きしません。
- 更新後にBrowser Cacheを更新し、Healthcheck、DB verify、Login、Feed、Widget、Account Settingsを確認します。

推奨Commit: `V1.1-K: release version 1.1.0`
推奨Tag: `v1.1.0`
