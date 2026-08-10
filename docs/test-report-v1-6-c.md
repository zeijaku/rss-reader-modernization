# V1.6-C Test Report

## 最終結果

| 範囲 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| V1.6-C専用 | 68 | 0 | 0 |
| Icon Quest／Game Widget回帰 | 460 | 0 | 0 |
| Clock Timer／Swipe回帰 | 361 | 0 | 0 |
| Syntax／Asset／Repository Gate | 141 | 0 | 0 |
| **合計** | **1,030** | **0** | **0** |

## V1.6-C専用確認

- `lights_out` subtypeのValidation／保存Config
- 角、辺、中央の反転対象
- 25マス、ON／OFF、Clear判定
- 同じ押下を2回行うと元へ戻る性質
- 有効操作列から生成した盤面の可解性
- 空盤面を問題として返さないこと
- Moves、Reset、新しい問題、Clear
- Current boardとReset boardのCopy分離
- Icon QuestとLights Outの混在Render
- 複数Lights Out WidgetのRuntime分離
- 360px横Overflow、44px操作領域
- Solar Dark ThemeでのON表示
- Storage、音、通知、Vibrationを未実装であること

## 横断確認

- Icon Questの固定4 Level、Storage、Recovery、Keyboard、Theme
- Game Widget CRUD、所有者検証、並べ替え、Header
- Clock Timerの開始／一時停止／再開／Reset、Storage、複数Tab同期
- V1.6-B Swipe Indicator、縦Scroll、操作除外、Reduced Motion
- Frontend Asset Inventory
- PHP／JavaScript Syntax
- Repository Runtime Data／秘密情報除外

## 実施できなかったTest

- iPhone Safari実機
- Android Chrome実機
- V1.6-Dで予定するStorage復元／異常Recovery／削除Cleanup

V1.6-Cの対象機能でSKIPした自動Testはありません。
