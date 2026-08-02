# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: 現在の証拠でRelease条件を満たす。
- HOLD: 後続工程または実環境確認が必要。FAILではないが、正式Releaseはできない。
- FAIL: 既知の不具合またはSecurity低下。修正するまで進めない。

## M4-E時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | Secure Baseline、M1、M2、M4-A〜Dを継続実行。SKIP理由を維持 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、Public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copyを収録 |
| Third-party notice accuracy | PASS | jQuery 3.7.1、Font Awesome 6.7.2、配布Path、License copyを実Assetへ同期 |
| Installation / Update / Recovery | PASS | 設置、設定、更新、Backup、Restore、Rollbackを実コードへ同期し専用test追加 |
| GitHub / Portfolio / CI definition | PASS | Workflow、Security、Contribution、Repository設定、Portfolio資料を追加し専用testで確認 |
| Release ZIP / Notes / SHA-256 | PASS | deterministic builder、内部Manifest、外部SHA-256、Preview ZIP、Release Notes、Verifierを確認 |
| Tag / GitHub Release procedure | PASS | annotated Tag、Artifact添付、誤Tag対応、公開後不変方針をDocument化 |
| GitHub hosted CI / Settings | HOLD | M4-E開始時点でstatus / workflow runを確認できず。利用者がActionsとSettingsを画面確認 |
| Real environment / RC | HOLD | M4-Fで実MySQL、Browser、Feed、Restore drillを確認 |
| Version / Tag / GitHub Release | HOLD | M4-Gで1.0.0、v1.0.0、正式Artifactを確定 |

`Release ZIP / Notes / SHA-256` のPASSは、M4-E PreviewでPackage構成と再現性を確認したことを意味する。Previewの `RELEASE_BUILD.txt` は `package_status=PREVIEW`、`publishable=no` であり、正式Release Artifactではない。

M4-DのCI definition PASSは、Workflowの構文・権限・TriggerとLocal regressionのPASS。GitHub hosted runnerの成功を意味しない。M4-E開始時点ではGitHub ConnectorからM4-D commitのstatus / workflow runを確認できなかったため、Actions画面の確認をHOLDとして維持する。

M4-E完了はVersion 1.0.0 Release可を意味しない。実Hosting / MySQL / Browser / Feed / RestoreはM4-F、exact Version / Tag / GitHub ReleaseはM4-Gで確認する。
