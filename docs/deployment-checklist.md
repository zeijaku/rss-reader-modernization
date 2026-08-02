# 配置確認Checklist

## 配置前

- [ ] 対象Version、Commit、ZIP SHA-256を記録した
- [ ] Release Notes、変更 / 新規 / 削除fileを確認した
- [ ] DB migrationと必須設定追加の有無を確認した
- [ ] `config/local.php`、`APP_HASH_KEY`、DatabaseをBackupした
- [ ] Database dumpのSizeとSHA-256を確認した
- [ ] Rollback先Versionを確保した
- [ ] Maintenance時間と連絡方法を決めた
- [ ] 別環境または展開先でPackageを確認した

## Package

- [ ] Top-level directoryが1つ
- [ ] ZIP path traversal / absolute path / duplicate entryなし
- [ ] 入れ子ZIPなし
- [ ] `config/local.php`、`.env`、実DB、Log、Session、Cacheなし
- [ ] `LICENSE`、`THIRD_PARTY_NOTICES.md`、`licenses/`あり
- [ ] ManifestとSHA-256一致

## 配置

- [ ] DocumentRootは `public/`
- [ ] Private directoryはWeb公開外
- [ ] `config/local.php`を上書きしていない
- [ ] 削除一覧がある場合だけ対象fileを削除した
- [ ] `var/session/`が書込み可能
- [ ] `var/security/login-throttle/`が書込み可能
- [ ] `var/cache/feed/`が書込み可能
- [ ] Log有効時は`var/log/`または指定Pathが書込み可能
- [ ] 無条件な`777`を設定していない

## CLI

- [ ] `php -v`
- [ ] `php tools/healthcheck.php`
- [ ] `php tools/db_sb13.php verify`
- [ ] V1.1-Gでは`php tools/db_v11g.php verify`
- [ ] V1.1-Hでは`php tools/db_v11h.php verify`
- [ ] `bash tests/run.sh`
- [ ] `node --check public/js/dashboard.js`

`healthcheck.php`だけではDatabase接続を確認しないため、DB verifyまたは実動作確認も行います。

## Browser

- [ ] HTTPS
- [ ] Version表示
- [ ] Registration方針
- [ ] Login / Logout / Session
- [ ] 4タブ
- [ ] Feed追加 / 変更 / 削除 / 再読込
- [ ] Clock追加 / 変更 / 削除
- [ ] Memo追加 / 変更 / 削除 / 改行表示
- [ ] Task Widget追加 / 変更 / 削除
- [ ] Task追加 / 変更 / 完了切替 / 期限 / 優先度 / 削除
- [ ] RSS 2.0 / RSS 1.0 / Atom
- [ ] Stock保存 / 一覧
- [ ] Settings / Navbar / Tab名
- [ ] Drawer / Modal / Page Top
- [ ] Keyboard / Focus / ARIA
- [ ] 8テーマ
- [ ] 320 / 375 / 768 / 992 / 1280px
- [ ] JavaScript Console errorなし
- [ ] CSS / JS / WebFont / faviconがHTTP 200

## 配置後

- [ ] Error logを確認した
- [ ] Database row countに異常がない
- [ ] Backupと実施記録を安全な場所へ保存した
- [ ] GitHub mainまたはRelease Assetと配置物のVersionが一致する
- [ ] 問題がある場合のRollback判断者を決めた

## M4-Fへ残す証拠

- 実PHP / MySQL Version
- 有効Extension
- healthcheck結果
- DB verify結果
- Browser / Responsive結果
- 実Feed結果
- Backup / Restore drill結果
- 既知の制限事項
