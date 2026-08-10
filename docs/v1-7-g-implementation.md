# V1.7-G Widget Grid Prototype

## Scope

V1.7-Hで`widget_height`をDBへ追加する前に、既存Widgetを代表するFixtureでCSS Gridと並び替えを検証した。Application DashboardのMarkup、CSS、JavaScript、API、DBは変更していない。

## Compared layouts

### Fixed row

```css
.prototype-grid[data-layout-mode="fixed"] {
    grid-auto-rows: 220px;
}
```

- 高さ1は220px
- 高さ2は`220px × 2 + gap 12px = 452px`
- 右端の縦2Widgetが1段目と2段目を確実に占有する
- 長い本文はWidget Body内でScrollする
- Drag中もDrop領域の高さが安定する

### Content priority

```css
.prototype-grid[data-layout-mode="content"] {
    grid-auto-rows: minmax(220px, auto);
}
```

- 内容が短い場合は自然な高さを維持出来る
- SpanするWidgetの内容量がImplicit Row sizingへ影響する
- 縦2の見た目を常に「通常2個分」として保証しにくい
- 長いFeed／Taskによって同じRowの他Widgetが引き伸ばされる

## Decision for V1.7-H

正式実装は**固定Row方式を基本**とする。

- Desktop: 4列、Row Unitは既存13remに近い220px
- Tablet: 2列、同じRow Unitを基本に実機で微調整
- Smartphone: 1列、`widget_height`を表示高へ反映せず自動高
- 高さ2: `grid-row: span 2`
- `grid-auto-flow: dense`は使用しない
- DOM順、Keyboard順、Screen Reader順を一致させる
- Feed、Task、Memo、Calendarなど長文系はBody内Scrollを使用する
- Game、Clock／Timerは中央配置を維持する

固定Row方式は既存の「内容に応じてCard全体が伸びる」挙動を変えるため、V1.7-HではWidget種別ごとのBody Scroll、Focus、Wheel、Touch、概要展開を重点確認する。

## Fixture

順序は次の通り。

1. RSS Feed
2. Search Feed
3. Clock／Timer
4. Task 1×2
5. Memo
6. Calendar
7. Icon Quest
8. Lights Out
9. 横2 RSS

Desktop 4列では、Taskが右端で1～2段目を占有し、2段目左側へMemo、Calendar、Icon Questが配置される。

## Drag and keyboard

Prototypeは保存を行わないが、DOM要素の移動を確認出来る。

- Pointer Drag
- ArrowLeft／ArrowUp
- ArrowRight／ArrowDown
- Home
- End

正式実装でも並び順の正本はDOM順／`widget_sort_order`とし、見た目だけを詰め直すDense packingは採用しない。
