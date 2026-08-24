# V1.20.1 Test Report

実施日: 2026-08-25
対象: RSS Reader Modernization 1.20.1 final source

## Result

**Release blocking failure: 0**

V1.20.1-Eでは、通常の途中Phaseで省略していた全体Regression / Compatibility / Security / Package Gateをまとめて実施しました。

## Current Regression

`tests/run-current.sh`の全Sectionを確認しました。単一Commandでは実行環境のCommand timeoutに達したため、同Scriptに記載された後半Sectionを同じCommand列で分割実行しています。

- PHP syntax: PASS
- Core / Secure Baseline: PASS
- RSS engine / fetch / cache: PASS
- Frontend runtime / asset contract: PASS
- Dashboard / Widget core: PASS
- Feed / Search / Article Actions: PASS
- Game / Clock / Mobile interactions: PASS
- Assets / Login / Grid / Calendar: PASS
- Stock / split entry points: PASS
- Information Widgets: PASS

Environment-only SKIP:

- PDO SQLite integration: driver unavailable
- SimpleXML / mbstring live parser checks: extension unavailable
- M2-F Chromium browser smoke: runtime dependencies incomplete

これらはTest failureではなく、静的/fixture/Node/PHP側の対応TestはPASSしています。

## Compatibility Gates

- V1.17 Camera / Video: PASS
- V1.17.1 Session lock / watchdog / no-reload settings: PASS
- V1.17.2 X Timeline: PASS
- V1.18 Connection Monitor / pre-release compatibility: PASS
- V1.19-B Architecture: PASS 40 / FAIL 0
- V1.19-C Security: PASS 39 / FAIL 0 + HTTP checks PASS
- V1.19-D Documentation / endpoint matrix / account compatibility: PASS 71 / FAIL 0 + dependent checks PASS

Compatibility Gate中、V1.20.0固定だった古いTest条件を3箇所更新しました。

1. V1.19 Architecture Version条件をV1.20.xへ拡張。
2. Public PHP inventoryをCalendar色Endpoint追加後の明示Whitelistへ同期。
3. Public Endpoint Matrix documentationを`calendar_color_api.php`込みの現行Inventoryへ同期。

Application behaviorをTestへ合わせて戻す変更は行っていません。

## V1.20 / V1.20.1 Gates

- V1.20 RSS Typing / Wire Defense compatibility: PASS 29 / FAIL 0
- V1.20 All RSS Recent compatibility: PASS 24 / FAIL 0
- V1.20.1-E final gate: PASS 78 / FAIL 0

V1.20.1-E Gateでは以下を確認しています。

- exact `APP_VERSION=1.20.1`
- exact `APP_ASSET_REVISION=1.20.1`
- Drag Handle `[=]` / Navbar compact contract
- Memo internal scroll / target-only manual refresh / unsaved confirmation
- Calendar `red|blue|green` allowlist / owner scope / Migration / fresh-install schema
- Public PHP deny-by-default + Calendar color endpoint explicit allowlist
- Task high/normal/low Calendar color mapping
- Block Collapse subtype / Canvas / rAF lifecycle / no interval / no network / no DB persistence
- Break / Score / Combo / Stability / pointer / keyboard / reduced-motion contract
- Dynamic asset revision alignment
- Release builder / verifier / CI / workflow / documentation contract

## Security / Source hygiene

- High-signal secret scan: PASS
- PHP syntax on changed/runtime files: PASS
- JavaScript `node --check` on V1.20.1 assets: PASS
- `git diff --no-index --check` equivalent whitespace check against formal V1.20.0 source: PASS (no whitespace errors)
- Runtime-generated session/cache/log/security files removed before package build

## Package Gate

Final package builder / verifierを実行し、次をPASSしました。

- Production Runtime ZIP deterministic build: PASS
- Complete Source ZIP deterministic build: PASS
- RELEASE / SOURCE manifest verification: PASS
- ZIP CRC / duplicate entry / unsafe path checks: PASS
- Private / legacy exact file exclusion: PASS
- `config/local.php`, real DB, runtime logs/session/cache exclusion: PASS
- SHA-256 sidecar verification: PASS

最終SHA-256値はPackage外部の`.zip.sha256` sidecarを正本とします。

## Production evidence

V1.20.1 A〜D3は段階的にProductionへ上書きし、ユーザー確認済みです。EではVersion / Asset revision / fresh-install schema / release tooling / documentationを統合しています。

正式Git commit / push / tag / GitHub Releaseは、このPackageのProduction最終確認後に別工程で行います。
