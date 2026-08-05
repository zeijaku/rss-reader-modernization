# V1.4-D / R2 Focused Test Report

Game WidgetのCard外側余白とHeader左余白だけを対象に確認しました。

- 共通Card Selectorへの`.mini-game-card`追加
- 共通Inner Selectorへの`.mini-game-card-inner`追加
- Header高44px
- Header Padding `0 4px 0 8px`
- Game WidgetとClock WidgetのDrag Handle左位置一致
- 360pxで横Overflowなし
- Version Label `V1.4-D / R2`

Focused Test結果はPASS 26／FAIL 0／SKIP 0です。

Game本体、Storage、API、DB、既存RSSのFull回帰は実施していません。正式Full回帰はV1.4-Eで実施します。
