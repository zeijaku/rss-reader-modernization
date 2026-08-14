# V1.14-F Apply Note

## 目的

V1.14-B〜Eで実施したBootstrap 5.3.8 / Bootswatch 5.3.8移行とBootstrap Offcanvas化を前提に、PC / Smartphone / 全8 Themeの見た目を最終調整するcheckpointです。

V1.14-Fでは機能追加やDB変更は行いません。Bootstrap 5移行後にTheme差が出やすい文字色・背景色・閉じるボタン・Navbar・Offcanvas・小画面レイアウトを対象にします。

## 主な変更

- Bootstrap 4時代から残る `text-dark` のForm label / legendを、選択Themeの文字色へ追従させる
- SettingsのNavbar icon選択は既存markupを変えず、親要素Selectorで44px選択領域を確実に適用
- Bootstrap 5で`navbar-light`のTheme定義がなくなったことを考慮し、Light / Dark Navbarの文字contrastをapplication CSS側で安定化
- Dark headerのModal close iconをThemeに依存しない白色SVGへ固定し、Solar等でも見失わないよう調整
- Light固定のOffcanvas close iconをThemeに依存しない黒色SVGへ固定
- Stock / Memo / Task / Calendarの独自surface・文字・borderをBootstrap / Bootswatch Theme変数へ追従
- Solar / SlateのCalendar Sunday / Holiday / Saturday色を暗背景向けに微調整
- Mail / Links / Weather Widgetのsurface・文字・borderをTheme変数へ追従
- SmartphoneでOffcanvas幅を`min(16.25rem, 86vw)`とし、小さい画面でも背景側に閉じ領域を残す
- SmartphoneのModal footer button間隔をBootstrap 5向けに整理
- Mail / Links / Weatherはloaderを変更せず、`dashboard.css`側の高specificity overrideでTheme追従

## V1.14-F/R1：Card header背景色・文字色

本番確認でSearch Feedの見出し背景色が変更されないことを確認したため、Bootstrap 5のTable描画とCard header contrastを追加調整しました。

- Search Feedは`bg-*`を`tr`へ付ける構造をやめ、実際に描画される`th.feed-card-header`へ`text-bg-*`を付与
- 通常RSS / Search Feed / Clock / Game / Memo / Task / Links / Weather / Calendarの全Card headerを`bg-*`からBootstrap 5の`text-bg-*`へ統一
- Mail Widgetの動的Card headerも`text-bg-*`へ統一
- Card header内に固定されていた`text-white`を除去し、Title・編集/更新Icon・drag handleを親Headerの文字色へ追従
- `primary / secondary / success / info / warning / danger / dark`の既存保存値はそのまま使用
- Bootstrap / Bootswatch 5.3.8がThemeごとに持つ`text-bg-*`のforeground contrastを利用するため、背景色に応じて白/黒の適切な文字色を自動適用
- DashboardとStock画面の重複Widget描画の両方を同じ仕様へ統一

## 変更しないもの

- DB schema / migration
- 保存済み`widget_style`値
- API / RSS取得処理
- Widgetの機能仕様
- `config/`
- `var/`
- `.htaccess`
- jQuery 3.7.1
- Bootstrap / Bootswatch 5.3.8 vendor asset
- APP_VERSION（V1.14-Gの正式化までは1.13.0）
- V1.14-Eで削除したjquery-drawer / iScroll / standalone Popper

## Focused validation

V1.14-Fでは全回帰テストは行いません。

- PHP 8.1 syntax check
- 変更JavaScript syntax check
- Bootstrap / Bootswatch 5.3.8 asset checksum
- 全8 Theme resolver / asset存在確認
- 全Theme assetに7種類の`text-bg-*` utilityが存在すること
- 通常RSS / Search Feed / Clock / Game / Memo / Task / Links / Weather / Calendarの9 Card headerが`text-bg-*`を使用すること
- Search Feedの背景色Classが`tr`ではなく`th.feed-card-header`に付与されること
- Card headerの固定`text-white`が残っていないこと
- Mailの動的Card headerも自動contrastを使用すること
- Bootstrap Offcanvas継続確認
- V1.14-Eで削除したlegacy dependencyが復活していないこと
- V1.14-E→Fのproduction差分を限定確認
- `git diff --check`

全回帰・PHP 8.1 / 8.4 matrix・Version 1.14.0正式化はV1.14-Gで実施します。

## Validated checkpoint

- Initial F visual checkpoint: `5d0b2005514ec13abd2b8bcf1562b4050d4b94ee`
- F/R1 generated production commit: `d10011acfe1ca8dd809a8d2cd61a5af3e63e1f01`
- Production code diff from V1.14-E: `app/view/dashboard_widgets.php`, `public/css/dashboard.css`, `public/js/dashboard.js`, `public/js/mail-widget.js`, `public/stock.php`
- Final green run is recorded by the subsequent validation commit before production handoff.

## 本番確認の重点

1. Search Feedの見出し色を`warning / info / secondary`などへ変更し、背景色が実際に切り替わる
2. Search FeedのTitle・編集Icon・更新Iconが背景色に応じて読みやすい文字色になる
3. 通常RSS / Clock / Game / Memo / Task / Links / Weather / Calendarでも見出しのTitle・Iconが背景色に追従する
4. Mail Widgetを利用している場合、Mail見出しも同じcontrastになる
5. DashboardとStock画面の両方でSearch Feedの見出し色が一致する
6. 現在使用中ThemeでDashboard / Stock / Settingsに大きな色崩れがない
7. 可能ならBootstrap標準またはYetiと、Solar / Slateのいずれかを確認し、明暗両Themeで文字が読める
8. Modal / Offcanvas / NavbarのV1.14-F調整が維持されている
9. Consoleに新しいJavaScript errorがない
10. NetworkでBootstrap 5.3.8 bundleが読み込まれ、旧Drawer/iScroll/Popperが取得されない

細かな表示差が残る場合はV1.14-Gへ進む前にV1.14-F内で調整します。
