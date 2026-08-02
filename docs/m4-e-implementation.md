# M4-E / R1 Release ZIP・Manifest・Release Notes・Tag手順

## 目的

Version 1.0.0の正式公開前に、配布用Runtime ZIPをCheckpoint ZIPから分離し、再現可能なBuild、内部Manifest、外部SHA-256、Release Notes、Tag / GitHub Release手順を準備する。

M4-EはPackaging工程であり、Application機能、DB、公開API、Security境界、Frontend Runtime Assetを変更しない。

## 実施内容

- `RELEASE_NOTES.md` の1.0.0準備版を追加。
- `tools/build_release_package.py` を追加し、preview / rc / final modeを分離。
- ZIP entry順、timestamp、permissionを固定し、同一Sourceから同じSHA-256になるBuildを追加。
- Runtime Release ZIPのallowlistを定義。
- `RELEASE_BUILD.txt` と `RELEASE_MANIFEST.sha256` をPackage内へ生成。
- ZIP全体の `.zip.sha256` sidecarを生成。
- `tools/verify_release_package.py` でCRC、Path、Manifest、Version、Secret、禁止fileを検証。
- Tag `v1.0.0` とGitHub Releaseの安全な作成手順を追加。
- M4-E preview Artifactを生成し、正式公開不可をmetadataとRelease Notesへ明記。
- M4-E専用testでdeterministic build、negative mode、Release documentation、Tag safetyを確認。

## M4-E preview

M4-Eでは次を作成する。

```text
rss-reader-modernization-1.0.0-preview-m4-e.zip
rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
```

PreviewはPackage layoutと検証方法の証拠であり、`publishable=no` としてGitHub Releaseへ公開しない。Application markerは `M4-E R1` のまま保持する。

## 正式Releaseとの分離

- M4-F: `1.0.0-rc1`等のVersion markerで実環境を含むRC確認。
- M4-G: exact `1.0.0`、最終Release Notes、正式ZIP、Tag `v1.0.0`。

Builderのfinal modeはexact 1.0.0 markerでなければ停止するため、M4-Eから正式Packageを誤生成しない。

## GitHub hosted CI

M4-D commitに対するstatus / workflow runはM4-E開始時点でConnectorから確認できなかった。Workflow定義とLocal testはPASSしているが、GitHub hosted CIは利用者がActions画面で確認するまでHOLDを維持する。

## 変更していない範囲

DB schema / Migration、Public API、Authentication、Authorization / owner scope、Session、CSRF、SSRF、XSS、Validation、RSS / Atom、Cache / Retry、Item identity、Feed CRUD、Stock、Settings、Frontend Runtime Asset、Runtime設定項目は変更していない。
