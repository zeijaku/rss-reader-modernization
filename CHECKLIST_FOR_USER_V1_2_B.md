# V1.2-B User Checklist

1. 現行Source、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Directoryへ展開し、現行Sourceとの差分を確認する。
3. SQLは実行しない。`config/local.php`へ項目追加もしない。
4. `.htaccess`は第1段から変更していない。現在使用中のServer用Fileをそのまま維持する。
5. Application Fileを配置し、`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle Dataは上書きしない。
6. Browser Cacheを更新する。Feed Cacheの削除は行わない。
7. PCで、長い記事TitleだけHoverすると少し遅れて全文が出ることを確認する。
8. Keyboard Tabで記事TitleへFocusし、同じ全文表示を確認する。
9. 短いTitleには不要なTooltipが出ないことを確認する。
10. `▽`で対象記事だけ概要が開閉することを確認する。
11. `content`／`description`内のHTML、画像、iframe、動画が実行・表示されず、Textになることを確認する。
12. 長い概要がCardを無制限に伸ばさず、概要内でScrollできることを確認する。
13. 概要内の「元記事を開く」と通常Title Linkが従来どおり別Tabで開くことを確認する。
14. Feed見出しが`＝ Title　✎ ⟳`の順になり、編集Buttonの位置が大きく変わっていないことを確認する。
15. `⟳`を押しても現在の記事が消えず、対象Feedだけ更新されることを確認する。
16. 更新中は`⟳`が回転し、連打できないことを確認する。
17. Networkを一時的に失敗させ、以前の記事が残ることと再試行できることを確認する。
18. 個別更新後にFeed Title、記事、NEW件数が対象Feedだけ更新されることを確認する。
19. `⟳`や`▽`を操作してもWidget Dragが始まらないことを確認する。
20. Smartphoneで`▽`、Stock、`⟳`が押しやすく、横幅やTitle表示が崩れないことを確認する。
21. Stock保存、NEW解除、Feed編集、Drag & Drop、Clock、Memo、Task、Calendar、Account Settingsを回帰確認する。
22. Login／Logout／Session切れ／Common Errorが第1段のまま動くことを確認する。
23. `php tools/healthcheck.php`を実行する。
24. `git status`、`git diff`、`git diff --cached`を確認してからCommit／Pushする。
