# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: 現在の証拠でRelease条件を満たす。
- HOLD: 後続工程または実環境確認が必要。FAILではないが、正式Releaseはできない。
- FAIL: 既知の不具合またはSecurity低下。修正するまで進めない。

## M4-C時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | Secure Baseline、M1、M2、M4-A / Bを継続実行。SKIP理由を維持 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、Public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copyを収録 |
| Third-party notice accuracy | PASS | jQuery 3.7.1、Font Awesome 6.7.2、配布Path、License copyを実Assetへ同期 |
| Installation / Update / Recovery | PASS | 設置、設定、更新、Backup、Restore、Rollbackを実コードへ同期し専用test追加 |
| GitHub / Portfolio / CI | HOLD | M4-Dで整理 |
| Release ZIP / Notes / SHA-256 | HOLD | M4-Eで作成 |
| Real environment / RC | HOLD | M4-Fで実MySQL、Browser、Feed、Restore drillを確認 |
| Version / Tag / GitHub Release | HOLD | M4-Gで1.0.0とv1.0.0を確定 |

M4-CのInstallation / Update / Recovery PASSは、DocumentationとSource / Package contractのPASS。実HostingでのRestore成功を意味しない。実環境証拠はM4-Fで確認する。

M4-C完了はVersion 1.0.0 Release可を意味しない。正式Releaseには残るHOLDをすべて解消する。
