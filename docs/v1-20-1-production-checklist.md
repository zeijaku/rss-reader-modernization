# V1.20.1 Production Checklist

1. Application code、`config/local.php`、Database、必要な`var/`DataをBackupする。
2. Production ZIPとsidecarのSHA-256が一致することを確認する。
3. V1.20.0から直接更新する場合、`013_v1_20_1_calendar_event_color.sql`の`@table_prefix`を`DB_TABLE_PREFIX`と合わせて実行する。C段階ですでに実行済みなら再実行不要。
4. ZIPを別Folderへ展開し、Application Rootへ相対PathでCodeを上書きする。
5. Footerが`RSS Reader Modernization 1.20.1`であることを確認する。
6. Drag Handle / Navbar / Memo / Calendar colors / Block Collapseを各1回確認する。
7. Login / Remember Me / Dashboard / Stock / Settings / Logoutを確認する。
8. Camera / Video / X / Mailを利用している場合、対象Widgetを1回更新しAsset revision Errorがないことを確認する。
9. DevTools ConsoleとPHP / Apache Error Logに新しいErrorがないことを確認する。

問題がなければ正式Git登録へ進みます。既存Tagは上書きしません。
