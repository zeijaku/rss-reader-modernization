# Version 1.3.0 User Checklist

1. 現行Code、`config/local.php`、Server固有`.htaccess`、実DB、`var/`をBackupする。
2. ZIPを別Folderへ展開し、SHA-256、Manifest、変更Fileを確認する。
3. Version 1.2.0適用済み環境ではSQLやMigrationを実行しない。
4. `config/local.php`、実DB、`var/`の生成Data、環境固有設定を不用意に上書きしない。
5. Codeを配置し、BrowserをHard Reloadする。
6. PC／SmartphoneでHeader、Drawer、現在地、外部Link、Account Settings、Logoutを確認する。
7. Esc、外側Click、Tab／Shift+Tab、Focus復帰、現在地の`aria-current`を確認する。
8. 通常RSS、Search Feed、記事Title、新着Bell、概要、三点リーダー、記事Actionsを確認する。
9. Clock、Memo、Task、Calendarを含むWidget見出しの高さと文字位置を確認する。
10. `git status`と差分を確認し、main統合後のCommitへ`v1.3.0` Tagを付ける。
