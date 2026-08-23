# V1.20-F Release Candidate

## Scope

V1.20-FはV1.20-B〜Eを正式V1.19.0 Complete Sourceへ統合し、Version 1.20.0-RC1として全体回帰とPackage Gateを実行するPhaseです。新しい機能をFで追加することは目的としません。

統合対象はCard Header Compact、RSS Typing、Wire Defense R7、全RSS新着 E1です。

## Gate

- Current Full Regression
- V1.17 / V1.17.1 / V1.17.2 compatibility
- V1.18 compatibility
- V1.19 Architecture / Security / Cleanup compatibility
- V1.20 RSS Typing / Wire Defense actual runtime helper tests
- V1.20 全RSS新着 validation / config / security contract
- PHP / JavaScript / Python syntax
- High-signal secret scan
- Public PHP endpoint / CSRF / auth / request-size boundary regression
- Runtime Production RC package build / verifier
- Complete Source RC package build / verifier
- ZIP CRC / manifest / duplicate / path traversal / generated runtime data exclusion

Historical `run-v119e.sh` / `run-v119f.sh`はV1.19.0-RC1 / V1.19.0そのものを検証する固定Release Gateなので、V1.20 compatibilityには使用せず保存します。

## Version

- APP_VERSION: `1.20.0-rc1`
- APP_VERSION_LABEL: `RSS Reader Modernization 1.20.0-RC1`
- APP_ASSET_REVISION: `1.20.0-rc1`
- intended stable release: `1.20.0`
- intended stable tag: `v1.20.0`
- RC publishable: `no`

## DB / Config

DB Table、Column、Migration、SQL、新規必須Config／Secretの追加はありません。

## Non-goals

- Git commit / push / tag / GitHub Release
- Version 1.20.0正式化
- 大規模Frontend refactor / Widget registry再設計
- HSTS / strict script-src / style-src等の別Security rollout
