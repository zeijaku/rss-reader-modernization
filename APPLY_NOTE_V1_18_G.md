# APPLY NOTE — Version 1.18.0

Version 1.18.0はConnection Monitorの正式Releaseです。

- Base: Version 1.17.2
- DB Migration: なし
- SQL実行: 不要
- 必須config追加: なし
- 新規外部Dependency: なし
- Runtime ZIPは更新済み実ファイルを含み、Patch script実行を前提にしない
- `config/local.php`、実DB、`var/`の生成Dataを上書きしない
- 本番反映後はFooter VersionとConnection MonitorをBrowserで確認する
