# RSS Reader Modernization 1.46.0

V1.46.0は、既存機能・API・Database・Security Boundaryを維持したままFrontendのlegacy couplingを段階的に減らすModernization Releaseです。Application Version / Asset Revision は `1.46.0`。V1.45.0からのDatabase Migrationと必須Config変更はありません。

## Frontend audit / scope

V1.46-AでFrontend dependency、Dashboardの責務、Dynamic asset load order、Calendar / Drawer CSS layerを監査しました。全面RewriteやFramework移行は行わず、低couplingなTask / Memo / Game / Clockから段階的に整理しています。

Bootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2、PHPMailer 7.1.1はV1.46では維持します。jQuery 4とFont Awesome 7は互換性範囲が広いため、V1.46の仕上げへ混在させず将来の専用Migrationへ分離します。

## Dashboard controller split

- Task / Memo / Game / Clock controllerを `dashboard.js` から個別fileへ分割します。
- Core → split controller → dashboardのload orderを維持します。
- Feed rendering、Drag & Drop、Calendar、Mail、Account Security等の責務は移動しません。
- API action、payload、owner scope、CSRF、validation等のBackend contractは変更しません。

## Reduced jQuery coupling

Task / Memo / Game / Clockのsplit controllerは、直接のjQuery DOM/Event操作をNative DOM/Eventへ移行します。

Shared Dashboard transportは、既存のCSRF token rotation、401 unauthenticated handling、API error contractを保持するためV1.46では `$.ajax()` を維持します。その上にNative controllerから利用するPromise / request-pending adapterを追加しています。

jQuery 4への更新は行いません。

## Task partial refresh

Task Itemのcreate / update / toggle / deleteは、成功後にPage全体をreloadせず、対象Task Widgetだけをowner-scoped `widget.list` で再取得して描画します。

Task Widget自体のcreate / update / deleteは、Cardのlayout / styleが変わり得るため従来どおりPage reloadを維持します。

Task title等の描画はDOM `textContent` を使用し、HTMLとして解釈しません。

## Clock sizing

Desktop上で1列幅のClock Widgetが狭い場合に、viewport基準のfont-sizeによって午前/午後や秒がCard外へ切れる問題を修正します。

対応BrowserではClock viewの実幅を基準にしたcontainer-relative sizingを使用し、未対応Browserでは保守的なfallback sizeを使用します。

## hls.js 1.7.3 / local vendoring

- hls.jsを1.6.16から1.7.3へ更新します。
- 公式v1.7.3 Release artifactの `dist/hls.min.js` をRepositoryへ同梱します。
- HLS Widget使用時だけlazy loadする既存構成を維持します。
- jsDelivrから実行JavaScriptを取得しません。
- `Hls.isSupported()`、Manifest/Error event、Network / Media recovery、Native HLS fallback、destroy lifecycleを維持します。
- autoplayは追加しません。
- 同梱artifactのSHA-256をRepositoryで固定・検証します。

YouTube iframeでFirefoxが表示するFeature Policy / third-party Cookie / Dynamic State Partitioning / fingerprinting protection等のwarningは、hls.js loaderやRSS Reader SessionのErrorではありません。

## CSS consolidation

Current runtimeで後付けPatchとなっていたCSSを統合します。

- Calendar: former `calendar-polish-r3.css` → `calendar-polish.css`
- Drawer: former visual / mobile layer → `drawer-catalog.css`
- Drawerの旧cascade順（visual → mobile/touch → catalog）は統合後も維持します。
- Calendar deadline、Bootstrap / Bootswatch Theme等の独立CSS behaviorは変更しません。

Final cleanupでは次のobsolete fileをCurrent sourceから削除します。

- `public/css/calendar-polish-r3.css`
- `public/css/drawer-v121b.css`
- `public/css/drawer-v121c.css`
- `licenses/hls.js-1.6.16-Apache-2.0.txt`
- `licenses/fontawesome-5.3.1-LICENSE.txt`

Historical test / one-time release tool内に過去Versionのfile名・SRI値が資料として残る場合がありますが、Current runtime / Current regressionはそれらを読み込みません。

## Database / configuration

- Database Migration: なし。
- 新規Table / Column: なし。
- 必須Config変更: なし。
- Public APIの破壊的変更: なし。
- Fresh InstallはCurrent `database/schema.sql` を使用します。
- 既存Databaseへ `schema.sql` を再実行しません。

## Security / compatibility

V1.46はFrontend整理であり、次の既存Boundaryを変更しません。

- authenticated Session / owner scope
- POST + CSRF mutation
- PDO parameter binding
- SSRF / outbound request boundary
- output escaping / XSS対策
- TOTP 2FA / Remember Me / Session registry
- Mail OAuth / Remote Files credential handling

hls.jsをsame-origin vendoringしたことで、HLS Player起動時の外部Executable JavaScript dependencyは減少します。

## Verification limits

- PHP 8.1 / PHP 8.4 Current regressionを各Phase、main merge、Release-ready sourceで確認します。
- hls.js official Release ZIP digest、vendored artifact SHA-256、exact Version guard、lazy-load retry、Native fallback、Network / Media recovery contractをFocused Testで検証します。
- Calendar / Drawer CSS consolidationはCurrent contractで旧runtime requestが消えていることと必要selector / cascadeを検証します。
- User Browser TestではTask Item部分更新、Clockの狭幅表示、D2後のDashboard / Utility / Video画面を確認しています。
- 実際のHLS Stream再生可否は配信元URL、CORS、Codec、Browser MSE / Native HLS能力にも依存します。
- YouTube等Third-party iframeのConsole warningはBrowser Privacy Policyによるものがあり、RSS Reader Application Errorとは区別します。
- Production DeploymentはGitHub Release公開とは別工程です。

## Release

Tag: `v1.46.0`

Runtime package: `rss-reader-modernization-1.46.0.zip`

Complete Source package: `rss-reader-modernization-1.46.0-complete.zip`
