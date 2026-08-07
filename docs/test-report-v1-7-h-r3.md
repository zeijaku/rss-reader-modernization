# V1.7-H / R3 Test Report

## Result

Secure BaselineからV1.7-H/R3までの`tests/run.sh`順序を維持し、実行時間上限に合わせて重複しない区間へ分割して実行した。

```text
PASS 6344
FAIL 0
SKIP 15
```

SKIPは不足Extension、Headless Browser環境、過去正式Version専用Release Gateによるもの。

途中で`SOURCE_BUILD.txt`がR2のBaseline情報のままだったためV1.7-B系履歴Testが1件失敗したが、R3のBaselineをR2 ZIPへ更新後に同Testを再実行しPASSした。Application機能のFailureではない。

## R3 focused checks

- 標準Row 320px下限
- 高さ2の2 Row Span維持
- Smartphone自動高
- RSS自動5件／10件
- RSS手動1～30件
- R2 DOM高さTrim撤去
- Clock／Game高さ1の自然拡張
- Clock／Game `overflow:hidden`切断撤去
- Migration 009なし
- SQL Prefix対応／`information_schema`非依存維持
- Feed runtime
- Clock Timer runtime／recovery
- Icon Quest runtime／storage
- Lights Out runtime／storage
- V1.7-B～H architecture

## Browser limitation

このExecution環境のChromium Headlessは一部の直接Screenshot測定でProcessが終了せずTimeoutする既知状態のため、R3専用Screenshot成功とは記録していない。既存Browser Test、DOM Render Test、CSS／JavaScript構造Test、Runtime Testで代替確認した。

## Syntax

```text
PHP 112 files: PASS
JavaScript 27 files: PASS
```

## Re-extracted package focused regression

ZIPを別Directoryへ再展開し、V1.7-B～H、Feed Runtime、Clock Timer、Lights Out Storageを再実行した。

```text
PASS 350
FAIL 0
SKIP 0
```
