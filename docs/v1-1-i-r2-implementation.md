# V1.1-I / R2 Implementation

## 目的

Calendarまで追加したDashboardの操作性を小さく改善する。

- スマートフォンの左右スワイプで4タブを切り替える。
- FeedとCalendarの読込中に、文字だけでなくSpinnerを表示する。

## Mobile swipe

スワイプは`767.98px`以下でだけ有効にする。左へ64px以上で次のタブ、右へ64px以上で前のタブへ移動する。最初と最後のタブでは循環しない。

縦Scroll、画面端の戻る操作、Calendar横Scroll、Widget Drag、入力操作との競合を避けるため、次を対象外にしている。

- Calendar Widget
- Button、Link、input、textarea、select、label
- Modal、Drawer
- Widget並び替えHandle
- 画面左右24px以内から始まる操作
- 複数Touch

## Loading Spinner

Feedの見出し・本文、Calendarの月読込へFont Awesomeの`fa-spinner fa-spin`を表示する。文字は残し、`aria-busy`と`role=status`も維持する。

成功・失敗後はSpinnerを削除し、失敗時は従来のError表示へ切り替える。`prefers-reduced-motion: reduce`では回転を止める。

## 変更しない範囲

- DB schema / Migration
- Public API
- Task / Calendar連動
- Widget並び替えAPI
- 4タブのURL形式
