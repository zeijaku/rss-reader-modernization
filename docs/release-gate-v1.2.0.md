# Version 1.2.0 Quality Gate

## Required

- [x] `APP_VERSION`とLabelが`1.2.0`で一致
- [x] V1.2-A～Dの機能をRelease Notesへ整理
- [x] DB／SQL／Migration／必須設定追加なしを確認
- [x] Full regression PASS 4,268／FAIL 0／SKIP 10
- [x] PHP 99 / JavaScript 19 / Python 137 / Shell 11 files syntax PASS
- [x] Documentation link PASS
- [x] Complete ZIP / Runtime ZIP build PASS
- [x] ZIP SHA-256 / CRC / Path / Manifest PASS
- [x] `config/local.php`と生成Runtime Dataの除外方針を確認
- [ ] 利用者の最終確認

## Manual verification

- Login／Logout／Session expiry
- 通常RSS／Search Feed
- 記事Title／概要／個別更新
- 新着Bell
- 記事Actions（Stock／URL Copy／X／Task）
- PC／Smartphone

R5の利用者確認は問題なしと報告済みです。正式版候補の配置とGitHub Releaseは成果物確認後に行います。

実環境固有のMySQL接続、Hosting設定、外部Feed到達性は利用者環境で確認します。
