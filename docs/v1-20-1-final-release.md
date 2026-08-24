# V1.20.1-E Final Release

A〜D3で確認した変更を正式`1.20.1`へ統合します。Eでは新機能を追加せず、Version / Cache / Fresh-install schema / Test / Package / Documentationを揃えます。

- `APP_VERSION=1.20.1`
- `APP_VERSION_LABEL=RSS Reader Modernization 1.20.1`
- `APP_ASSET_REVISION=1.20.1`
- intended tag: `v1.20.1`
- DB変更: `calendar_event.calendar_event_color` 1Column
- Migration: `013_v1_20_1_calendar_event_color.sql`
- 新規必須Config / Secret: なし
- Widget下端完全統一: 保留

## Release Gate結果

- Current Regression: PASS（実行環境依存のPDO SQLite / SimpleXML・mbstring / Chromium smokeはSKIP）
- V1.17 / V1.17.1 / V1.17.2 / V1.18 / V1.19 Compatibility: PASS
- V1.20 compatibility: PASS
- V1.20.1-E final gate: PASS 78 / FAIL 0
- High-signal secret scan: PASS
- Runtime / Complete package build & verifier: PASS

正式Git登録はProduction package確認後の別工程とします。
