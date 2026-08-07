# V1.7-G Test report

## Result

```text
PASS 1,369
FAIL 0
SKIP 1
```

SKIP 1はHeadless Chromium Screenshot Testである。Chromium Binaryは存在したが、この実行環境ではProcessが終了せずTimeoutした。実Browser成功としては扱わず、Static DOM／CSS、配置Simulation、Node Runtime、既存Widget回帰で代替確認した。

## Prototype checks

- 代表Widget 9件
- 縦2Widget 1件
- 4列／2列／1列Rule
- Desktopの1段目4個／2段目3個＋縦2下半分
- Smartphoneで固定高解除
- 全8 Theme選択肢
- Fixed／Content priority比較
- Dense packing不使用
- Pointer Drag構造
- Keyboard並び替えPure Runtime

## Regression

- Dashboard Widget配置・並び替え
- Header／Spacing
- Icon Quest
- Lights Out
- Clock Timer
- Smartphone Swipe
- Asset URL一元化
- HTTP Cache／Security Header
- Remember Token
- 30日ログイン

## Decision

V1.7-Hは固定220px Row、`grid-row: span 2`、長文Widget Body内Scroll、Smartphone自動高を基本とする。内容優先方式はSpan Widgetの内容が周囲のImplicit Row高へ影響するため採用しない。
