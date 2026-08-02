# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: 現在の証拠でRelease条件を満たす。
- HOLD: 後続工程または実環境確認が必要。FAILではないが、正式Releaseはできない。
- FAIL: 既知の不具合またはSecurity低下。修正するまで進めない。

## M4-A時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | PASS 2247 / FAIL 0 / SKIP 7。SKIPは理由を維持 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copyをBaselineへ復元 |
| Third-party notice accuracy | HOLD | jQuery / Font Awesome表記が旧Version。M4-Bで更新 |
| Installation / Update / Recovery | HOLD | M4-Cで確定 |
| GitHub / Portfolio / CI | HOLD | M4-Dで整理 |
| Release ZIP / Notes / SHA-256 | HOLD | M4-Eで作成 |
| Real environment / RC | HOLD | M4-Fで実MySQL、Browser、Feedを確認 |
| Version / Tag / GitHub Release | HOLD | M4-Gで1.0.0とv1.0.0を確定 |

M4-A完了はVersion 1.0.0 Release可を意味しない。正式ReleaseにはHOLDをすべて解消する。
