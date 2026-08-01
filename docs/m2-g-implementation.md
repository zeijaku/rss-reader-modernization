# M2-G / R1 最終回帰・Documentation

## 目的

M2-A〜Fで変更したFrontendをまとめて確認し、M2完了Checkpointとして配置手順、依存関係、既知の制限、test結果を揃える。
新機能や大きな画面変更を追加する工程ではない。

## 実施内容

- Secure Baseline、M1-A〜G、M2-A〜Fの全回帰testを継続実行。
- M2完了状態を確認する `tests/test_m2g_final_regression.py` を追加。
- Documentation、local link、秘密情報pattern、手動確認項目を確認する `tests/test_m2g_documentation.py` を追加。
- README、CHANGELOG、Roadmap、Version policy、配置ChecklistをM2完了状態へ更新。
- [`m2-completion-summary.md`](m2-completion-summary.md) にM2-A〜Gの内容と保留事項をまとめた。
- ZIP Manifest、再展開後test、禁止file、入れ子ZIP、unsafe pathを最終確認する。

## 最終状態

- Frontend基盤、Feed表示、HTML semantics / Accessibility、Responsive / UI、Asset整理、互換依存更新まで完了。
- jQuery 3.7.1 full build、Font Awesome Free 6.7.2を使用。
- Bootstrap / Bootswatch 4.1.3、Popper 1系、jquery-drawer 3.2.2、iScroll 5.2.0-snapshotを維持。
- Mobile 1列、Tablet 2列、Desktop 4列、Stock列44px、Drawer通常36px / Touch 44pxを維持。

## 変更しない範囲

DB、Migration、`config/local.php`、公開API、Authentication、Authorization、Session、CSRF、SSRF、XSS、Feed Parser / Adapter、Item identity、Cache、Lock、HTTP 304、Retry、stale-if-errorは変更していない。
M2-Fから画面HTML、Dashboard CSS / JavaScript、Frontend library binaryも変更していない。

## 環境上の確認限界

Build環境では実MySQL、実Feed provider、Windows PowerShell、通常のDesktop Browserを完全には再現できない。
Chromium headless harnessはDBus runtime不足のためSKIPする。配置先でChrome / Edge、実DB、実RSS 2.0 / RSS 1.0 / Atomを確認する。
