# Version 1.15.0 Test Report

## Baseline

GitHub Release `v1.14.1`のComplete Source Artifactを取得し、Release metadataに記載されたSHA-256と一致することを確認したものをBaselineに使用しました。V1.15-A～Eの累積更新をこのBaselineへ適用してRelease Candidate treeを構成しています。

## Existing regression — PHP 8.4

既存`tests/run.sh`のRegression範囲を実行しました。Suite全体が長いためRelease作成環境の1回あたり実行上限を避けて区間分割していますが、SB-00～15、M1、M2、V1.1～V1.8、V1.13、およびV1.15までの非Browser Regression commandを通過させています。

GitHub CIと同じ条件へ寄せるため、Playwright Browser testはPlaywright unavailableとしてskipしました。GitHubの通常CI workflowもPlaywright / Chromiumをinstallしていません。

## Version 1.15 focused tests

- Backend / calculation / parser: PASS 32 / FAIL 0.
  - Information Widget共通Location validation
  - Weather compatibility wrapper / cache
  - Earthquake intensity / JMA URL / XML DTD・ENTITY拒否
  - Sun / Moon sunrise・sunset・moon age・next full moon
  - US AQI / UV boundary、Air Quality JSON parser / cache
- Static / API / UI contract: PASS 104 / FAIL 0.
  - Bootstrap require order
  - widget type allowlist
  - API action grammar
  - Drawer Catalog
  - CRUD / Refresh / D&D契約
  - shared Information UI classes
  - Height 1 / 2、Smartphone、Theme variable
  - Earthquake feed fallback / tsunami contract
  - Air Quality 15分Fresh / 24時間Stale
  - Version 1.15 DB Migrationなし

## Syntax / packaging

- PHP 8.4 lint: `app/` / `public/` / `tools/` 80 files PASS.
- `public/js/utility-widgets.js`: Node.js syntax PASS.
- Release / Complete package builder / verifier: Release Gateで実行。

## Environment-specific limitations

- PHP 8.1 CLIはRelease作成環境に存在しないため、GitHub Actions PHP 8.1 matrixを公開前必須確認とします。
- `test_v11i_r2_loading_browser.py`のCalendar loading Timeoutは正式v1.14.1 Artifactでも再現しました。
- `test_v14d_r2_game_header.py`の削除済み`public/css/bootstrap.min.css`参照も正式v1.14.1 Artifactで再現しました。
- これらはV1.15固有Regressionとは分離し、実Browser / Deviceでの最終確認項目として扱います。
- 実Hosting Server、実MySQL、外部Feed、JMA、Open-Meteo、IMAPは利用環境での最終確認が必要です。
