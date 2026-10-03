# RSS Reader Modernization 1.43.1

V1.43.1はDashboard表示の統一を目的としたUI maintenance releaseです。Application Version / Asset Revision は `1.43.1`。V1.43.0からDB Migration、config変更、API/Auth変更はありません。

## Dashboard Widget chrome

- 対応Widgetの外枠を1px border / 4px角丸 / overflow hiddenへ統一します。
- div型headerは実表示44pxを維持し、flex shrinkで短くならないよう固定します。
- Mail / Information / Blind Spot / Calculator / X / Camera / Game / Feed等、異なる実装元のCard外枠差をDashboard共通規格で吸収します。

## Feed / Search Feed header

- Feed系だけ残っていた`table > thead > tr > th` header構造を廃止します。
- 色付きheaderを`.feed-card-inner`直下の通常divへ移し、他Widgetと同じ44px headerモデルに統一します。
- `.content-title`、編集/更新Button、Search Feedの`.content-header`等の既存selectorは維持します。
- Feed tableのcolgroup / tbody / article row、All RSS Recent、Stock、Summary等の既存処理は変更しません。

## Page Top

- Desktopの表示幅・文字サイズは維持したまま、ラベルを意図的に`ページ` / `上部`の2行へ分割します。
- Smartphone / narrow viewportは従来どおり48×48pxの矢印のみです。
- `aria-label="ページ先頭へ移動"`を維持します。

## Verification limits

- PHP 8.1 / PHP 8.4 Current regressionを通過させます。
- Widget header contractでは共通outer frame / 44px headerとFeed headerがtable外divであることを固定します。
- Browser fixtureでは各Widget familyの外枠、header実測、Feed header構造、Page Top表示契約を確認できるよう更新しています。
- V1.43.0から追加SQLなしで更新できます。

## Release

Tag: `v1.43.1`

Runtime package: `rss-reader-modernization-1.43.1.zip`

Complete Source package: `rss-reader-modernization-1.43.1-complete.zip`
