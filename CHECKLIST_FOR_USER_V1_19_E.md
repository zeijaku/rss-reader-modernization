# V1.19-E Production RC Checklist

1. Application codeと`config/local.php`をBackupする。
2. `rss-reader-modernization-1.19.0-rc1.zip`をApplication Rootへ相対Pathで上書きする。
3. Footerが`RSS Reader Modernization 1.19.0-RC1`であることを確認する。
4. Login / Remember Me / Dashboard / Stock / Settings / Logoutを確認する。
5. Feedを1件更新し、Stock保存・解除を確認する。
6. MemoまたはTaskを追加・変更・削除する。
7. Widgetを1回Drag & Dropし、Reload後も位置が維持されることを確認する。
8. Calendarを開き、月表示と予定 / Task期限表示を確認する。
9. Information Widgetを1種類更新する。Connection Monitorが最小確認に向く。
10. Camera / Videoを利用している場合、Consoleにhls.js SRI errorがなく、HLS利用時は再生も確認する。
11. Account Settingsを開き、Password formのusername DOM warningが消えていることを確認する。
12. DevTools Consoleに新しい赤Errorがないことを確認する。
13. PHP / Apache Error LogにUnexpected 500、`Failed opening required`、`Cannot redeclare`、`undefined function`がないことを確認する。
14. Smartphone実機またはDevice modeでTab swipe、Calendar、Drawer、主要Widgetの幅を確認する。

DB Migration / SQL / config追加作業はありません。
