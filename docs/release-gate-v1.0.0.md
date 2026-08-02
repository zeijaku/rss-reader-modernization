# Version 1.0.0 Release Gate

## 判定の使い方

- PASS: 現在の証拠でRelease条件を満たす。
- HOLD: 後続工程または実環境確認が必要。FAILではないが、正式Releaseはできない。
- FAIL: 既知の不具合またはSecurity低下。修正するまで進めない。

## M4-F / Release Candidate RC1時点

| Gate | 状態 | 内容 |
|---|---|---|
| Baseline identity | PASS | M2-G commit、ZIP、Manifest、重要file Hashを固定 |
| Existing regression | PASS | Secure Baseline、M1、M2、M4-A〜E、M4-F専用testを継続実行 |
| Security contract | PASS | M2-Gから重要Security fileのHash変更なし |
| DB / API contract | PASS | schema、Migration、Public APIの変更なし |
| Repository public files | PASS | LICENSE、notice、license copy、公開Documentationを収録 |
| Third-party notice accuracy | PASS | jQuery 3.7.1、Font Awesome 6.7.2、配布Path、License copyを実Assetへ同期 |
| Installation / Update / Recovery | PASS | 設置、設定、更新、Backup、Restore、Rollbackを実コードへ同期 |
| GitHub / Portfolio / CI definition | PASS | Workflow、Security、Contribution、Repository設定、Portfolio資料を確認 |
| Release ZIP / Notes / SHA-256 | PASS | deterministic builder、内部Manifest、外部SHA-256、RC ZIP、Release Notes、Verifierを確認 |
| RC Version boundary | PASS | `1.0.0-rc1`、`RELEASE_CANDIDATE`、`publishable=no`。Final modeは拒否 |
| Environment probe / Evidence format | PASS | 必須Extension、Runtime directory、25項目Evidence、Secret混入、Gate exitを確認 |
| GitHub hosted CI / Settings | HOLD | PHP 8.1 / 8.4 JobとRepository Settingsを利用者が画面確認 |
| Real environment evidence | HOLD | 実MySQL、Browser、Feed、Backup / Restore、Rollbackの結果待ち |
| Version / Tag / GitHub Release | HOLD | M4-Gで1.0.0、v1.0.0、正式Artifactを確定 |

`Release ZIP / Notes / SHA-256` のPASSは、M4-F RCでPackage構成と再現性を確認したことを意味します。RCの `RELEASE_BUILD.txt` は `package_status=RELEASE_CANDIDATE`、`publishable=no` であり、正式Release Artifactではありません。

## Real environment evidence

Release gate category: `Real environment / RC`。

実環境結果は `docs/m4-f-validation-template.json` をPrivateな `var/m4f-evidence/` へCopyして記録します。Credential、Cookie、Session ID、実Feed URL、個人情報はEvidenceへ入れません。

```powershell
python tools/m4f_evidence_gate.py `
  .\var\m4f-evidence\m4-f-result.json --require-pass
```

Exit code `0`になったことをM4-Gの入力条件とします。PENDINGまたはBLOCKEDがある場合はHOLD、FAILがある場合は修正または再確認が必要です。

M4-E完了はVersion 1.0.0 Release可を意味しません。

M4-FのCheckpointをGitへpushしただけではReal environment evidenceはPASSになりません。M4-GではEvidence、GitHub hosted CI、Final Version、Tag、正式ZIPを同じRelease判断へ揃えます。
