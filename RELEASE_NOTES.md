# RSS Reader Modernization 1.20.1 Release Notes

## 目的

Version 1.20.1は、正式V1.20.0をBaselineとしてDashboardの操作性と日常利用Widgetを小さく改善するMaintenance / Feature Releaseです。Card HeaderのDrag HandleとNavbarをCompact化し、Memoの高さ制御と手動Refresh、Calendar予定色とTask優先度色、Game WidgetのBlock Collapseを追加します。

## 主な変更

- **Drag Handle / Navbar Compact**: Widgetの並び替えHandleを小さな`[=]`表示へ整理しつつ、既存のDrag / Touch / Keyboard操作領域を維持。NavbarはDesktopで56pxから48pxへCompact化し、coarse pointerでは44px操作領域を維持します。
- **Memo**: Height 1 / Height 2の範囲内で本文だけを縦Scrollし、長文でCard全体が伸び続けないようにしました。Headerに対象Memoだけを再取得する手動Refreshを追加し、未保存編集がある場合は置換前に確認します。
- **Calendar color**: 通常予定へ`red / blue / green`の固定色を追加。既存予定は`blue`をDefaultとし、Task期限は既存Priorityを`high=赤 / normal=青 / low=緑`としてCalendarに反映します。
- **Block Collapse**: Game WidgetへCanvas + Vanilla JavaScriptの短時間Puzzleを追加。Break回数、Score / Combo、Chain、Stability、危険域での弱支持Blockのずれ、Mouse / Touch / Keyboard操作に対応します。Sound、外部通信、Game状態のDB保存は追加しません。
- **Release integration**: `APP_VERSION=1.20.1`、`APP_ASSET_REVISION=1.20.1`へ確定し、dynamic / fallback Asset URLも同Revisionへ統一します。

## Database / Migration

V1.20.1では既存`calendar_event`Tableへ次のColumnを1つ追加します。

```text
calendar_event_color VARCHAR(8) NOT NULL DEFAULT 'blue'
```

既存DBをV1.20.0から更新する場合は、Backup後に`database/migrations/013_v1_20_1_calendar_event_color.sql`を実行してください。SQL冒頭の`@table_prefix`は実環境の`DB_TABLE_PREFIX`と一致させます。MigrationはColumn存在確認を行い、既存予定を保持したまま未定義色を`blue`へ正規化します。

新規Install用`database/schema.sql`にはこのColumnを取り込み済みです。

## Compatibility / Security boundary

- 新規必須Config / Secret: なし
- Task schema変更: なし
- Memo schema変更: なし
- Block Collapse用DB Table / Column: なし
- Calendar色は`red / blue / green`のAllowlistのみ
- Calendar色EndpointはPOST / Authentication / CSRF / Request Size / Action Allowlist / Owner scopeを維持
- `public/.htaccess`のPublic PHP deny-by-defaultを維持し、`calendar_color_api.php`だけを明示Allowlistへ追加
- `config/local.php`、実DB、生成済み`var/`DataはDistributionへ含めません

## Version / Cache

- Application Version: `1.20.1`
- Visible label: `RSS Reader Modernization 1.20.1`
- Asset Revision: `1.20.1`
- Intended Git tag: `v1.20.1`
- Package status: `FINAL`
- Publishable metadata: `yes`

V1.20.0およびV1.20.1 A〜D3の`immutable` Cacheを再利用しないよう、正式版ではV1.20.1関連Assetだけでなく既存dynamic loader / Camera streaming fallbackも`?v=1.20.1`へ統一します。

## Release validation

V1.20.1-EではCurrent Regressionを区間実行して全Sectionを確認し、V1.17 / V1.17.1 / V1.17.2 / V1.18 / V1.19 Compatibility Gate、V1.20機能Compatibility、V1.20.1専用Gate、PHP / JavaScript syntax、secret scan、Runtime / Complete packageのCRC / manifest / path safety / SHA-256を確認しました。

A〜D3の各段階はProductionでユーザー確認済みです。Eで追加した正式Version / Cache key / schema統合は、正式Tag / GitHub Release前にProduction packageで最終確認します。

## Verification limits

Automated testではHosting固有のApache挙動、実Browserの描画感、外部Serviceの実応答までは完全再現出来ません。特に次はProduction確認を残します。

- Footerの正式Version表示とAsset cache更新
- PC / SmartphoneのDrag Handle / Navbar
- Memo Height 2 / 内部Scroll / 手動Refresh
- Calendar色の保存とMigration適用済みDBでの表示
- Task Priority色のCalendar表示
- Block CollapseのCanvas / Touch / Keyboard / Stabilityの操作感
- DevTools Console、PHP / Apache Error Log

## 更新

V1.20.0から更新する場合は、`config/local.php`、実DB、必要な`var/`DataをBackupし、`013_v1_20_1_calendar_event_color.sql`を適用した後、Production ZIPのCodeを相対Pathで上書きします。詳細は`docs/v1-20-1-production-checklist.md`を参照してください。
