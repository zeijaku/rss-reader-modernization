# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: Repository内の証拠で条件を満たす。
- DISCLOSED: 配布物へ未収録のmanual evidenceに相当する実環境確認。未実施をPASSへ読み替えず、Release Notesへ明記する。
- FAIL: 既知の不具合またはSecurity低下。修正するまで公開しない。

## M4-G / Version 1.0.0時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | Secure Baseline、M1、M2、M4-A〜G testを継続実行 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、Public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copy、公開Documentationを収録 |
| Third-party notice accuracy | PASS | jQuery 3.7.1、Font Awesome 6.7.2、配布Path、License copyを同期 |
| Installation / Update / Recovery | PASS | 設置、設定、更新、Backup、Restore、Rollback手順を整備 |
| GitHub / Portfolio / CI definition | PASS | Workflow、Security、Contribution、Repository設定、Portfolio資料を確認 |
| Final Version boundary | PASS | `1.0.0`、`FINAL`、`publishable=yes`。RC / Preview modeは拒否 |
| Release ZIP / Notes / SHA-256 | PASS | deterministic builder、内部Manifest、外部SHA-256、正式ZIPを確認 |
| Secret / Runtime data exclusion | PASS | Private設定、実DB、Log、Session、Cache、Evidence、入れ子ZIPを除外 |
| GitHub hosted CI result | DISCLOSED | Workflow定義は検査済み。PHP 8.1 / 8.4実Run結果は配布物へ未収録 |
| Real environment evidence | DISCLOSED | 実MySQL、Feed、Browser、Backup / Restore、Rollback結果は配布物へ未収録 |
| Version / Tag / GitHub Release | PASS / USER ACTION | VersionとArtifactは確定。Tag / GitHub Releaseは利用者がrelease commitへ作成 |

`publishable=yes`は正式Release用のVersionとPackage構成であることを示します。個別Hosting環境の動作を自動的に保証するものではありません。

## Real environment / RC evidence

M4-Fの`docs/m4-f-validation-template.json`はHOLD / PENDINGのまま残しています。未実施項目を架空のPASSへ変更していません。利用者環境では次を確認できます。

```powershell
Copy-Item .\docs\m4-f-validation-template.json `
  .ar\m4f-evidence\m4-f-result.json
python tools/m4f_evidence_gate.py `
  .ar\m4f-evidence\m4-f-result.json --require-pass
```

Credential、Cookie、Session ID、実Feed URL、個人情報はEvidenceへ記録しません。Private EvidenceはRepositoryとRelease ZIPへ含めません。

## Final decision

```text
Automated regression       PASS
Final package              PASS
Application runtime delta  Version / Release資料のみ
Real environment evidence Not recorded in distribution
APP_VERSION                1.0.0
Intended Tag               v1.0.0
GitHub Release             利用者作業
```
