# V1.20.1 User Checklist

- [ ] 現行Code / `config/local.php` / DB / 必要な`var/`DataをBackup
- [ ] Production ZIPとSHA-256が一致
- [ ] `013_v1_20_1_calendar_event_color.sql`の`@table_prefix`を実環境と一致させて実行（C段階ですでに実行済みなら再実行不要）
- [ ] Production ZIPを相対Pathで上書きし、`config/local.php`と実DBを維持
- [ ] Footerが`RSS Reader Modernization 1.20.1`
- [ ] Drag Handle `[=]` / D&D / Keyboard reorder
- [ ] Navbar Compact / Drawer
- [ ] Memo Height 1 / 2 / 内部Scroll / 手動Refresh
- [ ] Calendar赤・青・緑 / Task high・normal・low
- [ ] Block Collapse / Restart / New Game / Stability / Touch
- [ ] Login / Remember Me / Stock / Settings / Logout
- [ ] Console / PHP / Apache Error Logに新しいErrorなし

問題がなければGitのCommit / Push / Tag `v1.20.1` / GitHub Releaseへ進みます。
