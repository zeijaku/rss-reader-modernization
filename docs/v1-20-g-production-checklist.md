# V1.20-G Production Checklist

1. 現在のApplication code、`config/local.php`、Database、必要な`var/`DataをBackupする。
2. `rss-reader-modernization-1.20.0.zip`とsidecarのSHA-256が一致することを確認する。
3. ZIPを別Folderへ展開し、Application Rootへ相対PathでCodeを上書きする。`config/local.php`、実DB、生成済み`var/`Dataは維持する。
4. SQL／Migration／`schema.sql`は実行しない。
5. Footerが`RSS Reader Modernization 1.20.0`になっていることを確認する。
6. Login / Remember Me / Dashboard / Stock / Settings / Logoutを確認する。
7. 通常RSS／Search Feed／全RSS新着、RSS Typing、Wire Defenseを各1回確認する。
8. Wire Defenseで1秒reload、COREの緑→Orange→赤、straight／curve／wave routeを確認する。
9. PCとSmartphoneでDrawer、Modal、40px Headerを確認する。
10. Camera / Video / X / Mailを利用している場合、対象機能を1回更新しAsset revision Errorがないことを確認する。
11. DevTools ConsoleとPHP / Apache Error Logに新しいErrorがないことを確認する。

V1.20-F RC1から機能コードは変更していないため、正式版での主な差はVersion／Asset revision／Release metadataです。
