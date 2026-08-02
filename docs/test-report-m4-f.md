# M4-F / R1 Test Report

## Result

M4-Fでは`1.0.0-rc1`の自動Regression、Release Candidate Package、Checkpoint Package、環境Probe、実環境Evidence Gateを確認しました。

```text
Source tree regression       PASS 4927 / FAIL 0 / SKIP 7
Checkpoint ZIP regression    PASS 4927 / FAIL 0 / SKIP 7
Checkpoint package           PASS 770 / FAIL 0
RC package verifier          PASS 652 / FAIL 0
```

## M4-F dedicated tests

```text
Release Candidate builder    PASS 51
Environment probe            PASS 32
Evidence gate                PASS 16
Documentation                PASS 193
```

Release Candidate testでは、同じSourceから2回生成したZIPが同じSHA-256になること、RC marker、`publishable=no`、Final mode拒否、Preview mode拒否、Private Evidenceが残っている場合のBuild拒否を確認しました。

## Syntax

```text
PHP syntax       72 files PASS
JavaScript       10 files PASS
Python AST       76 files PASS
```

## Build environment probe

```text
PHP              8.4.16 CLI
PDO              available
pdo_mysql        unavailable
cURL             unavailable
SimpleXML        unavailable
mbstring         unavailable
PDO drivers      なし
Probe status     HOLD
--require-ready  exit 2
```

Runtime directoryの存在と書込み権限はPASSです。ProbeはDBへ接続せず、Credential、Cookie、Session ID、Feed URLを表示しません。

## SKIP / HOLD

自動RegressionのSKIPは既存の7件です。

- PDO SQLite driverがないためSQLite integrationをSKIP。
- SimpleXML / mbstringがないためlive parser / adapter matrixをSKIP。
- Chromium runtimeが完走しないためBrowser smokeをSKIP。

次はこのBuild環境では実行できず、PASSへ読み替えていません。

- 実MySQL接続とSchema verify。
- 実RSS 2.0 / RSS 1.0 / Atom取得。
- 実Browserでの8 Theme / Responsive /操作確認。
- Backupから別DBへのRestore drillとRollback確認。
- GitHub hosted CIのPHP 8.1 / 8.4 Job確認。

これらは`docs/m4-f-validation-template.json`をPrivateな`var/m4f-evidence/`へCopyして記録し、`tools/m4f_evidence_gate.py --require-pass`がexit 0になるまでRelease GateをHOLDとします。

## Package safety

- ZIP CRC、重複Entry、絶対Path、`../`、Backslash Pathを検査。
- Checkpoint ZIPとRC ZIPのTop-level directoryを固定。
- 内部Manifestと外部SHA-256を照合。
- `config/local.php`、実`.env`、実DB、Backup、Log、Session、Cache、入れ子ZIPを除外。
- `var/m4f-evidence/`の実結果を除外し、残っている場合はRC Builderを停止。
- Private key、AWS access key、API keyの高確度Patternを検査。
- RC ZIPは`RELEASE_CANDIDATE`、`publishable=no`であり、正式Release Artifactではない。

## Decision

```text
Automated regression       PASS
RC package                 PASS
Real environment evidence HOLD
Version 1.0.0 / v1.0.0    未実施
M4-G                       Evidence PASSまで進行不可
```
