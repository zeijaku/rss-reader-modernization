# RSS Reader Modernization 1.19.0 Release Notes

## 目的

Version 1.19.0は新機能を増やすReleaseではなく、V1.18.0までの機能とデータを維持したまま、Architecture、Security、公開境界、Documentationを整理するMaintenance Releaseです。V1.19.0-RC1でFull Regressionと本番互換確認を行った後、同じ機能範囲を正式化しています。

## 主な変更

- `app/api.php`をFacade / Dispatcherとして維持し、Content、Dashboard、Account、Integrationsの4分類へ実装を分割。
- `app/dashboard_widget.php`をCoreとして維持し、Feed、Personal、Utilityの3分類へWidget実装を分割。
- 認証済みAPI requestにApplication-level 1MiB上限を追加。超過時はHTTP 413。
- RegistrationへIP単位の短時間Throttleを追加。既定は15分10試行、Block 15分。
- CSPへ`object-src 'none'`を追加。
- `public/`直下の実行可能PHPを既存7 EndpointへWhitelist化。
- Public Endpoint Matrix、Security Boundary、今後の新機能追加時Security Checklistを追加。
- Account Password FormへPassword Manager向けの非表示username hintを追加。Raw login emailを新しくSession / HTMLへ保持しない設計は維持。
- hls.js 1.6.16のSRIを実取得bytesから計算したSHA-384へ修正。

## 互換性

- DB Migration: なし
- SQL実行: 不要
- 新規必須Config / Secret: なし
- 既存`config/local.php`、DB、`var/`生成Dataはそのまま維持
- Public API Endpoint: `public/api_v1.php`のまま
- 既存API Action名: 維持
- V1.18.0のConnection Monitor / Camera / X / Mail / Widget群: 維持

## Version / Cache

- Application Version: `1.19.0`
- Visible label: `RSS Reader Modernization 1.19.0`
- Asset Revision: `1.19.0`
- Intended Git tag: `v1.19.0`

V1.18系およびV1.19.0-RC1の長期`immutable` Cacheを再利用しないため、正式版ではAsset URLを`1.19.0`へ切り替えています。

## Security rollout policy

V1.19.0では互換性を優先し、HSTSと全面的な`script-src` / `style-src` CSPは強制していません。HTTPS構成やinline / dynamic style整理を確認せず一括適用すると本番UIを壊す可能性があるため、今後の段階的Hardening対象としています。

## Release validation

V1.19.0-RC1でCurrent full regression、V1.17 / V1.17.1 / V1.17.2 compatibility、V1.18 compatibility、V1.19-B/C/D/E focused gate、Security scan、Runtime / Complete package verifierを実施しました。本番互換確認でも目立った機能問題は確認されず、Consoleで残ったPerformance warningや外部広告系通信ErrorはRSS Reader本体のRelease blockerとは判定していません。

## Verification limits

Automated regressionではPHP / Python / NodeによるDomain、HTTP、Security、Frontend contract、Package integrityを確認します。ただし次は本番Browser / Hosting環境での確認が必要です。

- 実HostingのApache / `.htaccess`挙動
- 実外部Feed / Weather / X / Mail / Camera配信元との接続
- Browser固有のMedia / HLS / CORS挙動
- Smartphone実機の操作感と表示
- 長時間Session / Remember Meの実時間経過

## 本番更新

詳細は`docs/update.md`と`docs/v1-19-f-production-checklist.md`を参照してください。`config/local.php`、実DB、生成済み`var/`Dataは上書きせず、Runtime ZIPのCodeだけを更新します。
