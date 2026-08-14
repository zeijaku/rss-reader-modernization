# V1.14-F Apply Note

## 目的

V1.14-B〜Eで実施したBootstrap 5.3.8 / Bootswatch 5.3.8移行とBootstrap Offcanvas化を前提に、PC / Smartphone / 全8 Themeの見た目を最終調整するcheckpointです。

V1.14-Fでは機能追加やDB変更は行いません。Bootstrap 5移行後にTheme差が出やすい文字色・背景色・閉じるボタン・Navbar・Offcanvas・小画面レイアウトだけを対象にします。

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

## 変更しないもの

- DB schema / migration
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
- V1.14-F visual marker確認
- Settings icon optionの44px visual rule確認
- Bootstrap Offcanvas継続確認
- V1.14-Eで削除したlegacy dependencyが復活していないこと
- V1.14-E→Fの`app/` / `public/`差分が`public/css/dashboard.css`だけであること
- `git diff --check`

全回帰・PHP 8.1 / 8.4 matrix・Version 1.14.0正式化はV1.14-Gで実施しまぅ。

## 本番確認の重点

1. 現在使用中ThemeでDashboard / Stock / Settingsに大きな色崩れがない
2. 可能ならSolar / Slateを各1回選び、Form label・Stock・Calendar・Mail / Links / Weatherの文字が読める
3. Modal headerの×が全Themeで見える
4. Offcanvasの×が全Themeで見える
5. PCのNavbar link / menu buttonがLight / Dark Navbarで読める
6. SmartphoneでOffcanvasが画面全幅を覆わず、背景側にも閉じるための領域が残る
7. SmartphoneのModal footer buttonが重ならない
8. Consoleに新しいJavaScript errorがない
9. NetworkでBootstrap 5.3.8 bundleが読み込まれ、旧Drawer/iScroll/Popperが取得されない

細かな表示差が残る場合はV1.14-Gへ進む前にV1.14-F内で調整します。
