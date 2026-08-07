# Version 1.5.0 Apply Note

- Version 1.5.0の完全統合成果物です。
- Version 1.4.0適用済み環境からの更新では、DB Migration、SQL、必須設定変更はありません。
- Clock Widget登録は既存`dashboard_widget` Tableを利用し、Timer状態はBrowser Storageへ保存されます。
- `config/local.php`、Server固有`.htaccess`、実DB、Log、Session、Cache、Throttle Dataは不用意に上書きしません。
- 更新後にBrowserを再読み込みし、既存Clock、Timer、状態復元、終了表示、Smartphone Feedを確認します。

推奨Commit: `release: finalize version 1.5.0`
推奨Tag: `v1.5.0`
