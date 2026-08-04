# V1.2-A User Checklist

1. 現行Source、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Directoryへ展開し、現在のServer用`.htaccess`と同梱版をDiffする。
3. ApplicationがRoot設置なら`ErrorDocument ... /public/error.php`を使用する。
4. DocumentRootが`public/`なら`/error.php`、Subdirectory設置ならPrefix付きPathへ手動変更する。
5. SQLは実行しない。`config/local.php`へ項目追加もしない。
6. Application Fileを配置する。`config/local.php`、実DB、Log、Session、Cacheは上書きしない。
7. Browser Cacheを更新し、Login／Registrationの表示をPCとSmartphoneで確認する。
8. Password表示切替、Enter送信、二重送信防止、Login成功／失敗を確認する。
9. Logout後の「ログアウトしました。」が一度だけ出ることを確認する。
10. Session timeout設定を短くした検証環境で、期限切れMessageが別文言になることを確認する。
11. 存在しないURL、403対象、Test用500／503でStatusと共通画面を確認する。
12. Feed、Stock、Clock、Memo、Task、Calendar、Account Settingsを回帰確認する。
13. `php tools/healthcheck.php`を実行する。
14. `git status`、`git diff`、`git diff --cached`を確認してからCommit／Pushする。
