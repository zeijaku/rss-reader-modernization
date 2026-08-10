# V1.3-D 共通余白と細かなデザイン修正

## Baseline

- `rss-reader-modernization-v1.3-c-r1.zip`
- Development Version: `1.3.0-dev.3`

## 実装内容

### 記事Title余白

Bootstrapの`.table td`はClass単体の指定よりSpecificityが高く、V1.2で指定していた記事Cellの余白が実表示では上書きされていました。

V1.3-Dでは次のSelectorへ変更し、既存の意図した値を確実に適用します。

- `.feed-table .feed-item-title-cell`
- `.feed-table .feed-item-stock-cell`
- `.feed-table .feed-item-summary-cell`

記事Title Cellは`7px 2px 7px 6px`、左右操作Cellは`padding: 0`です。

### 三点リーダー

PCでは36px幅を維持し、記事Title表示幅を確保します。PointerがcoarseのTouch端末では、三点リーダー列とButtonを44pxへ拡張します。高さはPC／Touchとも44pxです。

### Widget見出し

`widget-title-text`を共通Hookとして使用し、通常RSS、Search Feed、Clock、Memo、Task、Calendarの以下を統一しました。

- Drag Handle後の開始位置
- 文字サイズ
- 行高
- Ellipsis
- 一行表示

Search Feedにも`widget-title-text`を追加しています。

## 維持事項

- Widget Header高44px
- 新着Bellの1行目だけを字下げする表示
- RSS概要「＋」の44px操作高
- 記事Actions機能
- Keyboard操作とFocus表示
- JavaScript処理
- DB構造
