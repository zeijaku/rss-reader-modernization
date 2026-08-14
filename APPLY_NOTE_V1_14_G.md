# V1.14-G Apply Note

## 目的

Version 1.14の正式Release Gateです。V1.14-B〜F/R1で完了したBootstrap / Bootswatch 5.3.8移行、Bootstrap Offcanvas化、旧Frontend dependency整理、PC / Smartphone / 全8 Theme調整、Card header contrast対応をVersion 1.14.0として確定します。

V1.14-Gでは新機能追加やDB変更は行いません。Release Version、Documentation、配布物、Dependency inventory、全Regressionを現行Sourceへ揃えます。

## 最終化内容

- `APP_VERSION = 1.14.0` / `APP_VERSION_LABEL = RSS Reader Modernization 1.14.0`
- README / CHANGELOG / RELEASE_NOTESをVersion 1.14.0へ更新
- `docs/dependencies.md` / `THIRD_PARTY_NOTICES.md`をBootstrap / Bootswatch 5.3.8実配布構成へ更新
- Release package builder / verifierを1.14.0へ更新
- Version / Package / Tag手順を1.14.0へ更新
- F/R1までrollback用に残していたBootstrap / Bootswatch 4.1.3旧Assetを、runtime参照0確認のうえ削除
- jquery-drawer / iScroll / standalone Popperの削除状態を維持

## 変更しないもの

- DB schema / Migration / SQL
- `config/local.php` / 必須config項目
- `var/` Runtime Data
- `.htaccess`
- RSS取得 / API契約 / Stock / Memo / Task / Calendar / Mail等の機能仕様
- jQuery 3.7.1
- Font Awesome Free 6.7.2
- Bootstrap / Bootswatch 5.3.8 vendor content

## Release Gate

GitHub Actionsで次を確認します。

1. V1.14-G finalizerの冪等適用
2. Application Version / Release Documentation整合
3. Bootstrap / Bootswatch 5.3.8 checksum
4. 全8 Theme resolver
5. Bootstrap 4旧Asset、jquery-drawer、iScroll、standalone Popperの不存在
6. 旧Assetへのruntime参照0
7. PHP 8.1 `tests/run.sh` 全Regression
8. PHP 8.4 `tests/run.sh` 全Regression
9. `git diff --check`
10. Runtime Release ZIP生成 / verifier
11. Complete Source ZIP生成 / verifier
12. SHA-256 / Manifest / Secret / Runtime Data除外確認

## Database / Production update

Version 1.13.0から1.14.0へのDB Migrationはありません。

本番では旧Frontend Assetを残さないため、`app/`と`public/`をBackup後に入れ替える方法を推奨します。`.htaccess`、`config/`、`var/`、DBは既存環境を維持します。

## Validated checkpoint

Release Gate完了後に最終Source commit、GitHub Actions run、Artifact SHA-256を追記します。
