# V1.6-D Test Report

## 最終結果

| 範囲 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| V1.6-C／D専用・互換 | 138 | 0 | 0 |
| Game／Icon Quest／Timer回帰 | 454 | 0 | 0 |
| Timer／Swipe未完了区間 | 101 | 0 | 0 |
| 全8 Theme・360／420／1024px | 265 | 0 | 0 |
| Syntax／Asset／Repository Gate | 138 | 0 | 0 |
| **合計** | **1,096** | **0** | **0** |

## V1.6-D専用確認

- localStorage優先
- sessionStorage Fallback
- memory Fallback
- 書込み途中障害からsessionStorageへFallback
- User／Widget単位Storage Key
- 盤面、初期盤面、Moves、Clear状態の保存・復元
- 複数Lights Out Widgetの分離
- 異なるUserのKey分離
- 壊れたJSON／未知状態の除去と安全な復旧
- 正常Copyと異常Copyが混在する場合の修復
- `savedAt`が新しい正常Copyの採用
- Widget Storage削除
- Arrow Key、Home、End、Roving tabindex
- Clear後のReset Focus
- 360px、44px操作領域、Dark Theme、Reduced Motion

## 横断確認

- Icon Quest固定4 Level、Storage、Recovery、Keyboard、Theme
- Game Widget CRUD、所有者検証、Header、並べ替え
- Clock Timerの状態保持、Recovery、複数Tab同期、Background復帰
- V1.6-B Swipe Indicator、縦Scroll、操作除外、Reduced Motion
- 全8 Theme、360／420／1024px
- PHP／JavaScript Syntax
- Asset Inventory
- Repository秘密情報／Runtime Data除外

## 実施できなかったTest

- iPhone Safari実機
- Android Chrome実機

自動TestでSKIPした項目はありません。V1.6-EではFull Regressionと正式Package検証を改めて実施します。
