# M2-E / R2 — 不要Frontend Asset整理

## 目的

M2-D / R2の画面、操作、API、DB、RSS Engineを変更せず、Legacy由来で同梱された開発用・重複Frontend Assetを整理する。

M2-EではLibraryのVersion更新、CDN化、npm導入、CSS結合、独自subset作成は行わない。Version更新はM2-Fへ分離する。

## 調査方法

次の参照を確認した。

- `public/index.php` と `app/common/common_login.php` の `link` / `script`
- `app/common/common_func.php` のTheme whitelist
- 保持するCSS内の `url()` とSource Map参照
- PHP / HTMLで使用するFont Awesome class
- vendor file先頭のLicense header

## 保持したAsset

### CSS

- `bootstrap.min.css` と、そのSource Map
- Bootswatch 7テーマ
- `all.css`
- `drawer.min.css`
- `dashboard.css`

通常Bootstrapを含め、Themeは合計8種類を維持した。

### JavaScript

- `jquery-3.3.1.min.js`
- `popper.min.js`
- `bootstrap.min.js` と、そのSource Map
- `iscroll.js`
- `drawer.min.js`
- `dashboard.js`

`popper.min.js` はBaselineにMap本体がなかったため、末尾のSource Map hintだけを削除した。実行コードは変更していない。

### WebFont

`all.css` が参照するFont AwesomeのEOT / SVG / TTF / WOFF / WOFF2を維持した。M2-EではBrowser互換を推測して削りすぎない方針とした。

## 削除したもの

- Bootstrapの非圧縮版
- Bootstrap bundle
- Bootstrap grid / reboot単独版
- 使用していないSource Map
- Font AwesomeのJavaScript版と個別CSS
- Font AwesomeのSCSS / LESS
- Font Awesome metadata
- Font Awesome SVG sprite
- Drawer非圧縮版

完全一覧は [`m2-e-deleted-assets.txt`](m2-e-deleted-assets.txt) を参照する。

## 配置方法

新規フォルダへ展開する場合、古いAssetは残らない。

既存Git作業フォルダへ上書きする場合、ZIPに含まれない旧Assetは自動削除されない。上書き後に次を実行する。

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\apply_m2e_cleanup.ps1 -WhatIf
powershell -ExecutionPolicy Bypass -File .\tools\apply_m2e_cleanup.ps1
```

ScriptはGit working treeと`public/index.php`を確認してから、M2-Eで廃止した範囲だけを削除する。Windows PowerShell 5.1でも文字コードに依存しないよう、cleanup helper本体はASCIIだけで記述している。

## 変更しない範囲

- DB構造 / Migration
- 公開API Action / Response
- Authentication / Authorization / Session / CSRF
- SSRF-safe Fetch / Feed Parser / Adapter / Item Identity
- Cache / Lock / HTTP 304 / Retry / stale-if-error
- Login / Register / 4タブ / Feed CRUD / Stock / Settings
- Responsive 1 / 2 / 4列、Stock列44px、Drawer密度
- Frontend LibraryのVersion

## 規模

`public/`配下は127ファイル・15,721,978 bytesから、39ファイル・約4.88MBへ整理した。88ファイル、約10.84MBを削減した。
