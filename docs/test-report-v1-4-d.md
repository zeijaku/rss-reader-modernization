# V1.4-D Test Report

Test Level：Feature＋広範囲回帰

## 結果

- PASS：5,328
- FAIL：0
- SKIP：12

一括Runnerは実行環境の300秒上限を超えるため、`tests/run.sh`と同じ順序・同じCommandを区間分割して全適用対象を実行した。V1.3-Cの全Theme Header MatrixもTheme単位へ分割し、8 Theme合計672件を完走した。

V1.4-D専用では、Storage／Recovery 13件、Architecture 20件、Dashboard Render 29件、Browser操作16件、全8 Theme Matrix 144件を実行し、すべてPASSした。

## 主な確認範囲

- 初回Tutorialと確認状態保存
- Clear／Game Over表示
- 勝敗数とWidget単位の記録削除
- 正常Copyを残したStorage Recovery
- 全Copy異常時の安全な初期化
- 長時間後の状態復元
- User／Widget分離
- Key Repeat、二重Activation、Touch長押し対策
- 360px／420px／1024px
- 全8 Theme、Focus、44px、Contrast
- V1.4-B基盤、V1.4-C全4 Level
- 認証、Session、CSRF、SSRF、XSS
- RSS Engine、Cache、Conditional Request、Retry
- 通常RSS、Search Feed、Article Actions
- Clock、Memo、Task、Calendar、Account Settings
- Drawer、Header、共通余白

## SKIP

- PDO SQLite Driver不足
- SimpleXML／mbstring不足によるLive Parser系
- M2-F Chromium Runtime依存不足の旧Smoke Test
- Version 1.0／1.1／1.2／1.3正式版だけを対象とするHistorical Release Gate

V1.4-DのPlaywright Testは利用可能なChromiumで実行し、SKIPなし。正式Package／Release GateはV1.4-Eで実施する。
