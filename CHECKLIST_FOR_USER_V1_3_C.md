# V1.3-C / R1 User Checklist

1. 現行Source、`config/local.php`、実DB、`var/`をBackupする。
2. ZIPを別Directoryへ展開し、V1.3-Bとの差分を確認する。
3. SQLは実行しない。Migrationもない。
4. `config/local.php`、実DB、Server用`.htaccess`、`var/` Runtime Dataは上書きしない。
5. Application Fileを配置後、BrowserをHard Reloadする。
6. Footerが`RSS Reader Modernization 1.3.0-dev.2`になっていることを確認する。
7. Headerが「iGuguru／現在のTab名／外部Link／Menu」の順で表示されることを確認する。
8. iGuguruだけがHome Linkであり、現在のTab名が別表示になっていることを確認する。
9. 長いTab名が改行せず、省略表示されることを確認する。
10. PCで設定済み外部Linkが右側に表示されることを確認する。
11. PCで外部Link名が長くてもHeaderが横へはみ出さないことを確認する。
12. Smartphoneで外部LinkがHeaderに出ず、Drawer側に表示されることを確認する。
13. PC／SmartphoneのMenu Buttonが同じBars Iconであることを確認する。
14. Menu Buttonが小さすぎず、Focus表示が見えることを確認する。
15. Navbar Dark／Primary／Lightを切り替え、文字とButtonが背景に埋もれないことを確認する。
16. 使用中ThemeでHeader高と垂直位置が不自然でないことを確認する。
17. Esc、外側Click、Tab / Shift+Tab、Focus復帰が従来どおり動くことを確認する。
18. Drawer、Login、Feed、Stock、Search Feed、Task、Calendar、Clock、Memoを簡易回帰確認する。
