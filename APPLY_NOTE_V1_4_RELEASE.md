# Version 1.4.0 Apply Note

- Version 1.4.0の完全統合成果物です。
- Version 1.3.0適用済み環境からの更新では、DB Migration、SQL、必須設定変更はありません。
- Game Widgetの登録行は既存`dashboard_widget` Tableへ保存され、盤面・Best・勝敗はBrowser Storageへ保存されます。
- `config/local.php`、Server固有`.htaccess`、実DB、Log、Session、Cache、Throttle Dataは不用意に上書きしません。
- 更新後にBrowserをHard Reloadし、既存機能とIcon Questの追加、操作、保存、復元、並べ替えを確認します。

推奨Commit: `release: finalize version 1.4.0`
推奨Tag: `v1.4.0`
