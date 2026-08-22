# V1.19-E Release Candidate

## Scope

V1.19-EはV1.19-B/C/Dの統合互換確認とRelease Candidate package作成を行うPhaseです。新機能は追加しません。

## Gate

- Current full regression
- V1.17 / V1.17.1 / V1.17.2 compatibility
- V1.18 focused compatibility
- V1.19-B modular architecture
- V1.19-C security hardening / HTTP behavior
- V1.19-D cleanup / documentation
- V1.19-E RC contract
- Secret scan / package manifest / CRC / path safety
- Runtime RC / Complete RC build and verifier

## Version

- APP_VERSION: `1.19.0-rc1`
- APP_VERSION_LABEL: `RSS Reader Modernization 1.19.0-RC1`
- APP_ASSET_REVISION: `1.19.0-rc1`
- intended stable release: `1.19.0`
- intended stable tag: `v1.19.0`
- RC publishable: `no`

## Non-goals

- Git commit / push / tag / release
- DB migration
- New feature development
- HSTS or strict script/style CSP rollout
