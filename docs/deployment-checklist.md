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

- [ ] **推奨構成**ではDocumentRootを `public/` にしている
- [ ] Application RootをWeb公開する互換構成の場合、Root `.htaccess` が有効で `app/` / `config/` / `tools/` / `var/` への直接Accessが403になることを確認した
- [ ] Apacheでは`mod_rewrite` / `mod_headers`が有効。Nginx等では`.htaccess`と同等のPrivate path拒否・Security Header・Public PHP whitelistをServer側へ設定した
- [ ] `public/`の直接実行PHPはPublic Endpoint Matrixの明示Whitelistだけで、未登録PHPは403になる
- [ ] Private runtime data、Secret、DB dump、LogをWeb公開領域へ置いていない
- [ ] `config/local.php`を上書きしていない
- [ ] 削除一覧がある場合だけ対象fileを削除した
- [ ] `var/session/`が書込み可能
- [ ] `var/security/login-throttle/`が書込み可能
- [ ] `var/cache/feed/`が書込み可能
- [ ] X Timelineを利用する場合は`var/cache/x/`が書込み可能
- [ ] Log有効時は`var/log/`または指定Pathが書込み可能
- [ ] 無条件な`777`を設定していない

## CLI

- [ ] `php -v`
- [ ] `php tools/healthcheck.php`
- [ ] `php tools/db_sb13.php verify`
- [ ] V1.1-Gでは`php tools/db_v11g.php verify`
- [ ] V1.1-Hでは`php tools/db_v11h.php verify`
- [ ] V1.1-Iでは`php tools/db_v11i.php verify`
- [ ] `bash tests/run.sh`
- [ ] `node --check public/js/dashboard.js`
- [ ] `node --check public/js/calendar.js`

`healthcheck.php`だけではDatabase接続を確認しないため、DB verifyまたは実動作確認も行います。

## Browser

- [ ] HTTPS
- [ ] Response HeaderのCSPに `frame-ancestors 'self'`, `base-uri 'self'`, `form-action 'self'`, `object-src 'none'` が含まれる
- [ ] Version表示
- [ ] Registration方針
- [ ] Login / Logout / Session
- [ ] 4タブ
- [ ] Feed追加 / 変更 / 削除 / 再読込
- [ ] 記事Titleの1～2行表示 / 全文Tooltip / RSS概要開閉
- [ ] Feed Card個別更新
- [ ] Search Feed追加 / 変更 / 削除 / 検索 / 個別更新
- [ ] 新着Bellの個別解除 / Feed単位解除
- [ ] 記事ActionsのStock / URL Copy / X / Task追加
- [ ] X Timelineを利用する場合は「上級者向け」案内、Bearer Token状態、公開Accountの投稿取得を確認
- [ ] Connection MonitorのOnline／Latency／30s・60s・5m／Avg・Max・Jitter／Qualityを確認
- [ ] Connection MonitorのOffline→Recovery／Downtime／Last Disconnectを確認
- [ ] 複数Connection MonitorでもProbeがPage全体で約5秒に1回であることを確認
- [ ] Background tabでProbe停止、復帰時に即時再開することを確認
- [ ] Clock追加 / 変更 / 削除
- [ ] Memo追加 / 変更 / 削除 / 改行表示
- [ ] Task Widget追加 / 変更 / 削除
- [ ] Task追加 / 変更 / 完了切替 / 期限 / 優先度 / 削除
- [ ] Calendar追加 / 変更 / 削除 / 月移動
- [ ] 通常予定の追加 / 変更 / 削除 / 複数日表示
- [ ] Task期限・優先度・完了状態のCalendar連動
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
