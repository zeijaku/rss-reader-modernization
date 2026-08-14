# V1.14-C Apply Note

## 目的

V1.14-B で配置済みの Bootstrap 5.3.8 / Bootswatch 5.3.8 を実際の画面で使用するため、Bootstrap 4 系の記法を Bootstrap 5 系へ移行し、Frontend runtime を Bootstrap 5.3.8 へ切り替える checkpoint です。

V1.14-C は Frontend Modernization の切替工程です。機能追加、DB変更、DrawerのOffcanvas化、jQuery削除、デザイン全面変更は行いません。

## 主な変更

- Theme stylesheet resolver を Bootstrap / Bootswatch 5.3.8 のversioned assetへ切替
- `public/index.php` / `public/settings.php` / `public/stock.php` のBootstrap runtimeを `bootstrap.bundle-5.3.8.min.js` へ切替
- jQuery 3.7.1 は継続使用
- standalone Popper はruntimeから外し、Bootstrap bundle内のPopperを使用
- jquery-drawer / iScroll はV1.14-D/Eまで継続使用
- Bootstrap Data APIを `data-bs-*` 記法へ移行
- `form-group` / `form-row` / custom checkbox / select / input-group のBootstrap 5記法へ移行
- `sr-only` を `visually-hidden` へ移行
- `ml-*` / `mr-*` / `text-right` などをstart/end系utilityへ移行
- Modalの旧close buttonを `btn-close` へ移行
- Calendar / Mail Widget等、JavaScriptで動的生成するModal triggerもBootstrap 5記法へ移行
- Mail Widgetの旧badge color classをBootstrap 5系へ移行

## 変更しないもの

- DB schema / migration
- `config/` の設定項目
- `.htaccess`
- `var/` の運用
- APP_VERSION（V1.14正式版までは1.13.0のまま）
- jquery-drawer / iScrollの削除
- DrawerのBootstrap Offcanvas化
- jQuery 3.7.1の削除
- 全Themeの最終的な見た目調整

## Focused validation

V1.14-Cでは全回帰テストは実施せず、Bootstrap切替に直接関係する検査へ限定します。

- PHP syntax check
- 変更JavaScriptのsyntax check
- Bootstrap 4主要legacy attribute/classの残存scan
- Theme 8種のresolverとasset存在確認
- Bootstrap / Bootswatch 5.3.8 asset checksum確認
- jQuery -> Bootstrap bundleのruntime読込順確認
- standalone Popperがruntimeから外れていることを確認
- jquery-drawer / iScrollが引き続きruntimeに残っていることを確認
- 独自 `data-content-*` attribute保持確認
- `git diff --check`

全体回帰はV1.14-Gのrelease finalizationで1回実施する方針です。

## Generated source checkpoint

- Bootstrap 5 runtime migration commit: `3aed802ba5e459b6c26b712d671ec53866546877`
- Migration helper: `tools/v1.14-c-migrate.py`
- Migration helperは冪等性を確認済みで、既にV1.14-C化されたsourceへ再適用しても追加差分を発生させません。

## 本番確認時の注意

V1.14-CからBootstrap 5.3.8が実際に有効になります。V1.14-Bと異なり、フォーム、Modal、Navbar等にBootstrap 4との差による軽微な見た目の差が出る可能性があります。

機能上の崩れはV1.14-Cで修正対象です。一方、Themeごとの細かな余白・色・PC/SPの最終調整はV1.14-Fでまとめて行います。

本番確認時は、Dashboard / Drawer / Modal / Stock / Settings / Calendar / Mail Widgetを中心に確認してください。
