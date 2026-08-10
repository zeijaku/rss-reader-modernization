# V1.6-C Lights Out基本実装

## 目的

既存Mini Game Widget基盤へ、5×5のLights Outを第二Game subtypeとして追加します。押したマスと上下左右を反転し、すべて消灯するとClearです。

## Widget統合

- `widget_type`は既存の`game`
- `widget_config.game`へ`lights_out`を保存
- 既存Game CRUD、Tab、並べ替え、横幅、Header色を再利用
- Icon Questは従来どおり`icon_quest`
- DB Table／Column、Migration、SQL、API Routeは追加しない

## 基本Rule

- 5×5、25マス
- Tap／Click／Buttonの標準Keyboard Activation
- 押したマスと上下左右だけを反転
- Moves表示
- Resetは現在の初期盤面へ戻す
- 新しい問題は別の解ける盤面を生成
- 全消灯でWidget内にClear表示

## 問題生成

全消灯盤面へ10～20回の有効な押下を適用して問題を作ります。同じ操作列をもう一度適用すれば全消灯へ戻るため、生成盤面は必ず解けます。偶然全消灯になった場合は再生成し、上限到達時も中央押下による解ける盤面へFallbackします。

Solver、難易度判定、最短手数表示は追加していません。

## Runtime分離

Icon Questの`mini-game.js`はLights Out cardを明示的に除外し、Lights Outは`lights-out.js`だけで初期化します。Subtype未指定の旧FixtureはIcon Questとして扱うため、従来の後方互換も維持します。

## 状態保持

V1.6-Cでは盤面、初期盤面、Moves、Clear状態を各Widget DOMに保持します。Reload後の復元、Storage Fallback、Widget削除時Cleanup、User／Widget Storage KeyはV1.6-Dで実装します。

## Accessibility／表示

- 各マスはNative Button
- `role=gridcell`、行列Index、`aria-pressed`、点灯／消灯Label
- StatusはWidget内の`aria-live=polite`
- 44px操作領域
- 360pxで横Overflowなし
- Solar／SlateのDark surfaceへ明示的なON／OFF Contrast
- 既存Reduced Motion Ruleを継承

## Cache Busting

変更したAssetだけ個別に更新しました。

- `mini-game.css?v=1.6-c-r1`
- `mini-game.js?v=1.6-c-r1`
- `lights-out.js?v=1.6-c-r1`
- `dashboard.js?v=1.6-c-r1`

一元管理方式は導入していません。

## 非対象

- Browser Storageへの保存・復元
- Storage異常Recovery
- 複数Browser Tab同期
- Arrow Keyによる盤面内Roving Focus
- 音、Vibration、Browser Notification
- Solver、Hint、難易度、Best、統計
