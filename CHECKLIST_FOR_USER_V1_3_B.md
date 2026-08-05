# V1.3-B / R1 User Checklist

1. 現行Source、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Directoryへ展開し、現行Sourceとの差分を確認する。
3. SQLは実行しない。Migrationもない。
4. `config/local.php`、実DB、Server用`.htaccess`、`var/` Runtime Dataは上書きしない。
5. Application Fileを配置後、BrowserをHard Reloadする。
6. Footerが`RSS Reader Modernization 1.3.0-dev.1`になっていることを確認する。
7. Drawerが「表示／Widget追加／カスタマイズ／リンク／Account」の順になっていることを確認する。
8. 現在のタブまたはStockに左Borderと薄い背景が付くことを確認する。
9. タブ1～4、Stock、各Widget追加、タブ表示変更、表示設定、Account Settings、Logoutが従来どおり動くことを確認する。
10. PCでは設定済み外部LinkがHeaderに表示され、Drawerには重複表示されないことを確認する。
11. Smartphoneでは設定済み外部LinkがDrawerに表示されることを確認する。
12. Iconと文字の開始位置がLink、Button、Logoutで揃っていることを確認する。
13. Mouse Hover、Keyboard Focus、選択中表示を確認する。
14. Esc、外側ClickでDrawerが閉じることを確認する。
15. Tab / Shift+TabでFocusがDrawer内を循環し、閉じた後にMenu Buttonへ戻ることを確認する。
16. Smartphoneで各項目が押しにくくなっていないことを確認する。
17. Drawer内容が画面高を超える場合に縦Scrollできることを確認する。
18. Login、Feed表示、Stock、Search Feed、Task、Calendar、Clock、Memoを簡易回帰確認する。
