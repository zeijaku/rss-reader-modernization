# M2-D / R2 Test Report

## Baseline

- M2-D / R1
- PASS: 1815
- FAIL: 0
- SKIP: 6

## M2-D / R2確認範囲

- Feed / StockのMobile 1列、Tablet 2列、Desktop 4列
- Feed tableのStock列44px固定と記事列の残り幅利用
- Drawer通常36px / coarse pointer 44pxの密度切替
- 長い日本語、URL、英数字の折返し
- Touch target、Modal、Navbar、Page Top、Drawer
- RSS追加先の表示
- RSSの明示削除、Cancel、二重送信防止
- Feed errorのCard単位再読込
- Mutation error / Stock successの画面内notice
- Feed / StockのFake PDO実描画
- 重複ID、Form / Label、ARIA、XSS-safe DOM
- SB / M1 / M2-A〜M2-C regression

## Project最終結果

```text
PASS: 1837
FAIL: 0
SKIP: 6
PHP syntax error: 0
JavaScript syntax: PASS
```

M2-D専用・拡張test:

- Responsive / UI structure: 52 checks
- R2 layout regression: 20 checks
- Mutation runtime: 20 checks
- Dashboard Feed / Stock render: 23 checks
- Feed retry runtime: 7 checksをM2-B runtimeへ追加

## 視覚確認

Fake PDOでFeed 8件とStock 5件のHTML実描画を確認し、8つのFeed tableすべてに `colgroup` とStock列hookがあることを確認しました。Headless Chromiumは実行環境で終了せずtimeoutしたため、pixel単位のScreenshot判定は行っていません。320 / 375 / 768 / 992px以上の実Browser目視は配置後Checklistに残しています。

## SKIP

Build環境にPDO SQLite、SimpleXML、mbstringがないことによる既存6項目です。
