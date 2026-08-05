# Version 1.3.0 Apply Note

- Version 1.3.0の完全統合成果物です。
- Version 1.2.0適用済み環境からの更新では、DB Migration、SQL、必須設定変更はありません。
- Version 1.0系DBから直接更新する場合は、Backup後にVersion 1.1のMigration 002～006を順番に適用します。
- `config/local.php`、Server固有`.htaccess`、実DB、Log、Session、Cache、Login Throttle Dataは不用意に上書きしません。
- 更新後にBrowserをHard Reloadし、Header、Drawer、現在地、外部Link、Account、通常RSS、Search Feed、記事Actions、Widget見出しを確認します。

推奨Commit: `release: finalize version 1.3.0`
推奨Tag: `v1.3.0`
