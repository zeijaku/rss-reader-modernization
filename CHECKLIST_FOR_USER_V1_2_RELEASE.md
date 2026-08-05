# Version 1.2.0 User Checklist

1. 現行Code、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Folderへ展開し、SHA-256と変更Fileを確認する。
3. Version 1.1.0適用済み環境ではSQLやMigrationを実行しない。
4. Server側の`config/local.php`、`.htaccess`、実DB、`var/`の生成Dataを不用意に上書きしない。
5. Codeを配置し、BrowserをHard Reloadする。
6. Login／Logout／Session expiry、通常RSS、Search Feed、概要開閉、個別更新、新着Bellを確認する。
7. 記事ActionsのStock、URL Copy、X投稿画面、Task追加を確認する。
8. PC／Smartphoneで三点リーダー、概要「＋」、Title表示、Drawer、4タブを確認する。
9. `git status`と差分を確認してからCommit、Push、Tag、GitHub Releaseを行う。
