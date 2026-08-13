# V1.13-D 適用メモ — index.php 可読性改善

## 目的

V1.13-CをBaselineとして、`public/index.php` に残っているDashboard表示コードを再評価し、既存動作を変えずに内部Viewへ整理します。

V1.13-Dでは分割しすぎないことを優先し、Widgetごとの細分化は行いません。

## Production変更

追加する内部Viewは2つだけです。

- `app/view/dashboard_widgets.php`
  - DashboardのWidget取得・描画を一括で保持
  - Feed / Search Feed / Clock / Memo / Task / Game / Links / Weather / Calendarを個別ファイルへ分けない
- `app/view/dashboard_modals.php`
  - Dashboardで使用する各種追加・変更Modalを一括で保持
  - Account Settings Modalも従来どおりDashboard側に維持

`public/index.php` は認証、Session、旧Stock URL互換Redirect、Head/Asset、Navbar、Drawer、Footer等のEntry Point / Page Shellを引き続き保持します。

## 移動方法

既存コードを整形し直さず、V1.13-Cの該当Blockをそのまま内部Viewへ移動しています。

V1.13-Dの確認では、2つのViewをinclude位置へ展開したDashboardソースがV1.13-C `public/index.php` と完全一致することをSHA-256で確認しています。

- V1.13-C `public/index.php`: `bb9f03e66cc263260d14b210f0720ce019ec0c071bbf9acbac7c09fbb32e19d4`
- V1.13-D 展開後Dashboardソース: 同一

## サイズ

- V1.13-C `public/index.php`: 147,418 bytes / 1,575 lines
- V1.13-D `public/index.php`: 26,443 bytes / 437 lines
- `app/view/dashboard_widgets.php`: 49,860 bytes / 426 lines
- `app/view/dashboard_modals.php`: 71,256 bytes / 714 lines
- 3ファイル合計: 147,559 bytes

`index.php`単体は約82%小さくなっていますが、これはPerformance改善ではなく構造分離です。V1.13-Eで実測結果に基づくPerformance判断を行います。

## DB / Config / API

変更ありません。

- 新規Table: なし
- Column変更: なし
- Migration追加: なし
- `config/local.php` 変更: なし
- API Action変更: なし
- JavaScript / CSS変更: なし

状態変更処理は従来どおり `public/api_v1.php` + 認証 + CSRF + owner scopeを使用します。

## 適用

V1.13-C環境をBackupした上で、このZIP全体を上書き適用してください。

DB / SQL作業はありません。
