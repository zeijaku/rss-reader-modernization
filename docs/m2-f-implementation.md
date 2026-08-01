# M2-F / R1 Frontend依存関係更新

## 目的

M2-E / R2で残した実行時Assetを確認し、現在のHTML / JavaScriptを大きく書き換えずに更新できる依存だけを更新する。

## 実施内容

- jQuery 3.3.1を3.7.1 full buildへ更新。
- `public/index.php` とLogin / Register画面の読込先を更新。
- Font Awesome Free 5.3.1を6.7.2へ更新。
- Font Awesome WebFontをCSSが参照するTTF / WOFF2 8ファイルへ入替え。
- 旧icon class alias、`fas` / `far` / `fab`、local font配信を維持。
- 旧jQueryと旧WebFont形式を安全に削除するPowerShell helperを追加。

## 据え置いた依存

Bootstrap / Bootswatch 4.1.3、Popper 1系、jquery-drawer 3.2.2、iScroll 5.2.0-snapshotは据え置いた。Bootstrapだけを4.6系へ入替えると7つのBootswatch themeとのVersionが混在し、Bootstrap 5へ移すとMarkup、plugin API、Popper、Drawerまで変更対象になる。このためM2-Fでは混在更新を避けた。

## 変更しない範囲

DB、公開API、Authentication、Authorization、Session、CSRF、SSRF、XSS、Feed Parser / Adapter、Item identity、Cache、Lock、HTTP 304、Retry、stale-if-errorは変更していない。Dashboard固有CSS / JavaScript、Responsive、Drawer密度、Stock列幅も変更していない。

## 配置

旧AssetはZIPの上書きだけでは残る。既存Git作業フォルダでは `tools/apply_m2f_cleanup.ps1` を `-WhatIf` で確認してから実行する。新しい空フォルダへ配置する場合は不要。
