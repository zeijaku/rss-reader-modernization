# M2 Frontend Modernization completion summary

## 完了Checkpoint

`Frontend M2-G / R1`

M2ではLegacy由来の基本的な見た目を残しつつ、Frontendの責務、表示状態、操作性、Asset構成を段階的に整理した。
大規模なSPA化やbuild tool導入は行わず、既存PHP + jQuery + Bootstrap構成の範囲で進めている。

## 工程

| Work unit | 主な内容 |
|---|---|
| M2-A | inline JavaScript / CSS整理、Dashboard script基盤、data属性への受渡し |
| M2-B | Feed通信とDOM描画分離、Loading / Empty / Error、欠損値と長い文字列 |
| M2-C | HTML5 landmark、Form / Button、Keyboard、Focus、ARIA |
| M2-D | 1 / 2 / 4列Responsive、明示的削除、Feed再読込、画面内通知、Drawer密度 |
| M2-E | 未使用CSS / JavaScript / SCSS / LESS / metadata / sprite削除 |
| M2-F | jQuery 3.7.1、Font Awesome Free 6.7.2へ互換更新 |
| M2-G | 全回帰、Asset / Documentation / 配布物整合、M2完了確認 |

## 現在のFrontend依存

| Dependency | Version / state |
|---|---|
| jQuery | 3.7.1 full build |
| Font Awesome Free | 6.7.2 |
| Bootstrap CSS / JS | 4.1.3 |
| Bootswatch themes | 4.1.3、7 themes + Normal |
| Popper | 1系、Bootstrap 4.1.3互換構成 |
| jquery-drawer | 3.2.2 |
| iScroll | 5.2.0-snapshot |

CDN、npm、Composer package、Webpack、Vite等は追加していない。

## 維持した機能と境界

- Login / Registration / Logout / Session。
- 4タブ、Feed CRUD、Stock、Settings。
- RSS 2.0 / RSS 1.0 / Atom。
- CSRF、SSRF、XSS、owner scope。
- deterministic Item identity、Cache、Lock、ETag / Last-Modified / HTTP 304。
- Retry / Backoff / Retry-After / stale-if-error。
- 公開API action / response contractとDB構造。

## 配置先での手動確認Matrix

画面幅は最低限、320px、375px、768px、992px、1280pxを確認する。
ThemeはNormal、Yeti、Minty、Flatly、Journal、Sketchy、Solar、Slateを確認する。

機能はLogin / Registration切替、Logout、4タブ、Feed表示・追加・変更・削除・再読込、Stock、Settings、Drawer、Modal、Popover、Page Topを確認する。
ConsoleにJavaScript errorがなく、CSS / JavaScript / WebFont / faviconがHTTP 200であることを確認する。
KeyboardではSkip link、DrawerのEscape / Tab循環 / focus return、Modalのfocusを確認する。

## M2完了後へ分離した内容

- Bootstrap 5へのmajor migration。
- Bootstrap / Bootswatch / Popper / Drawerを含む一括更新。
- 全Themeのcontrastを個別に作り込む調整。
- npm / bundler導入やAsset pipeline化。
- SPA化やFrontend framework導入。

これらはM2へ混在させず、必要性を確認した上で別工程として扱う。
