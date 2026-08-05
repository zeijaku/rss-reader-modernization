# V1.4-B / R1 確認Checklist

## 配置後

- Footerに`RSS Reader Modernization V1.4-B / R1`が表示される。
- DrawerのWidget追加に`Game追加`が表示される。
- 現在のTabへGame Widgetを追加できる。
- 見出し、横幅、見出し色を変更できる。
- Game Widgetを複数追加できる。
- Game Widgetを既存Widgetと一緒に並べ替えできる。
- 別TabにもGame Widgetを追加できる。
- Game Widgetを削除できる。

## Mock盤面

- 5×5盤面が表示される。
- Player、Enemy、Treasure、Goal、WallがFont Awesomeで表示される。
- 盤面がWidget幅から横にはみ出さない。
- ResetでBrowser保存状態を初期化できる。
- V1.4-Bでは盤面を押してもPlayerが移動しない。

## PC／Smartphone

- PCのMouseで編集、Reset、並べ替えができる。
- KeyboardでDrag HandleへFocusでき、既存の矢印並べ替えが動く。
- 盤面上のTouchでTab Swipeが誤作動しない。
- 360px幅で盤面とResetが収まる。
- 使用中Themeで見出し、盤面、Focusが確認できる。

## 問題発生時

- BrowserをHard Reloadする。
- ConsoleにJavaScript Errorがないか確認する。
- Game Widgetを削除して再追加する。
- Resetを実行する。
- 他のBrowserまたはPrivate Windowでも確認する。
