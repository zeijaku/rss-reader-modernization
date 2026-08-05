# Version 1.4.0 User Checklist

1. 現行Code、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupする。
2. ZIPを別Folderへ展開し、SHA-256、Manifest、変更Fileを確認する。
3. Version 1.3.0適用済み環境ではSQLやMigrationを実行しない。
4. `config/local.php`、実DB、`var/`の生成Data、環境固有設定を不用意に上書きしない。
5. Codeを配置し、BrowserをHard Reloadする。
6. 通常RSS、Search Feed、記事Actions、Stock、Memo、Task、Clock、Calendar、Account Settingsを確認する。
7. DrawerからGame Widgetを追加し、Tab配置、並べ替え、Title、横幅、Header色、削除を確認する。
8. Icon QuestをKeyboard／Tapで操作し、Treasure、Goal、Enemy、Clear／Game Over、New Game、Resetを確認する。
9. Reload後の途中状態、Best手数、勝敗数、記録削除、複数Widgetの状態分離を確認する。
10. 360／420／1024px、全8 Theme、Focus、ARIA、Reduced Motion、Header左余白を確認する。
11. `git status`と差分を確認し、main統合後のRelease Commitへ`v1.4.0` Tagを付ける。
