# Version 1.5.0 User Checklist

1. 現行Code、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupする。
2. ZIPを別Folderへ展開し、SHA-256、Manifest、変更Fileを確認する。
3. Version 1.4.0適用済み環境ではSQLやMigrationを実行しない。
4. `config/local.php`、実DB、`var/`の生成Data、環境固有設定を不用意に上書きしない。
5. Codeを配置し、Browserを再読み込みする。
6. 通常RSS、Search Feed、記事Actions、Stock、Memo、Task、Calendar、Account Settingsを確認する。
7. Clock Widgetの追加・変更・削除、時計表示、12／24時間、日付・秒表示を確認する。
8. TimerのPreset、任意時間、開始、一時停止、再開、Reset、終了表示を確認する。
9. Reload／Background復帰後の時間補正、複数Tab同期、複数Clockの状態分離を確認する。
10. SmartphoneでFeedの横Overflowがなく、三点リーダーと概要［＋］が表示されることを確認する。
11. Icon Quest、Widget並べ替え、360／420／1024px、全8 Theme、Focus、ARIA、Reduced Motionを確認する。
12. `git status`と差分を確認し、main統合後のRelease Commitへ`v1.5.0` Tagを付ける。
