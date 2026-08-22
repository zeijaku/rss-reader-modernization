# V1.19.0 本番確認Checklist

1. Application code、`config/local.php`、実DBをBackupする。
2. Runtime ZIPのSHA-256を確認してApplication Rootへ上書きする。
3. SQL / Migrationは実行しない。
4. Footerが`RSS Reader Modernization 1.19.0`であることを確認する。
5. Login / Dashboard / Stock / Settings / Logoutを確認する。
6. RSS更新、Stock、MemoまたはTask、Widget並べ替えを最低1回確認する。
7. Calendarと普段使うInformation Widgetを確認する。
8. Camera / Video利用時はSRI Errorがなく、`camera-video-streaming.js?v=1.19.0`であることを確認する。
9. Account SettingsのPassword Form警告が再発していないことを確認する。
10. Consoleの新しいRSS Reader本体由来赤ErrorとPHP / Apache Error Logを確認する。
11. SmartphoneまたはDevice Modeで主要表示を軽く確認する。
