# V1.20-F Production RC Checklist

1. 現在のApplication code、`config/local.php`、Database、必要な`var/`DataをBackupする。
2. `rss-reader-modernization-1.20.0-rc1.zip`とsidecarのSHA-256が一致することを確認する。
3. ZIPを別Folderへ展開し、Application Rootへ相対PathでCodeを上書きする。`config/local.php`、実DB、生成済み`var/`Dataは維持する。
4. SQL／Migration／`schema.sql`は実行しない。
5. Footerが`RSS Reader Modernization 1.20.0-RC1`になっていることを確認する。
6. Login / Remember Me / Dashboard / Stock / Settings / Logoutを確認する。
7. 通常RSSを表示し、HeaderがCompact化されてもTitle、Edit、Refresh、Drag、記事Actionが操作出来ることを確認する。
8. 通常RSSでRSS Typingを開始し、日本語IME入力、60秒Timer、Escape／RSSへ戻る、Best保存を確認する。Search FeedにTyping buttonが出ないことも確認する。
9. Drawer → Game → Wire Defenseから追加し、Start、missile reload、Pause／Resume／Stop、3 Lives、Game Over、Best／Max Chain、Reload後の復元を確認する。
10. Wire Defense実行中にTabをBackgroundへ移し、復帰後に不要なGame loopが走り続けていないことを確認する。
11. Drawer → RSS → 全RSS新着を追加し、5／10／20／30件、Refresh、Edit、Deleteを確認する。
12. 複数RSSで新しい記事がpublication date順に混在し、source名が表示され、1Feed失敗時にも他Feedが表示されることを確認する。
13. 通常Search Feedが従来どおり検索結果を表示し、全RSS新着APIへ誤ってrewriteされないことを確認する。
14. Memo／Task／Calendar／Information Widgetを各1種類確認し、既存Widgetに目立った回帰がないことを確認する。
15. Camera / Video / X / Mailを利用している場合、対象機能を1回更新し、Consoleに新しいAsset revision／SRI Errorがないことを確認する。
16. PCとSmartphone実機またはDevice modeでDrawer、Modal、40px Header、RSS Typing、Wire Defense、全RSS新着の幅を確認する。
17. DevTools Consoleに新しい赤Errorがないことを確認する。
18. PHP / Apache Error LogにUnexpected 500、`Failed opening required`、`Cannot redeclare`、`undefined function`がないことを確認する。

DB Migration / SQL / 新規必須config作業はありません。RC確認で問題がなければV1.20-Gで正式`1.20.0`へ昇格します。
