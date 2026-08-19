# V1.17.2-B Test Report

実行日: 2026-08-19

## Result

V1.17.2-BのLocal Release GateはPASSです。

実X APIのCredentialそのものはAutomated Testへ登録せず、Server-side transport／fixture／status state machineを使って検証しています。実環境ではユーザー確認済みの公開Account Timeline取得をSmoke evidenceとして併用します。

## V1.17.2 focused tests

`tests/run-v1172.sh`

- V1.17.2-A X API／Cache／Validation／Security: **33 PASS / 0 FAIL**
- X Widget persistence／owner scope: **13 PASS / 0 FAIL**
- V1.17.2-B Bearer Token state: **13 PASS / 0 FAIL**
- Missing Token server/frontend block: PASS
- Malformed Token server/frontend block: PASS
- V1.17.2-B static Release Gate: **37 PASS / 0 FAIL / 0 SKIP**
- PHP syntax: PASS
- JavaScript syntax: PASS

確認対象には、`missing`／`invalid_format`／`unverified`／`verified`／`auth_failed`、401状態記録、2xx確認済み状態、Token fingerprint、Raw Token非露出、X API固定Host、Cache／stale、owner scope、1.17.2 Version／Asset revision、Release builder／workflowを含みます。

## Current regression

`tests/run-current.sh`

**PASS: current regression suite completed**

Core／Security Baseline、RSS Engine、Frontend runtime、Dashboard Widget、Feed／Search／Article Actions、Game／Clock／Mobile、Asset／Login、Stock、Information Widgetまで現行Product Contractを横断して完了しています。

実行Environmentの既知制約により、PDO SQLite、SimpleXML／mbstringを必要とする一部Live parser integration、Chromium browser smokeは既存RunnerどおりSKIPしました。代替fixture／static／runtime checksはPASSしています。

## V1.17 compatibility

`tests/run-v117.sh`

Camera / Video foundation、Snapshot、YouTube／Video、MJPEG／HLS、Auto detection／UI／Asset revisionをPASSしました。

V1.17-Eの旧Testが`1.17.1`のAsset queryを固定していたため、機能契約を変えず「現在の`APP_ASSET_REVISION`を使用する」compatibility checkへ修正し、V1.17.2でも同じStreaming契約を検証出来るようにしています。

## V1.17.1 compatibility

`tests/run-v1171.sh`

Session release、Camera／Mail／Information watchdog、Widget settings no-reload、hls.js SRI、production runtime、Release Gate compatibilityをPASSしました。

最終Release Gate compatibilityは **48 PASS / 0 FAIL / 0 SKIP** です。

## Security / packaging pre-check

- `config/local.php`: repository sourceに存在しないことを確認。
- 実Bearer Token: source／test／docsへ未収録。
- Example config: Placeholderのみ。
- 高Signal Secret pattern scan: PASS。
- X connection status Cache: Raw Tokenを保存しない。
- Browser API: Token／fingerprintを返さない。
- `var/cache/`: Runtime／Complete Release packageから除外するBuilder contractを確認。
- DB Migration: V1.17.2追加なし。

## Manual / real-service evidence

Automated Test外の実Service確認として、V1.17.2-A段階でX Developer Project／App、Pay Per Use Credit、Bearer Tokenを利用し、**指定した公開X Accountの投稿取得が成功**していることをユーザー確認済みです。

Release候補では、`CHECKLIST_FOR_USER_V1_17_2_B.md`に従い、上級者向け表示、Token状態、no-reload、YouTube／Clock非干渉、Browser Network／Secret非露出を最終Smoke確認します。

## Package verification

1.17.2 deterministic builderでRuntime／Complete ZIPを生成し、両VerifierをPASSしました。

確認項目はSHA-256 sidecar、ZIP CRC、Manifest、unsafe path、duplicate entry、Version marker、Private file／Runtime data／Secret pattern除外です。Runtime ZIPはTests／GitHub metadataを含まず、Complete ZIPも`config/local.php`、実Token、実DB、Runtime Cacheを含みません。

最終SHA-256は生成した`.sha256` sidecarとRelease Gate結果を正本とします。
