# V1.20-G Final Test Report

Date: 2026-08-23
Target: `RSS Reader Modernization 1.20.0`

## Result

V1.20-F RC1の本番確認後、機能コードは変更せずVersion／Asset revision／Release metadata／Package tooling／CI・Release Gateを正式`1.20.0`へ昇格しました。Final SourceでRegression、Compatibility、Security／Syntax、Package検証を再実行し、Release blockerは検出されませんでした。

## V1.20-G Final Gate

- Static / Integration final contract: 74 PASS / 0 FAIL
- RSS Typing / Wire Defense runtime helper: 29 PASS / 0 FAIL
- All RSS Recent PHP behavior: 24 PASS / 0 FAIL
- Total V1.20-G focused assertions: 127 PASS / 0 FAIL

## Full regression / compatibility

- `tests/run-current.sh`: PASS / exit 0 / full completion
- `tests/run-v117.sh`: PASS
- `tests/run-v1171.sh`: PASS
- `tests/run-v1172.sh`: PASS
- `tests/run-v118.sh`: PASS
- `tests/run-v119.sh`: PASS
- `tests/run-v119c.sh`: PASS
- `tests/run-v119d.sh`: PASS

Historical exact-version release gates for older releases remain preserved but are not rewritten to accept V1.20.0. V1.20正式CIはV1.19 compatibilityと`tests/run-v120g.sh`を使用します。

## Source / security gate

- PHP syntax: 163 files PASS
- JavaScript syntax: 45 files PASS
- Python compile: PASS
- GitHub Actions YAML: 8 workflows PASS
- High-signal secret scan: 0 hits
- V1.20 DB migration: none
- Merge conflict marker: none
- V1.19.0 baselineからのchanged-source whitespace check: PASS
- Apache syntax: `Syntax OK`（環境固有のServerName warningのみ）

## Package preflight

### Production Runtime

- Artifact: `rss-reader-modernization-1.20.0.zip`
- Package status: `FINAL`
- Publishable: `yes`
- Files: 561
- Runtime package verifier: 1,714 checks PASS
- Private config / DB / runtime / tests / GitHub metadata: excluded

### Complete Source

- Artifact: `rss-reader-modernization-1.20.0-complete.zip`
- Package status: `FINAL`
- Publishable: `yes`
- Files: 1,164
- CRC / top-level path / unsafe path / private files / generated runtime exclusion: PASS
- `SOURCE_MANIFEST.sha256`: all payload entries verified

Final Test ReportをSourceへ固定した後、Production／Complete Packageを再Buildし、Verifier、再展開後Gate、deterministic rebuildを再確認して最終SHA-256を確定します。最終SHA-256は配布sidecarとV1.20-G handoff資料を正本とします。

## Release boundary

- DB Table / Column change: none
- Migration / SQL execution: none
- New required configuration / secret: none
- Public endpoint remains `public/api_v1.php`
- V1.20-E adds All RSS Recent API actions inside the existing authenticated/CSRF-protected dispatcher
- V1.20-G itself adds no functional behavior beyond final Version / Cache / Release infrastructure promotion
