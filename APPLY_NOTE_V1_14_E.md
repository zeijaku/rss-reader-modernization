# V1.14-E Apply Note

## 目的

V1.14-Dで右メニューをBootstrap 5.3.8 Offcanvasへ切り替え、旧jquery-drawer / iScrollをruntimeから外した状態を基準に、不要になったFrontend dependencyを物理削除するcleanup checkpointです。

V1.14-Eでは機能追加や画面改修は行いません。V1.14-Dで使用停止済みであることを再確認したファイルだけを削除します。

## 削除するファイル

- `public/css/drawer.min.css` — jquery-drawer 3.2.2 CSS
- `public/js/drawer.min.js` — jquery-drawer 3.2.2 JS
- `public/js/iscroll.js` — iScroll 5.2.0-snapshot
- `public/js/popper.min.js` — 旧standalone Popper

standalone PopperはV1.14-Cからruntime参照がなく、現在は`bootstrap.bundle-5.3.8.min.js`に含まれるPopperを使用しています。

## 削除前に確認したこと

- Dashboard / Settings / StockはBootstrap Offcanvasを使用している
- `drawer.min.css` / `drawer.min.js` / `iscroll.js` はV1.14-Dでruntimeから外れている
- `popper.min.js` はV1.14-Cでruntimeから外れている
- application sourceに旧asset filenameのruntime参照がない
- `$.fn.drawer` / `.drawer()` / `drawer-open` / iScroll依存がapplication codeに残っていない

`drawer-nav` / `drawer-menu` / `drawer-item`などの名前は、既存デザインを維持するapplication側classとして残します。jquery-drawerライブラリへの依存ではありません。

## V1.14-Eで変更しないもの

- DB schema / migration
- `config/`
- `.htaccess`
- `var/`
- jQuery 3.7.1
- Bootstrap / Bootswatch 5.3.8
- Bootstrap Offcanvas実装
- APP_VERSION（V1.14正式版までは1.13.0）
- Themeごとの見た目調整

旧Bootstrap 4のunversioned assetについてはV1.14-Eの削除対象に含めません。今回の目的はV1.14-Dまでに明確に不要になったDrawer / iScroll / standalone Popperだけを安全に除去することです。

## Focused validation

V1.14-Eでは全回帰テストは行わず、dependency cleanupに直接関係する検査へ限定します。

- 削除対象4ファイルが存在しないこと
- application sourceに4ファイルへの参照が残っていないこと
- V1.14-D -> Eの`app/` / `public/`差分が4ファイル削除だけであること
- Bootstrap Offcanvas markup / Data APIが維持されていること
- Bootstrap bundle 5.3.8がruntimeで使用されていること
- Bootstrap / Bootswatch 5.3.8 asset checksum
- PHP syntax check
- Drawer関連の変更JavaScript syntax check
- `git diff --check`

全体回帰は計画どおりV1.14-Gで1回実施します。

## 本番確認の重点

V1.14-Eはcleanupのみのため、V1.14-Dと同じ操作が維持されていることを短時間で確認してください。

1. Dashboard右メニューがOffcanvasで開閉する
2. RSS追加 / Memo追加などDrawer内からModalが正常に開く
3. Stock / Settingsでも右メニューが正常に使える
4. Browser Consoleに新しいJavaScript errorがない
5. Networkで`drawer.min.css` / `drawer.min.js` / `iscroll.js` / `popper.min.js`が取得されていない
6. `bootstrap.bundle-5.3.8.min.js`は引き続き200で取得される

今回の4ファイルはZIP内からも削除されています。本番へ`app/` / `public/`を入れ替える運用であれば旧ファイルも自然に消えます。
