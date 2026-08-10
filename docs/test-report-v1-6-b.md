# V1.6-B Test Report

## Test Level

Feature＋横断回帰です。Swipe Indicator専用Testに加え、既存Swipe、Widget Drag、Header／Spacing、Icon Quest、Clock Timer、Smartphone Feed、全Theme、Core Security／Repository Gateを確認しました。

## 最終結果

| 範囲 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| V1.6-B専用 | 61 | 0 | 0 |
| Swipe／UI／Game／Timer横断 | 1,689 | 0 | 0 |
| Core／Security／Repository | 358 | 0 | 1 |
| Runtime Data cleanup後Repository再確認 | 15 | 0 | 0 |
| **実行出力合計** | **2,123** | **0** | **1** |

SKIP理由：実行環境にPDO SQLite Driverがないため、SQLite Integration Test 1件を実行出来ませんでした。ApplicationはMySQLを正本としており、今回DB処理は変更していません。

## V1.6-B専用確認

- 左Swipe：右端に左向き矢印
- 右Swipe：左端に右向き矢印
- Swipe距離によるOpacity増加
- 成立時の強調と160ms後のTab移動
- 短いSwipeの不成立
- 縦ScrollとTouch cancel
- Form、Timer／Game除外領域
- 最初／最後のTab
- PC非表示
- `pointer-events: none`
- Safe Area
- Reduced Motion
- 390pxでの横Overflowなし

## 横断確認

- 既存64px閾値、左右24px除外、縦／斜め判定
- Link、Button、Form、Calendar、横Scroll領域
- Widget Drag
- Icon Quest盤面、Storage、Keyboard、Theme
- Clock Timer、Storage Recovery、複数Tab同期、完了表示
- 360／420／1024px
- Bootstrap／Yeti／Minty／Flatly／Journal／Sketchy／Solar／Slate
- Smartphone Feed横Overflow、概要［＋］／［－］
- PHP Syntax、Dashboard JavaScript Syntax
- XSS安全なDOM操作、CSRF／API契約、Repository秘密情報Scan

## Repository Gate補足

Core Test実行中にTest自身が`var/session`とLogin Throttleへ一時Dataを生成したため、最初のRepository ScanがRuntime Dataを正しく検出しました。対象Dataを削除し、Repository Scanを再実行して15件すべてPASSしました。最終ZIPにはRuntime Dataを含めません。

## 実機確認

Headless Chromiumで390px Mobile表示を目視確認しました。iPhone Safari／Android Chromeの実機Gesture Navigationは利用者側確認項目です。
