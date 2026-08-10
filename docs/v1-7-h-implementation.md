# V1.7-H Widget height implementation

## Scope

V1.7-GのPrototype結果を本番Dashboardへ反映した。既存の`widget_width` 1～4を維持し、`widget_height` 1～2を追加する。

```text
widget_height = 1  標準
widget_height = 2  Desktop／Tabletで縦2段
```

## Database and API

`dashboard_widget`へ`widget_height TINYINT UNSIGNED NOT NULL DEFAULT 1`を追加した。既存WidgetはMigration時に1となる。

全Widget CRUDで高さをValidationして保存する。

- RSS Feed
- Search Feed
- Clock／Timer
- Memo
- Task
- Calendar
- Game（Icon Quest／Lights Out）

値は1または2だけを許可し、Ownerは従来どおり認証Sessionから取得する。API Route、Widget Type、Config Fileは追加していない。

## Layout

Dashboard ContainerをCSS Gridとして扱う。

```css
Desktop: 4 columns / 220px row
Tablet:  2 columns / 220px row
Mobile:  1 column / auto row
```

高さ2は`grid-row: span 2`となる。`grid-auto-flow: dense`は使用せず、DOM順、Keyboard順、Screen Reader順、`widget_sort_order`を一致させる。

固定Rowで長文がCard全体を押し広げないよう、Feed、Task、Memo、CalendarなどはBody内Scrollを使用する。SmartphoneではRow固定と縦Spanを解除し、従来に近い自動高へ戻す。

## User interface

左Drawerへ新しいMenu項目は追加しない。縦幅は各Widgetの追加／編集Modalで設定する。

```text
横幅: 1列 / 2列 / 3列 / 全幅
縦幅: 標準 / 縦2段
```

編集Buttonには現在値を`data-widget-height`として出力し、Modal表示時に復元する。

## Drag and keyboard

並び替えの保存形式は変更しない。

- Pointer Drag & Drop
- ArrowLeft／ArrowUp
- ArrowRight／ArrowDown
- Home
- End

高さはWidget自身の保存値であり、並び替え後も維持される。Smartphone Swipe除外、Game盤面、Timer操作の既存競合回避も維持する。
