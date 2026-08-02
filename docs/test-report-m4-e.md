# M4-E / R1 Test Report

## Scope

- Release package deterministic build
- Preview / RC / Final mode guard
- Runtime Release ZIP allowlist / exclusion
- internal `RELEASE_MANIFEST.sha256`
- external `.zip.sha256`
- Release Notes / Tag / GitHub Release documentation
- Checkpoint ZIP safety
- Secure Baseline、M1、M2、M4-A〜D regression

## Source tree regression

全Runnerは実行環境の1回あたりの実行枠に合わせ、M4-C healthcheckまでと、M4-D / M4-Eへ分割して完走した。

```text
前半 (M4-C healthcheckまで):  PASS 4017 / FAIL 0 / SKIP 7
M4-D + M4-E:                    PASS  558 / FAIL 0 / SKIP 0
-------------------------------------------------------------
合計:                            PASS 4575 / FAIL 0 / SKIP 7
```

## M4-E専用確認

```text
Release package builder:        PASS 43
Release documentation:          PASS 186
Release process / Tag safety:   PASS 45
```

主な確認:

- 同じSourceから2回BuildしたPreview ZIPのSHA-256一致。
- ZIP entry順、timestamp、permissionの固定。
- M4-E markerでRC / Final modeが拒否されること。
- CRC、重複entry、absolute path、parent traversal、backslash path。
- `config/local.php`、real `.env`、実DB系file、入れ子ZIPの除外。
- Runtime directoryに生成fileがないこと。
- Internal manifestのfile setと全SHA-256。
- External SHA-256 sidecarとZIP全体の一致。
- `RELEASE_BUILD.txt` の `package_status=PREVIEW`、`publishable=no`。
- Version markerとRelease metadataの一致。
- Release NotesがM4-F / M4-G前の正式公開を主張しないこと。
- annotated Tag、特定Tagのみのpush、誤Tag、公開後不変方針。
- `git reset --hard`、force-moving Tag、`git push --tags`を通常手順にしないこと。

## Preview Runtime Release ZIP

```text
Artifact: rss-reader-modernization-1.0.0-preview-m4-e.zip
Sidecar:  rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
Files:    200
Status:   PREVIEW / publishable=no
Verify:   PASS 626 / FAIL 0
```

外部SHA-256の実値は同梱する `.zip.sha256` をSource of truthとする。Test reportへHashを複製せず、Artifactとsidecarを一組で検証する。

## Syntax

```text
PHP syntax:          71 files PASS
JavaScript syntax:   10 files PASS
Python AST parse:    70 files PASS
```

## SKIP 7件

- PDO SQLite driver unavailable。
- live SimpleXML fixture parsing unavailable。
- SB-14 live parser matrix requires SimpleXML / mbstring。
- M1-A live normalized parser checks require SimpleXML / mbstring。
- M1-C live adapter matrix requires SimpleXML / mbstring。
- M1-D live identity adapter matrix requires SimpleXML / mbstring。
- Chromium runtime dependencies incomplete。

M4-Eでは実MySQL、実Feed、実Browser、Restore drillをPASSへ読み替えていない。M4-Fへ残す。

## GitHub hosted CI

M4-D commit `766f1b3aa857d1ee43134926b1465310ddbe6a08` に対するstatus / workflow runはM4-E開始時点でConnectorから確認できなかった。Workflow definitionとLocal regressionはPASSだが、GitHub Actions画面のPHP 8.1 / 8.4成功確認はHOLDを維持する。

## Result

```text
M4-E package / documentation gate: PASS
Version 1.0.0 formal release:       HOLD
Next:                               M4-F Release Candidate verification
```
