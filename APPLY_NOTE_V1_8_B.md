# V1.8-B / R1 適用メモ

## 目的

Version 1.8のStock一覧改善の第一段として、Stock一覧から既存Stockを安全に解除できるようにします。

## Application

- Version: `1.8.0-dev.1`
- Label: `RSS Reader Modernization V1.8-B / R1`
- Stock解除: `stock_flag = 1` の論理削除
- DB／Migration／SQL追加: なし
- 外部Library／Framework追加: なし

## 実装概要

- `stock.delete` API Actionを追加
- `stock_id + stock_owner + stock_flag = 0` でOwnershipと有効状態を確認
- API入口の既存Session認証／POST／CSRFをそのまま利用
- Stock一覧に「解除」Buttonを追加
- 解除前に確認表示
- 成功後は対象Stock CardだけをDOMから除去し、画面全体をReloadしない
- 最後の1件を解除した場合は既存のEmpty Stateを表示
- Smartphoneの解除Buttonは44px以上のTouch高さを確保

## DB

Migrationは不要です。既存の`content_stock.stock_flag`を利用します。

## Stable Release

GitHub `main` / `v1.7.0` はVersion 1.7.0の正式Baselineとして変更しません。

- Baseline branch: `main`
- Baseline commit: `3c5f6a8e981e3ddecd90cd8d228b95e57c037e6f`

Version 1.8.0の正式化はV1.8-Fで行います。
