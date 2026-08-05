# Version 1.2.0 Apply Note

- Version 1.2.0の完全統合成果物です。
- Version 1.1.0適用済み環境からの更新では、追加DB Migration、SQL、必須設定変更はありません。
- Version 1.0系DBから直接更新する場合は、Backup後にVersion 1.1のMigration 002～006を順番に適用します。
- `config/local.php`、実DB、Log、Session、Cache、Login Throttle Dataは上書きしません。
- 更新後にBrowser Cacheを更新し、Authentication、通常RSS、Search Feed、概要、個別更新、新着Bell、記事Actions、Stock、Taskを確認します。

推奨Commit: `release: finalize version 1.2.0`
推奨Tag: `v1.2.0`
