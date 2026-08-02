# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: 現在の証拠でRelease条件を満たす。
- HOLD: 後続工程または実環境確認が必要。FAILではないが、正式Releaseはできない。
- FAIL: 既知の不具合またはSecurity低下。修正するまで進めない。

## M4-D時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | Secure Baseline、M1、M2、M4-A / Bを継続実行。SKIP理由を維持 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、Public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copyを収録 |
| Third-party notice accuracy | PASS | jQuery 3.7.1、Font Awesome 6.7.2、配布Path、License copyを実Assetへ同期 |
| Installation / Update / Recovery | PASS | 設置、設定、更新、Backup、Restore、Rollbackを実コードへ同期し専用test追加 |
| GitHub / Portfolio / CI definition | PASS | Workflow、Security、Contribution、Repository設定、Portfolio資料を追加し専用testで確認 |
| GitHub hosted CI / Settings | HOLD | push後にPHP 8.1 / 8.4 Job、Private vulnerability reporting、Description / Topicsを画面確認 |
| Release ZIP / Notes / SHA-256 | HOLD | M4-Eで作成 |
| Real environment / RC | HOLD | M4-Fで実MySQL、Browser、Feed、Restore drillを確認 |
| Version / Tag / GitHub Release | HOLD | M4-Gで1.0.0とv1.0.0を確定 |

M4-DのCI definition PASSは、Workflowの構文・権限・TriggerとLocal regressionのPASS。GitHub hosted runnerの成功を意味しない。push後のActions結果とRepository Settingsは利用者が確認する。実Hosting / MySQL / Browser / Feed / Restore証拠はM4-Fで確認する。

M4-D完了はVersion 1.0.0 Release可を意味しない。正式Releaseには残るHOLDをすべて解消する。
