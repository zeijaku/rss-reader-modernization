# V1.7-H / R3 standard-height compatibility adjustment

## Scope

V1.7-H/R2の実機確認で、標準Row 220pxでは通常RSSが約3件まで減り、Clock Timer／Gameの操作部が切れることを確認した。R3では縦2機能を維持しながら、縦2導入前の高さ1の情報量と操作性を優先して調整する。

## Grid row

Desktop／Tabletは次を使用する。

```css
grid-auto-rows: minmax(320px, auto);
```

320pxは標準Widgetの下限であり、通常RSS 5件と44px Headerを無理なく収めることを基準にした。

`widget_height=2`は引き続き`grid-row: span 2`。`grid-auto-flow:dense`は使用しない。

Smartphoneは従来どおり`grid-auto-rows:auto`へ戻す。

## RSS automatic count

R2の`scrollHeight`／`clientHeight`測定による自動Trimを廃止した。

```text
height 1 + auto => 5
height 2 + auto => 10
mobile + auto   => 5
```

これにより描画TimingやTitle行数によって3件まで減る挙動を避ける。

手動指定1～30件はR2の`widget_config`保存をそのまま利用する。指定件数がCardへ収まらない場合だけ`.is-scrollable-y`を付与する。

Search Feedの`search_limit`は変更しない。

## Clock and Game compatibility

Clock／Gameの高さ1は、縦2導入前の自然高を優先する。

- `clock-card-inner`／`mini-game-card-inner`は高さ1で`height:auto`
- 最低高320px
- Bodyを`overflow-y:hidden`で切らない
- 320pxを超える場合はGridの`auto`側でRowを拡張

このためTimerや5×5 Gameの主要操作を高さ1でも利用出来る。

高さ2では2 Row Spanを維持し、Cardをその領域へ伸ばす。

## DB／API

R3によるDB、Migration、API Route、Config変更はない。V1.7-Hの`widget_height`とR2のRSS`widget_config.item_limit`をそのまま利用する。
