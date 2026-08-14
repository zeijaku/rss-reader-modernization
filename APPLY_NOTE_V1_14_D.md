# V1.14-D Apply Note

## 目的

V1.14-CでBootstrap 5.3.8 runtimeへ切り替えた状態を基準に、右側メニューをjquery-drawer 3.2.2からBootstrap 5.3.8 Offcanvasへ置き換えるcheckpointです。

V1.14-DではDrawerの動作主体だけを切り替えます。旧jquery-drawer / iScrollファイルの物理削除はV1.14-Eへ分離し、問題があった場合に比較・切り戻ししやすい状態を維持します。

## 主な変更

- Dashboard / Settings / Stockの右メニューを `offcanvas offcanvas-end` へ移行
- NavbarのPC/SPメニューボタンをBootstrap Offcanvas Data APIへ移行
- メニュー幅は従来jquery-drawerの `16.25rem` を維持
- Offcanvas内に明示的な閉じるボタンを追加
- jquery-drawerの独自Escape処理・Focus trapを廃止しBootstrap Offcanvasへ委譲
- Drawerが開いている間は既存のスマホタブスワイプを開始しないよう判定をOffcanvas状態へ変更
- Drawer内の「RSS追加」「Memo追加」「Account設定」等からModalを開く場合、Offcanvasを閉じてからModalを開くようにしてBackdropの重なりを防止
- Mail Widgetが動的に追加する「Mail追加」も同じDrawer -> Modal経路へ変更
- 変更したMail Widget JSを確実に取得出来るよう `calendar.js` 内のcache-busting queryをV1.14-D用に更新

## V1.14-Dでruntimeから外すもの

以下はHTMLからの読み込みを停止します。

- `public/css/drawer.min.css`
- `public/js/drawer.min.js`
- `public/js/iscroll.js`

ただしファイル自体はV1.14-Dでは削除しません。V1.14-Eで未使用を再確認した後に削除します。

## 変更しないもの

- DB schema / migration
- `config/`
- `.htaccess`
- `var/`
- jQuery 3.7.1
- Bootstrap / Bootswatch 5.3.8 assets
- APP_VERSION（正式なV1.14 finalizationまでは1.13.0）
- Themeごとの最終的な見た目調整

## Focused validation

V1.14-Dでは全回帰テストは行わず、Offcanvas切替に直接関係する検査へ限定します。

- Dashboard / Settings / StockにOffcanvas markupとData API triggerが存在すること
- jquery-drawer / iScroll assetがruntimeから外れていること
- 旧assetファイル自体はV1.14-Eまで残っていること
- `$.fn.drawer` / `.drawer()` / `drawer-open`依存がapplication JSから消えていること
- `bootstrap.Offcanvas.getOrCreateInstance()` を使用していること
- Drawer内Modal actionが専用sequencing経路になっていること
- Mail Widgetの動的Drawer itemも同じ経路を使用すること
- Bootstrap 5.3.8 asset checksum
- PHP syntax check
- 変更JavaScript syntax check
- `git diff --check`

全体回帰は計画どおりV1.14-Gで1回実施します。

## 本番確認の重点

V1.14-Dは右メニューの実装方式が変わるため、PCとスマホの両方で以下を重点確認してください。

1. 右上メニューボタンで右からOffcanvasが開く
2. ×、背景クリック、Escapeで閉じる
3. メニューを閉じた後にキーボードFocusが不自然な位置へ飛ばない
4. Drawer内のRSS追加 / Memo追加 / Account設定などからModalが正常に開く
5. Modalを閉じた後に画面操作へ戻れる
6. Stock / Settingsでも同じメニュー操作が出来る
7. スマホでDrawer表示中にDashboardタブスワイプが誤作動しない
8. Browser Consoleに新しいJavaScript errorがない
9. Networkでdrawer.min.css / drawer.min.js / iscroll.jsが取得されていない

細かな色・余白・Theme差はV1.14-Fでまとめて調整します。V1.14-Dでは操作不能、Backdrop残留、Modal競合、スクロール不能などの機能上の問題を優先して扱います。
