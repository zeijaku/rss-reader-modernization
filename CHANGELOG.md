## 1.29.0 - 2026-09-01

### Remote File Manager
- Added authenticated owner-scoped Remote File Manager support for FTP, explicit FTPS, SFTP and HTTPS WebDAV.
- Added connection registration/test, directory navigation/listing, upload/download, mkdir, rename/move, delete and refresh.
- Added Remote -> File Library and File Library -> Remote transfer paths plus bounded Image/PDF/TXT/CSV preview reuse.
- Grouped new backend code under `app/remote_file/` and kept public Remote endpoints explicitly allowlisted.

### Security / deployment
- Added Migration `021_v1_29_remote_connection.sql` for owner-scoped Remote connection metadata and an authenticated-ciphertext credential envelope.
- Added Sodium XChaCha20-Poly1305 AEAD credential encryption with owner/connection-bound AAD; the encryption key remains outside the database and repository.
- Added host/port validation, DNS answer validation/pinning, DNS-rebinding controls, public-IP default policy, explicit administrator CIDR allowlist for private networks, and permanent loopback/link-local denial.
- Added Base Path confinement, traversal rejection, bounded transfer/temp storage, symbolic-link/unknown-entry fail-closed checks where server metadata permits, and same-origin/base-path WebDAV redirect validation.
- SFTP requires verified `known_hosts`; FTPS/WebDAV keep peer/hostname TLS verification enabled. Plain FTP remains visibly marked as unencrypted.
- Added `tools/remote_file_env_check.php` to validate cURL protocol capability, SimpleXML/Sodium/OpenSSL availability, credential-key shape and private temp-directory readiness without printing secrets.

### Finalization
- Hardened password/private-key form serialization and client-side required-credential checks after production checkpoint testing.
- Finalized `APP_VERSION`, visible label, active asset revision and dynamic V1.29 asset keys at `1.29.0`.
- Added V1.29 Remote File Manager contracts to the durable current feature suite and retained the generic PHP 8.1/8.4 CI/Release gates.

# Changelog

## 1.28.0 - 2026-08-31

- Extended the authenticated V1.27 File Library without changing its metadata-only database schema or private-storage boundary.
- Added File Detail for original filename, MIME, extension, size, upload time, numeric file id, and image dimensions when available; stored physical names, owner ids, and filesystem paths are not returned.
- Added protected PDF preview for validated PDF files using the browser-native viewer; no PDF.js, CDN dependency, server PDF parser, or arbitrary path input is added.
- Added UTF-8 TXT Preview bounded to 64 KiB and 300 lines; UTF-8 BOM is accepted and invalid encoding fails closed while full download remains available.
- Added UTF-8 CSV Preview bounded to 512 KiB, 50 data rows, 30 columns, and 64 KiB per logical record using bounded `fgetcsv` parsing.
- Rendered TXT/CSV dynamic content as text rather than HTML and kept preview/detail access authenticated, owner-scoped, private-path resolved, and revalidated at serve time.
- Polished File Library actions and Modals for Smartphone/narrow layouts, including touch-friendly four-action 2x2 presentation and long filename/metadata wrapping.
- Removed development phase badges from current File Library/RSS Management UI while retaining the central application version marker.
- ZIP remains download-only and is never opened, extracted, or executed by the application.
- No V1.28 database migration, schema change, new required secret, environment variable, or permission change is introduced.
- Finalized application and active public asset revision markers at 1.28.0.

## 1.27.0 - 2026-08-30

- Expanded article URL tracking-parameter cleanup while leaving registered Feed URLs unchanged.
- Normalized remaining Dashboard header/touch targets without changing the established grid or drag-and-drop model.
- Added authenticated secure file upload backed by private `var/uploads/` storage and owner-scoped metadata.
- Added the `/file-library` page with 24-item pagination, responsive cards, thumbnails, download, delete, upload progress, and drag-and-drop file selection.
- Added an in-page Bootstrap Image Viewer that uses the existing authenticated owner-scoped content endpoint rather than exposing physical paths.
- Default per-file upload limit is 10 MiB; allowed types are JPEG, PNG, GIF, WebP, PDF, TXT, CSV, and ZIP.
- Browser MIME is not trusted. Server Fileinfo plus image/content/signature validation is authoritative; physical names use 256-bit random values.
- ZIP files are stored/downloaded only and are never extracted or executed by the application.
- Added Migration `020_v1_27_user_files.sql`; the fresh-install schema contains the same metadata-only `user_file` table contract.
- Preserved authentication, CSRF, owner scope, deny-by-default public PHP endpoints, `nosniff`, same-origin resource policy, restrictive CSP, and private-path non-disclosure.
- Finalized application and public asset revision markers at 1.27.0.

## 1.26.0 - 2026-08-30

- Added the Dashboard Information Board Widget for All RSS or a specific owner-scoped Feed.
- NEWS and the current article title remain fixed while only the sanitized RSS summary scrolls right-to-left.
- Added 5/10/20 item limits, slow/normal/fast speed, summary ON/OFF, previous/next navigation, source/date/count footer, NEXT preview, and summary progress.
- Uses existing RSS description/content only, bounded by the existing 4096-character RSS safety ceiling; no article-page scraping or secondary article fetch is added.
- Preserved reduced-motion, hover/focus/touch/page-hidden pause behavior, and aligned the Information Board header with the existing 44px Dashboard header/touch targets.
- Finalized application and asset revision markers at 1.26.0.
- Existing authentication, authorization, CSRF, owner-scope, SSRF-safe Feed retrieval, and text-sanitization boundaries are preserved.
- No database migration or new required secret/configuration is introduced.

## 1.25.0 - 2026-08-28

### Calendar event details
- Extended the existing Calendar event model with all-day/timed scheduling, optional start/end time, and an optional related HTTP/HTTPS URL without introducing server-side URL fetching.
- Kept existing events all-day by default and retained the existing Calendar / Task separation.
- Reused the existing Calendar create/edit transaction path and color handling rather than adding a parallel Event implementation.

### Recurrence
- Added `none` / `daily` / `weekly` / `monthly` / `yearly` recurrence with optional repeat-until date.
- Kept V1.25 recurrence edits/deletes at series level and intentionally deferred per-occurrence exceptions.
- Added explicit owner scope and recurrence resource bounds, including active-series and month-expansion limits.

### RSS / Stock to Calendar
- Added `Calendarへ追加` to the shared article actions menu for RSS and Stock.
- Article title and URL pre-fill the existing Calendar registration modal without auto-saving.
- Calendar creation does not change processed / important / archived / Stock解除 state and does not create a hard relation to the source item.

### Today / upcoming / Smartphone
- Polished the existing Today flow, current-day emphasis, focus behavior, and Smartphone Calendar layout.
- Added a server-derived 14-day upcoming list bounded to eight events, with three items shown initially and `もっと見る` / `閉じる` controls.
- Corrected Calendar modal focus handling so a focused descendant is blurred before Bootstrap hides the modal and focus is restored only after it is fully hidden.
- Reduced month-switch layout shift by temporarily holding the current Calendar grid height while asynchronous redraw completes.

### Database / Security
- Added `018_v1_25_calendar_event_time_url.sql` for all-day/time/URL columns and `019_v1_25_calendar_recurrence.sql` for recurrence columns; existing V1.24 installations apply them in numeric order after backup.
- Integrated Migrations 018/019 into the fresh-install schema together with the existing integrated 013/017 state.
- Calendar recurrence/upcoming operations remain authenticated POST + CSRF + request-size limited, fixed-action allowlisted, and owner-scoped.
- Calendar URLs are stored/validated only; no new SSRF fetch path, external Calendar credential, reminder scheduler, or required secret was added.

### Release verification
- Promoted V1.25 B-F/R3 Calendar contracts into the current CI/release feature suite.
- Finalized `APP_VERSION`, visible label, `APP_ASSET_REVISION`, and all V1.25 staged Calendar asset keys to `1.25.0`.
- Formal release uses the generic full regression, compatibility, security, version/dependency hygiene, deterministic package, clean-room, and secret-scan gates before tag publication.

## 1.24.0 - 2026-08-27

### Memo
- Kept long Memo content inside the selected Dashboard Widget Height so only the Memo body scrolls instead of enlarging the Grid row.
- Added live `current/4000` character counters to Dashboard Memo, register, and edit UI while preserving the existing 4000-character server validation limit.

### Stock state workflow
- Added independent `stock_processed`, `stock_important`, and `stock_archived` states while keeping `stock_flag` dedicated to Stock解除.
- Added owner-scoped state list/update controls for 未処理／処理済み, 通常／重要, and Archive／Archive済み.
- Default Stock list now excludes archived rows; processed, important, and Archive filters coexist with existing text search, Stock Tags, sorting, and pagination.
- Added current-page selection and bulk state updates for processed/unprocessed, important/normal, and archive/unarchive. Bulk Stock解除 is intentionally not added.
- Added responsive Smartphone presentation and retained touch-friendly controls.

### Database / Security
- Added `017_v1_24_stock_state.sql` for existing installations and integrated the same Stock state columns/index into the fresh-install `database/schema.sql`.
- State API operations remain authenticated POST + CSRF, owner-scoped, active-Stock-only, fixed-state allowlisted, and transactionally all-or-nothing for bulk requests.
- Bulk submitted IDs are positive integers, deduplicated, and capped at 100 raw IDs; mixed-owner/unavailable requests do not partially update.
- Invalid Stock filter values are converted to fixed safe defaults rather than interpolated into SQL.
- No new required secret or external API credential is introduced.

### Release verification
- Promoted V1.24 Memo and Stock feature contracts into the current CI/release feature suite.
- Finalized `APP_VERSION`, visible label, and immutable public asset revision to `1.24.0`.
- Formal release uses the generic full regression, security, deterministic package, clean-room, and secret-scan gates before tag publication.

## 1.23.0 - 2026-08-27

### Repository / Documentation
- Removed transient root-level checkpoint handoff documents from the current tree while retaining historical evidence in Git history and release tags.
- Documented the current documentation policy and kept Runtime package scope from expanding through a new archive directory.

### Version / Test maintenance
- Added a shared current-version contract reader and removed current-following test assertions that froze `APP_ASSET_REVISION` to Version 1.22.0.
- Kept feature compatibility gates while removing historical finalization gates from Current CI.
- Added guards against stale current asset keys and version-specific workflow regression.

### GitHub Actions / Release flow
- Reduced active GitHub Actions to `ci.yml` and the generic `release.yml`; Version-specific historical workflows remain available through Git history and release tags.
- Added a manual final Release workflow that accepts explicit `X.Y.Z`, requires release-ready `main`, rechecks the remote `main` SHA before publication, and refuses to overwrite an existing tag on another commit.
- Existing GitHub Releases are left unchanged on rerun.

### Package / Verification
- Parameterized Runtime and Complete Source package builders/verifiers with explicit `--release X.Y.Z` instead of hardcoded release constants.
- Retained deterministic ZIP generation, SHA-256 sidecars/manifests, private/runtime file exclusion, high-signal secret scan, and clean-room package checks.
- No database schema, migration, public API, application feature, UI, or new required configuration/secret changes are introduced in Version 1.23.0.

## 1.22.0 - 2026-08-26

### RSS Management / OPML
- Added `/rss-management` with an RSS list and OPML Import / Export for the authenticated user.
- OPML import validates XML locally without fetching imported URLs, limits size/feed count/depth, rejects DOCTYPE / ENTITY, and preserves optional feed title, site URL, and category path metadata.
- Existing feed fetches may fill a blank metadata title from the successfully parsed channel title without an extra outbound request.

### Feed Health
- Added per-feed health state derived from owned content: last check / success, latest article date, HTTP result, failure reason/count, redirect state, and effective URL.
- Manual recheck reuses the stored owned feed URL and the existing SSRF-safe feed pipeline; arbitrary request URLs are not accepted.

### RSS Rules
- Added owner-scoped RSS Rules with ordered conditions and explicit match mode / action.
- Integrated server-evaluated article actions for Highlight, Hide, Stock, and Task while retaining existing Article Actions and ownership boundaries.
- Rule condition rows do not duplicate user ownership; ownership is derived from the parent rule.

### Database / Security
- Added `014_v1_22_opml_feed_metadata.sql`, `015_v1_22_feed_health.sql`, and `016_v1_22_rss_rules.sql`. Existing databases apply them in numeric order after backup.
- No new required secret or external API credential is introduced.
- Public API authentication, POST/CSRF/request-size/action validation, owner scope, and SSRF-safe feed fetching remain in place.

### Release verification
- V1.22-A/B/C focused gates and V1.22-D integration gate are retained.
- V1.22-E adds the formal 1.22.0 contract, PHP 8.1 / 8.4 regression, historical compatibility gates, source secret scan, deterministic Runtime / Complete Source package verification, and clean-room checks before tag publication.

## 1.21.0 - 2026-08-25

### Drawer / Navigation
- Reorganized the Drawer into DISPLAY, FEED, PRODUCTIVITY, INFORMATION, MEDIA, GAME, SETTINGS, USER LINKS, and ACCOUNT without rebuilding existing actions.
- Kept Mail in PRODUCTIVITY and Camera / Video in MEDIA while preserving their existing dynamic insertion and feature implementations.
- Preserved configured user links for Smartphone while keeping the existing PC Navbar presentation.

### Visual hierarchy
- Added a restrained light-gray Drawer surface, clearer section headers, compact icon tiles, and more visible hover / focus states.
- Kept a single blue Current indicator and removed the similar blue section-header marker after Production review.
- Kept Logout in a restrained Danger treatment.

### Smartphone / Touch
- Kept 44px touch targets, improved Drawer scrolling and dynamic viewport / safe-area handling, and prevented long labels from causing horizontal overflow.
- Kept tall Modals within the Smartphone viewport without changing the existing Offcanvas-to-Modal lifecycle.
- Moved the RSS / Information Widget Catalog accordion chevron slightly inward from the right edge for easier touch operation.

### Compatibility / scope
- Bootstrap 5 Offcanvas and the existing jQuery-assisted behavior remain in place; no unrelated JavaScript modernization was introduced.
- No database schema or migration changes are required for Version 1.21.0.
- No `config/local.php` changes are required.
- File Upload / File Library / Image Viewer, Imgur Widget, and whole-grid Height 2 alignment remain deferred.

### Verification
- V1.21-A/B/C focused and compatibility tests were completed during development.
- Version 1.21.0 finalization runs the full current regression suite, compatibility gates, source secret scan, package verification, and clean-room package checks before release publication.

## RSS Reader Modernization 1.20.1 — 2026-08-25

### Dashboard compact / Memo refresh / Calendar color / Block Collapse

- V1.20.1-A: Widget Drag HandleをCompactな`[=]`表示へ整理し、Runtimeで注入される旧`::before`枠を抑止。Drag / Touch / Keyboard reorderの操作領域は維持。NavbarをDesktop 56px→48pxへCompact化し、coarse pointerの44px操作領域を維持。
- V1.20.1-B: MemoのHeight 1 / 2範囲内で本文だけをScrollさせ、長文によるCard全体の過伸長を抑制。対象Memoだけを`widget.list`で再取得する手動Refreshと未保存編集確認を追加。
- V1.20.1-C: Calendar予定へ`red / blue / green`を追加し、既存予定は`blue`をDefault化。Task期限は既存Priorityを`high=赤 / normal=青 / low=緑`で表示。既存DB向けMigration `013_v1_20_1_calendar_event_color.sql`を追加し、新規Install用`database/schema.sql`にも同Columnを統合。
- V1.20.1-C2: Calendar色専用EndpointをPublic PHP deny-by-default Matrixへ明示追加。POST / Authentication / CSRF / Request Size / Action Allowlist / Owner scopeを維持。
- V1.20.1-D: Game WidgetへBlock Collapseを追加。Canvas + Vanilla JavaScriptでBreak制限、Score / Combo、Chain、Stability、危険域の弱支持Blockずれ、Mouse / Touch / Keyboard操作へ対応。Sound / Network request / Game状態DB保存は追加しない。
- V1.20.1-E: `APP_VERSION` / Label / Asset Revisionを`1.20.1`へ確定し、dynamic Asset cache keyとfresh-install schemaを統合。Current / Compatibility / V1.20.1 Gate、Security / Syntax / Package検証を実施。
- Widget下端の完全統一はDashboard Grid全体へ影響するためV1.20.1では保留し、将来のLayout改善へ分離。
- 新規必須Config / Secretはなし。DB変更は`calendar_event_color`Column 1つのみ。

## RSS Reader Modernization 1.20.0 — 2026-08-23

### Card Header Compact / RSS Typing / Wire Defense / All RSS Recent

- V1.20-B: Dashboard Widget Headerを40pxへCompact化。通常RSS／Search Feedは`thead`内の各Layerも40pxへ揃え、既存操作領域を維持。
- V1.20-C: 通常RSS Cardへ60秒のRSS Typingを追加。Japanese IME、Score／Best、hidden-tab pause、Browser storage fallbackに対応し、Search Feedは対象外。
- V1.20-D: Wire Defenseを追加。六角形＋Server風CORE、クリック地点へのinterceptor missile、着弾爆発／chain、1秒reload gauge、Lives別の緑→Orange→赤、straight／curve／wave routeを実装。
- V1.20-E: 「全RSS新着」を追加。所有RSSだけを既存FeedFetchService経由で取得し、重複source／記事を除外してpublication date順に集約。5／10／20／30件表示に対応。
- V1.20-F: 正式v1.19.0 Complete SourceへB〜Eを統合し`1.20.0-RC1`としてFull Regression／Compatibility／Security／Package Gateを実施。本番環境で主要機能を確認。
- V1.20-G: `APP_VERSION`、Label、Asset Revisionを`1.20.0`へ確定し、Final Package tooling／Release Gate／Documentationを正式版へ昇格。
- DB Table／Column、Migration、SQL、新規必須Config／Secretの追加変更はなし。

## RSS Reader Modernization 1.20.0-RC1 — 2026-08-23

### Card Header Compact / RSS Typing / Wire Defense / All RSS Recent / RC integration

- V1.20-B: Dashboard Widget Headerを40pxへCompact化。通常RSS／Search Feedは`thead`内の各Layerも40pxへ揃え、記事本文・Article Actions等の既存操作領域は維持。
- V1.20-C: 通常RSS CardへRSS Typingを追加。表示済みRSS titleを使う60秒Game、Japanese IME、Score／Best／Round／Miss、hidden-tab pause、localStorage→sessionStorage→memory fallbackに対応。Search Feedは対象外。
- V1.20-D: GameへWire Defenseを追加。COREへ向かうpacketをinterceptor missileで防衛し、1秒Reload gauge、Lives別CORE palette、straight／curve／wave route、Best／Max ChainをBrowser側へ保存。Sound／Network requestは追加しない。
- V1.20-E: RSS Catalogへ「全RSS新着」を追加。所有RSSだけを既存FeedFetchService経由で取得し、重複source／記事を除外してpublication date順に集約。5／10／20／30件表示。既存Search Feedの`dashboard_widget` schemaを再利用しDB Migrationを追加しない。
- V1.20-F: B〜Eを正式v1.19.0 Complete Sourceへ統合し、`APP_VERSION=1.20.0-rc1`、`APP_ASSET_REVISION=1.20.0-rc1`へ切替。動的Asset loaderもRC revisionへ統一。
- Current Full Regression、V1.17／1.17.1／1.17.2／1.18／V1.19 Architecture・Security互換Gate、V1.20専用Game／全RSS新着Test、Syntax／Secret scan／Package integrityをRelease Candidate Gateとして実施。
- DB Table／Column、Migration、SQL、新規必須Config／Secretの追加変更はなし。RCは`publishable=no`で、正式Tag／GitHub Releaseは作成しない。

## RSS Reader Modernization 1.19.0 — 2026-08-22

### Architecture / Security / Documentation Maintenance Release

- `app/api.php`と`app/dashboard_widget.php`をFacade/Coreとして残し、API 4分類・Dashboard Widget 3分類へ責務単位で最小分割。既存API Action / Function contract、DB、公開Endpointを維持。
- Registration IP throttle、Authenticated API request-size guard、CSP `object-src 'none'`、Public PHP endpoint whitelistを追加。
- hls.js 1.6.16のSRI値を実CDN bytesからSHA-384計算して修正し、V1.19 final Asset Revisionを`1.19.0`へ確定。
- Architecture、Public Endpoint Matrix、Deployment/Security Boundary、新機能追加時Security ChecklistをDocumentation化。
- Account Password Formへ非表示`autocomplete="username"`補助Fieldを追加し、BrowserのPassword Form構造警告をCleanup。Raw login emailの保存・表示は追加しない。
- V1.19.0-RC1でCurrent full regression、V1.17～V1.18 compatibility、V1.19 focused/security/package gateを実施し、本番互換確認で目立った機能問題がないことを確認して正式化。
- `APP_VERSION=1.19.0`、`APP_VERSION_LABEL=RSS Reader Modernization 1.19.0`、`APP_ASSET_REVISION=1.19.0`へ確定。
- DB Migration、SQL、新規必須config/secret、主要機能追加はなし。

## RSS Reader Modernization 1.19.0-RC1 — 2026-08-21

- V1.19-B: `app/api.php`と`app/dashboard_widget.php`をFacade/Coreとして残し、API 4分類・Dashboard Widget 3分類へ責務単位で分割。Action名、DB、公開Endpointは維持。
- V1.19-C: 認証済みAPIへ1MiBのrequest size guard、RegistrationへIP単位Throttle、CSPへ`object-src 'none'`、`public/`直下PHPへ明示Whitelistを追加。
- V1.19-C follow-up: hls.js 1.6.16のSRIをBrowser実取得bytesからSHA-384計算して修正。
- V1.19-D: Architecture / Public Endpoint Matrix / Security Boundary / Security Checklistを文書化し、Account Password FormのPassword Manager向けusername hintを追加。
- V1.19-E: `APP_VERSION=1.19.0-rc1`、`APP_ASSET_REVISION=1.19.0-rc1`へ切替。V1.18までの回帰とV1.19 focused/security/package gateをまとめて実行するRelease Candidate工程へ移行。
- DB Migration、SQL、新規必須config/secret、主要機能追加はなし。

## RSS Reader Modernization 1.18.0 — 2026-08-20

### Connection Monitor / latency history / outage state / Release Gate

- DashboardのInformationカテゴリへConnection Monitor Widgetを追加し、Browser／DeviceからこのRSS Reader自身への接続状態を可視化。任意URLや第三者Monitorではなく、同一Originの`connection_probe.php`だけを測定対象とする。
- 軽量GET ProbeはHTTP 204・empty body・no-storeで返し、Session／DB／Application bootstrap／外部通信を通さない。GET以外は405とし、Client IPやLocal IP等の収集は行わない。
- Foregroundでは約5秒間隔で前回Request完了後に次回を予約し、Request overlapを防止。Background tabでは定期Probeを停止し、表示復帰時に即時確認する。
- Connection Monitorを複数配置してもPage内で1本のProbe streamを共有し、Widget数に応じて通信量が増えないようにする。
- 現在Latencyに加え、30秒／60秒／5分のIn-memory履歴、SVG Graph、Avg、Max、HTTP RTT差分を使ったJitter表示を追加。Offline／長時間空白を巨大Latencyとして扱わず、GraphとJitter計算を分断する。
- 2回連続の到達不能でOfflineを確定し、Last Disconnect、進行中Downtime、復旧後Last Downtime、約15秒のRecovered表示を追加。HTTP 500等は到達不能と混同せずProbe Errorとして分離する。
- 品質判定をExcellent（79ms以下）／Good（80–149ms）／Fair（150–299ms）／Slow（300ms以上）／Offlineへ整理。直近5分の成功値中央値をBaselineとして、十分な差が2回連続した場合だけ「通常より遅い」を表示する。
- PC／TabletのHeight 1は主要情報を残したCompact表示、Height 2はBaseline／経路／端末判定を含む詳細表示とし、SmartphoneではHeight差による情報欠落を避ける。Bootstrap／Bootswatch Theme変数へ追従する表示へ調整。
- 履歴、Baseline、切断状態はBrowser memoryだけに保持し、DB／localStorage／sessionStorageへ永続化しない。
- V1.18-Fで外部Internet Probe、固定Google／Cloudflare等へのProbe、任意Probe URL、Speed Test、WebRTC等によるIP探索をV1.18の非対象として固定。
- `APP_VERSION`／`APP_VERSION_LABEL`は`1.18.0`へ確定。実機確認後のCalendar／Dashboard CSS修正を確実に取得させるため、最終Asset Cache keyは`APP_ASSET_REVISION=1.18.0-r2`とし、動的Asset loaderも同じRevisionへ統一。Runtime／Complete package builder・verifier、CI／Release Gate、Release Documentationは1.18.0を対象とする。
- Release前確認で、長時間放置後にRemember MeからSessionを自動復旧した際、開いたままのPageが旧CSRF Tokenを保持してAPI更新が403になるCaseを修正。復旧時だけ旧Tokenを短時間Graceとして受け入れ、API Response Headerから新TokenへPage側を同期する。Remember Meが無効で認証自体が失効した場合は通常のLogin画面へ戻す。
- Smartphone Calendarが`min-width: 500px`を強制してCard幅を超えるCaseを修正。575.98px以下では7列GridをCard幅へ収め、Desktopの狭いCalendarは必要な横OverflowをCard内へ閉じ込める。
- DB Table／Column／Migration、必須config、外部JavaScript Libraryの追加変更はなし。

## RSS Reader Modernization 1.17.2 — 2026-08-19

### X Timeline Widget / Bearer Token guidance / Release Gate

- Dashboardへ上級者向けX Timeline Widgetを追加し、指定した公開X Accountの最近の投稿をRead Onlyで表示。既存`dashboard_widget.widget_config`へ設定を保存し、新規Table／Column／Migrationは追加しない。
- username、3／5／10件表示、Reply／Repostの含有設定、Title／Header color／Width／Height、手動Refreshに対応。
- X API requestはServer側だけで実行し、Browserから`api.x.com`へ直接接続しない。`APP_X_BEARER_TOKEN`はServer-side Secretとして保持し、HTML／JavaScript／API responseへ渡さない。
- X API host固定、TLS検証、bounded timeout、短時間Cache、期限付きstale fallbackを追加し、401／403等の認証・権限Errorをstaleで隠さない。
- X Timeline追加Modalへ「上級者向け機能」の案内を追加し、X Developer Platform、Pay Per Use、Server-side Bearer Tokenが必要なことを明示。
- Bearer Token状態を`missing`／`invalid_format`／`unverified`／`verified`／`auth_failed`へ分離。未設定／Local形式不正ではFrontendとServerの両方で追加を拒否し、HTTP 401を受けた現在Tokenは認証失敗として案内。
- Modal表示だけではX APIへToken検証Requestを送らず、実Timeline取得の結果で接続状態を更新。状態CacheにはSHA-256 fingerprintだけを保存し、Raw Tokenは保存しない。
- X設定変更／削除も全画面Reloadを前提にせず、無関係なYouTube／Clock Timer等の状態を不要に失わない既存V1.17.1契約を維持。
- X本体の「おすすめ / For You」Feed再現とUser Context OAuthを使うHome Timelineは対象外とし、将来課題へ分離。
- `APP_VERSION`、`APP_VERSION_LABEL`、`APP_ASSET_REVISION`を`1.17.2`へ統一。Runtime／Complete builderとVerifier、GitHub Actions Release Gateを1.17.2へ更新し、`var/cache/`全体を配布対象外へ整理。
- X Timelineを利用しない環境では新しい必須Secretはなく、DB Migrationも不要。

## RSS Reader Modernization 1.17.1 — 2026-08-19

### Stability / Session lock / Widget settings update

- 通常API ActionはAuthentication、CSRF、Action validation完了後にfile-backed PHP Session lockを早期解放し、遅い外部I/OによるDashboard API Requestの直列待ちを抑制。Account email／password変更はSession更新のため従来どおりLockを維持。
- Session解放処理をAPIの`Throwable` boundary内へ移し、`session_write_close()`失敗時も通常のJSON 500 responseとReference IDへ収めるよう修正。
- Camera / VideoへSnapshot 12秒、Video metadata 15秒、MJPEG 12秒のClient-side watchdogを追加し、固まった表示を再試行可能な状態へ復旧。
- Mailへ13.5秒、Earthquakeへ10.5秒、Sun / Moonへ6.5秒、Air Qualityへ8.5秒のClient-side watchdogを追加。
- RSS、Clock、Game、Memo、Task、Search Feed、Links、Weather、Earthquake、Sun / Moon、Air Quality、Calendar、Camera / Video、Mailの設定保存を、ページ全体Reloadではなく対象Card中心の更新へ変更。
- WeatherのTitle／色／Width／Heightのみの変更ではDataを再取得せず、地域／表示日数を変更した場合のみ再取得。
- Camera / VideoとMailは対象Widgetだけを再構築し、他Widgetの設定変更で再生中のYouTube iframe等が作り直されて停止する問題を解消。
- Dashboard共通通知をsuccess約2.5秒、info約3秒、danger約6秒で自動消去し、設定更新通知が残り続ける問題を修正。
- hls.js 1.6.16のVersion固定とanonymous CORSを維持したまま、Subresource IntegrityのSHA-384を実配布Assetと一致する値へ修正。
- `APP_VERSION`、`APP_VERSION_LABEL`、`APP_ASSET_REVISION`を`1.17.1`へ統一。
- DB Table／Column、Migration、SQL、必須configの追加変更はなし。
- GitHub Actions PHP 8.1／8.4のCurrent Regression、V1.17 focused tests、V1.17.1 focused tests、およびV1.17.1 Release GateをPASS。

## RSS Reader Modernization 1.17.0 — 2026-08-19

### Camera / Video Widget / Asset revision / Current test policy

- DashboardへCamera / Video Widgetを追加し、既存`dashboard_widget.widget_config`へ設定を保存。新規Table／Column／Migrationは追加しない。
- SnapshotをBrowserのImageとして表示し、手動更新とOFF／10秒／30秒／1分／5分／10分の自動更新、失敗時の直前成功画像維持に対応。
- YouTube watch／live／shorts／embed／youtu.be URLを既知HostとVideo IDで検証し、YouTube標準Playerで表示。
- MP4／WebM等をBrowser標準`<video>`で再生し、MJPEGはImage streamとして直接表示、HLSはNative HLSまたはhls.js 1.6.16で再生。
- hls.jsはVersion固定＋SRI付きで必要時だけ読込み、Apache-2.0 License noticeを追加。
- Auto Source判定へYouTube、Video extension、HLS、MJPEG endpoint、Snapshot画像extensionを追加し、曖昧なURLはSnapshotへ決め打ちせず「判定不能」として手動選択を案内。
- Smartphone向けにCamera / VideoのActionとModal余白を調整し、Width 1〜4／Height 1〜2と既存Drag & Dropを維持。
- 長期`immutable` Cache環境で段階配布Assetが古いまま残る問題に対応するため`APP_ASSET_REVISION`を導入し、正式Releaseでは`1.17.0`へ確定。
- TEST-1／TEST-2でDefault CIを現行Product Contract中心へ整理し、過去Version番号や過去Asset完全一致を固定する履歴Testを通常CIから分離。
- GitHub Actions PHP 8.1／8.4でCurrent Regression＋V1.17 focused testsをPASSし、Production smoke確認後にApplication Versionを`1.17.0`へ確定。
- DB Table／Column、Migration、SQL、必須configの追加変更はなし。

## RSS Reader Modernization 1.16.0 — 2026-08-17

### Calculator / Blind Spot Discovery / Dashboard UI

- UtilityへCalculator Widgetを追加。四則演算、Decimal、Percent、Sign、Backspace、Keyboard操作に対応し、計算はBrowser側のみで行い`eval()`は使用しない。
- Dashboard WidgetのTitle Barを44pxへ揃え、Drag Handleの実操作領域を44pxへ統一。
- InformationへBlind Spot / Discovery Widgetを追加し、20カテゴリ・国内向け40 Feedから普段見ない分野の記事を最大3件表示。
- Blind Spotは直前カテゴリを避け、24時間・最大18件の最近記事履歴で同一記事の連続表示を抑制。既存のSSRF Validation、FeedFetchService、Cache、Parserを再利用。
- Blind Spotの記事概要を「＋／－」で展開し、既存RSSと同じ右端配置へ統一。本文は`content`優先、未取得時は`description`を使用。
- Blind Spotへ既存Article Actionsを接続し、Stock保存、URL Copy、X投稿、Task追加を共通処理で利用。
- Smartphone、Height 1／2、Width 1〜4、Solar／Slateを含むThemeでBlind Spotの操作領域、内部Scroll、Title行数、Focus表示を調整。
- V1.16-F Full RegressionでClock Timer単体runtimeのjQuery非依存契約を確認し、Calculator／Blind Spot追加部をjQuery未定義環境では安全にskipするよう修正。
- DB Table／Column、Migration、SQL、必須configの追加変更はなし。Application Versionを`1.16.0`へ更新。

## RSS Reader Modernization 1.15.0 — 2026-08-16

### Information Widgets / Add Widget Catalog

- DrawerのWidget追加をRSS／Information／Utility／GameのCatalogへ整理し、既存Modal起動契約を維持したまま2列Tile表示へ変更。
- Earthquake Widgetを追加し、気象庁防災情報XMLの高頻度Feed＋長期Feed fallbackから最新地震、最大震度、M、深さ、津波文言を表示。
- Sun / Moon Widgetを追加し、Weatherと同じ地域検索、`date_sun_info()`、Dashboard向け月齢／月相計算で日の出・日の入り・月情報を表示。
- Air Quality / UV Widgetを追加し、Open-Meteo Air Quality APIからUS AQI、PM2.5、PM10、UV Indexを15分Cache＋stale fallback付きで表示。
- Weather／Earthquake／Sun / Moon／Air QualityのLocation Validation、Widget保存、Cache、Frontend状態表示を必要範囲だけ共通化。
- PC／Smartphone、Height 1／2、Solar／Slateを含むBootstrap 5 ThemeでInformation WidgetのHeader、操作領域、本文Scroll、Footer、Modal表示を調整。
- Dashboard空白領域をMouseでClickした際の不要な青いFocus outlineを抑制し、Keyboardの`:focus-visible`表示は維持。
- DB Table／Column、Migration、SQL、必須configの追加変更はなし。Application Versionを`1.15.0`へ更新。

## RSS Reader Modernization 1.14.1 — 2026-08-15

### Bootstrap / Bootswatch Theme alignment

- 通常RSS／Search Feedの記事Titleと概要を`--bs-body-color`／`--bs-body-bg`へ追従させ、Solar／Slate等のDark Themeで本文が同化する問題を修正。
- 記事Actions、Task、Stock、Calendar、Mail、Links、Weather、Clock Timer、Mini Game／Lights Outの中立Surface・補助色・BorderをBootstrap 5 Theme変数へ整理。
- Stock Tag管理PanelやSmartphoneのRSS概要Iconなど、後勝ちしていた固定色もTheme連動へ修正。
- Keyword Highlight、休日／週末、Timer終了、GameのPlayer／敵／宝／Goal、Lights Out ON等の意味を持つ状態色は明示色を維持。
- Solar／Slate専用の中立色上書きを減らし、Bootstrap / Bootswatch 5.3.8のTheme変数を共通契約として利用。
- PHP、JavaScript、HTML、API、DB schema、Migration、必須configの変更はなし。
- Application Versionを`1.14.1`へ更新。

## RSS Reader Modernization 1.14.0 — 2026-08-14

### Version 1.14.0 frontend modernization finalization

- Bootstrap / Bootswatchを4.1.3から5.3.8へ更新し、全8 ThemeをVersion固定Assetへ切替。
- Bootstrap 4時代のData API、Form、Utility、Modal等のmarkupをBootstrap 5へ移行。
- 右メニューのjquery-drawerをBootstrap Offcanvasへ置換し、Drawer→Modal遷移時のBackdrop／Focus競合を回避。
- jquery-drawer、iScroll、standalone Popper、および移行完了後のBootstrap 4旧配布Assetを削除。
- PC／Smartphoneと全8 ThemeでNavbar、Modal、Offcanvas、Stock、Memo、Task、Calendar、Mail、Links、Weatherの表示を調整。
- 通常RSS／Search Feed／各WidgetのCard見出しを`text-bg-*`へ統一し、背景色に応じた文字・Icon色へ自動追従。
- Search Feedの見出し背景色を`tr`ではなく`th`へ適用し、Bootstrap 5 Table背景に隠れる問題を修正。
- jQuery 3.7.1、Font Awesome Free 6.7.2、既存API／DB／Widget仕様を維持。
- Version 1.14でDB schema、Migration、SQL、必須configの追加変更はなし。
- Application Versionを`1.14.0`へ確定し、Release package builder／verifierとDependency／Release Documentationを現行構成へ更新。

## RSS Reader Modernization 1.13.0 — 2026-08-14

### Version 1.13.0 structure / performance / security finalization
- Stock一覧を`public/stock.php`へ分離し、Canonical routeを`/stock`へ整理。既存`/?tab=stock`は検索・並び替え・Page・Tag条件を維持して互換Redirect。
- 表示設定、Tab名、RSS Highlight設定を`public/settings.php`へ分離し、`/settings`へ集約。Account Settingsは従来どおりDashboard Modalを維持。
- `public/index.php`のDashboard Widget／Modal表示を内部Viewへ分割し、既存DOM／CSS／JavaScript／API契約を変えず可読性を改善。
- V1.13-A／EのPerformance計測を比較し、Stock DB helperを含め追加最適化が必要な劣化は確認されなかったため、根拠のないSQL／Cache変更は実施せず現行挙動を維持。
- V1.13-FでSecurity Header／Session／CSRF／SSRF／XSS／API境界を再確認し、Healthcheckと新規設置／Security Documentationを現行構成へ整合。
- 既存Migration `009_v1_9_mail_account.sql`と新規設置Schemaの説明を現行状態へ整合したが、DDL変更、新規Migration、必須config追加はなし。
- Application Versionを`1.13.0`へ確定。

## RSS Reader Modernization 1.12.1 — 2026-08-11

### Version 1.12.1 compatibility and regression fixes

- V1.11統合時に失われていたStock解除のAjax部分更新を復元し、対象Stockのみを削除する挙動を維持。
- Stock最終カード解除時は空状態表示、Page 2以降では前Pageへ戻る既存V1.8挙動を復元。
- StockからTaskへ追加する際、Task Widget 1件時の直接追加と複数時の既存選択Modalを復元。
- V1.12の現行実装に合わせ、履歴RegressionのV1.3-C fixtureとBrowser依存TestをCI環境へ整合。
- DB schema、Migration、configの追加変更はなし。Version 1.12のDB変更は引き続きMigration `012_v1_12_feed_keywords.sql`のみ。
- GitHub ActionsのPHP 8.1 / 8.4で全Regression PASSを確認。

## RSS Reader Modernization 1.12.0 — 2026-08-10

### Version 1.12.0 finalization

- RSS Highlightを追加し、ユーザー登録Keywordに一致するRSS Title部分を通常RSS／Search Feedの共通描画で強調表示。
- Highlight Keywordは複数登録、追加／削除、重複防止、最大50件、1件64文字までに対応。
- `feed_keyword` TableとMigration `012_v1_12_feed_keywords.sql`を追加。
- Mail Widget Phase 2として、Folder全体の未読件数、未読のみ表示、最終更新時刻を追加。
- Mail Widgetに件名／From検索、送信者Filter、IMAP Folder切替を追加。
- Mail本文取得はFolderとUIDを組み合わせて検証し、read-only境界を維持。
- Mail Folder選択は既存`dashboard_widget.widget_config`のschema 2として保存し、旧schema 1は`INBOX`として互換維持。
- Release helperをVersion 1.12.0向けへ更新し、Application Versionを`1.12.0`へ確定。
- Version 1.12でのDB構造変更はRSS Highlight用Migration 012のみ。Mail Phase 2によるTable／Column追加はなし。

## RSS Reader Modernization 1.2.0 — 2026-08-05

### Version 1.2.0 finalization

- V1.2-A～DとR2～R5の確認済み内容を統合し、Application Versionを`1.2.0`へ確定。
- 認証画面・通知・共通Error、記事表示・概要開閉・個別更新、Search Feed、記事Actionsを正式Release範囲として整理。
- 記事ActionsはStock保存、URL Copy、X投稿画面、記事TitleのみのTask追加へ対応。
- 三点リーダー、概要「＋」、新着Bellの操作性と記事Title表示領域の調整を含む。
- Version 1.2でDB Table／Column、Migration、SQL、必須設定の追加はなし。
- README、Release Notes、配置手順、Package Builder／Verifier、Version 1.2 Release Gateを更新。
- Application Runtimeの機能修正は行わず、R5からのApplication変更は`app/version.php`のみ。

## RSS Reader Modernization 1.2.0-dev.4 — V1.2-D / R5 — 2026-08-05

### New Bell title layout correction

- 記事側の新着BellをTitleの通常Flex幅から外し、Title左上へ固定。
- Bell分の余白をTitleの1行目だけに限定し、2行目は左端から表示出来るよう調整。
- Bellの22px表示、解除操作、Keyboard Focus、通常RSS／Search Feedの共通描画を維持。
- 三点リーダー、RSS概要「＋」、記事Actions、DB、SQL、設定ファイル、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.4 — V1.2-D / R1 — 2026-08-05

### Article Actions menu

- 通常RSSとSearch Feedの記事左端をBookmarkから三点リーダーへ変更し、1つの共通記事Actionsメニューを追加。
- 既存Stock保存処理、URLコピー、X Web Intent、記事タイトルのみのTask追加へ対応。
- Clipboard APIが利用出来ない場合は、従来Copy処理へFallback。
- X投稿用タイトルは長すぎる場合に200文字以内へ調整し、記事URLとともにURL Encode。
- Taskは現在のタブに表示される先頭のTask Widgetへ、期限なし・通常優先度で追加。記事URLは保存しない。
- 他メニューを開いた時、外側Click、Esc、Scroll、Resize、記事再描画時にActionsメニューを閉じる。
- PC／Smartphoneの44px操作領域、Keyboard操作、aria属性、カード内へ収める位置調整を追加。
- DB、Table、Column、Migration、SQL、外部Library、`.htaccess`、`config/local.php`の変更はなし。

## RSS Reader Modernization 1.2.0-dev.3 — V1.2-C / R5 — 2026-08-05

### Search Feed title color correction

- Search Feedの初期タイトル文字色を、既存カードと同じ`text-white`へ統一。
- 初回検索および個別更新後にJavaScriptで復元されるタイトルにも`text-white`を維持。
- `dark`を含む色付き見出しで、黒いタイトルが背景と同化する問題を修正。
- 背景に応じた文字色の動的切替は、全カード共通の将来課題として今回は実施しない。
- DB、API、Cache、CSS、`.htaccess`、`config/local.php`、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.3 — V1.2-C / R4 — 2026-08-05

### Search Feed summary and transient notice correction

- 「Stockへ保存しました」に2.5秒の自動消去を追加。
- 通常Feed専用だった概要Buttonのカード参照を、Search Feedにも対応。
- Search Feedの有効な`＋`からRSS概要を正常に開閉できるよう修正。
- 「RSS概要を確認出来ませんでした」に4秒の自動消去を追加。
- 空概要の`＋`は従来どおり表示したままdisabledを維持。
- Memoの`sessionStorage`対応は今回の対象外。
- DB、API、Cache、CSS、HTML、`.htaccess`、`config/local.php`、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.3 — V1.2-C / R3 — 2026-08-05

### Search Feed header layout correction

- Search Feedの見出しを通常RSSカードと同じ1段Layoutへ統一。
- `＝ / 検索語句 / 編集 / 再読み込み`を44px高の同一行へ配置。
- 長い検索語句は折り返して操作Buttonを2段目へ送らず、省略表示を維持。
- 編集・再読み込みButtonの44px操作領域とKeyboard Focus表示を維持。
- 検索処理、API、DB、Cache、記事表示、`.htaccess`、`config/local.php`、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.3 — V1.2-C / R2 — 2026-08-05

### Search Feed UI correction

- 初回検索成功後も見出しが「読み込み中...」のまま残る表示復元漏れを修正。
- 検索結果0件の場合も、Search Feed見出しを保存済み検索語句へ戻すよう修正。
- Structured API error時に初回見出しがLoading状態のまま残らないようError表示へ移行。
- Search Feedの見出し色を既存Widgetと同じ`success / primary / info / secondary / dark / warning / danger`表記へ統一。
- Search Feedの横幅表記を既存Widgetと同じ`1列 / 2列 / 3列 / 全幅`へ統一。
- DB、API、Cache、`.htaccess`、`config/local.php`、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.2 — V1.2-B / R3 — 2026-08-04

### Article title and summary control display correction

- 記事Titleを固定1行ではなく、内容に応じて1行または最大2行表示へ調整。
- 1行Titleでは不要な2行分の高さを確保せず、Stock／NEW／概要Buttonとの縦位置を自然に揃えた。
- 概要操作をUnicode `▽`からFont Awesomeの`plus-square`／`minus-square`へ変更。
- 展開中はMinus Icon、閉じた状態はPlus Iconとして表示し、44pxの操作領域は維持。
- API、DB、Cache、`.htaccess`、`config/local.php`、Version番号は変更なし。

## RSS Reader Modernization 1.2.0-dev.2 — V1.2-B / R2 — 2026-08-04

### R2 article action layout correction

- Smartphone幅で概要`▽`が薄く見え、Stockが右側へ移動していた記事行Layoutを修正。
- 記事行を`Stock｜Title｜▽`の3列へ戻し、Stockを従来位置の左側へ復帰。
- 概要`▽`は右端の独立44px列へ配置し、Icon Fontへ依存しないUnicode `▽`とColor／Font Sizeを明示。
- Loading／Empty／Error／Accordion detail rowの`colspan`を3列構成へ同期。
- DB、API、Cache、`.htaccess`、`config/local.php`、Version番号は変更なし。
- R2分割Regression: PASS 3,862／FAIL 0／SKIP 10。

### Feed article display / individual refresh

- 記事Titleの固定64文字切り詰めを廃止し、CSS Ellipsisと実寸Overflow判定へ変更。
- 実際に省略されたTitleだけ、240ms Delay後のHover／Keyboard Focusで全文Tooltipを表示。
- 各記事へ概要Toggleを追加し、`content`を優先、空の場合は`description`を使用。
- 概要は展開時だけDOMを生成し、`.text()`によるPlain Text表示、長文Scroll、元記事Linkを維持。
- 画像、iframe、動画、Script等を概要として実行・生成しない。
- Feed見出しを`＝ Title　✎ ⟳`へ整理し、編集位置を維持した個別更新Buttonを追加。
- 個別更新は既存`feed.fetch`を再利用し、owner確認、CSRF、Cache、ETag、Last-Modified、Retry、Backoffを維持。
- 更新中は現在の記事を残し、Button無効化と回転表示を行い、失敗時も旧記事を維持。
- 成功後は対象Feedだけ記事、Title、NEW件数を差し替え、他Widgetとページ全体は更新しない。
- Article行をTitle領域とAction領域へ整理し、既存Stockを維持しながら第3段／第4段で共通利用しやすい構造へ変更。
- DB、Migration、SQL、`.htaccess`、`config/local.php`、外部依存、Build環境の追加はなし。
- 分割した全RegressionでPASS 3,948／FAIL 0／SKIP 10。

## RSS Reader Modernization 1.2.0-dev.1 — V1.2-A — 2026-08-04

### Authentication / Notice / Common Error

- Login／RegistrationをBootstrap sample風Layoutから専用HTML・CSSへ更新。
- PC／Smartphone、Keyboard Focus、Native Enter submit、Password表示切替、二重送信防止へ対応。
- Login／Registrationへ中立名のHoneypotを追加し、Server側判定、汎用失敗Message、Login Throttle併用を実装。
- Logout後は旧認証Sessionを破棄し、新しい匿名SessionのFlashで「ログアウトしました。」を1回だけ表示。
- Session idle／absolute timeout時はSession IDを再生成し、Logoutとは別の期限切れMessageを1回だけ表示。
- 403／404／500／503の共通Error画面を追加し、Status、noindex／nofollow、情報非表示、Reference IDを維持。
- API Bootstrap／Configuration errorはHTMLへ変えず、Structured JSONを維持。
- Server用Root `.htaccess`のRewrite／拒否設定を維持し、ErrorDocument 403／404／500／503を追加。
- Unknown routeをDashboardの200へRewriteせず、正しい404へ変更。
- GitHub main inventoryに合わせ、添付ZIPに残っていた未参照jQuery 3.3.1とFont Awesome旧Formatを除外。
- DB、Migration、SQL、`config/local.php`追加、Feed Cache仕様変更はなし。
- 分割した全RegressionでPASS 3,744／FAIL 0／SKIP 10。

## Version 1.1.0 — 2026-08-03

### V1.1-K finalization and release

- V1.1-B～Jの機能を統合し、Application Versionを1.1.0へ確定。
- Secure Baseline、M1、M2、V1.1の回帰Testを再実行。
- V1.1追加後に古くなったM2構造TestとNode Harnessを現行実装へ同期。
- V1.1-C既存DB MigrationのDefault Prefixを`ig_`へ統一。
- 未参照のjQuery 3.3.1とFont Awesome旧形式を削除。
- Session、Feed Cache、Login Throttle Dataを配布対象から除外。
- README、Release Notes、Roadmap、設置・更新、Tag / GitHub Release手順を1.1.0へ整理。
- 完全統合ZIPとRuntime ZIPのDeterministic Build、SHA-256、CRC / Path Traversal / Manifest検証を追加。
- DBの追加変更はなく、V1.1-J / R2適用済みDBへの追加Migrationは不要。

## V1.1-J / R1 — Account Settings

- DrawerへAccount Settingsを追加。
- 現在のパスワード確認後に、Login用メールアドレスを変更可能にした。
- 現在のパスワード確認後に、新しいパスワードと確認入力でパスワードを変更可能にした。
- メールアドレスは既存仕様どおりHMAC Identityとして保存し、画面へ現在値を表示しない。
- 他UserとのIdentity重複、Active User、Transaction、MySQL Row Lock、CSRF、Throttleを維持した。
- 成功後はSession IDとCSRF Tokenを再生成し、Login状態を維持する。
- Account SettingsのTable／Column／Migration追加はない。


このChangelogはLegacy版そのもののリリース履歴ではなく、RSS Reader Modernization Projectの変更記録です。


## RSS Reader Modernization 1.1.0-dev.8 — V1.1-I / R2

- スマートフォン幅に限り、Dashboardの左右スワイプで4タブを切り替える操作を追加。
- 左スワイプは次のタブ、右スワイプは前のタブへ移動し、最初・最後のタブでは循環しない。
- Calendar、入力欄、Button、Link、Modal、Drawer、Widget並び替えHandle、画面端から始まる操作をスワイプ判定から除外。
- 縦Scrollと競合しないよう、移動距離、横方向の優位性、操作時間を制限。
- FeedとCalendarの読込中表示へFont Awesome Spinnerを追加し、成功・失敗後はbusy状態とSpinnerを解除。
- `prefers-reduced-motion`ではSpinnerの回転を止める。
- DB、Migration、API、必須設定は変更なし。

## RSS Reader Modernization 1.1.0-dev.8 — V1.1-I

- Calendar Widgetを追加し、月表示、前月・翌月・今月への移動へ対応。
- 通常予定を保存する`calendar_event`Tableと既存DB向けMigration `006_v1_1_calendar_event.sql`を追加。
- 通常予定の追加・変更・論理削除、複数日表示、最大500件・最大366日を追加。
- Task期限は`task`Tableを直接参照し、Task名、期限、優先度、完了状態の変更をCalendarへ自動反映。
- Calendar Widgetごとに完了Taskを表示するか選択可能。
- Calendar API、CSRF、owner境界、日付Validation、XSS-safe DOM構築、Migration / Browser Testを追加。
- Calendar Widget変更時のFrontend二重送信を実装中の回帰Testで検出し、1回送信へ修正。

## RSS Reader Modernization 1.1.0-dev.7 — V1.1-H

- Task項目を保存する`task`Tableと、既存DB向けMigration `005_v1_1_task.sql`を追加。
- 1つのTask Widget内に複数Taskを保持し、追加・変更・完了切替・論理削除へ対応。
- Task名、任意の期限、優先度（低／通常／高）、完了状態、作成順を保存。
- Task項目は`task`、Widgetの見出し・配置・幅・色・並び順は`dashboard_widget`へ分離して保存。
- Task CRUDをowner scope、CSRF、Transaction、Row Lock、論理削除で保護し、他Userの操作を拒否。
- Task名は1〜128文字、期限は厳密な`Y-m-d`、優先度はallowlist、1Widget最大100件へ制限。
- Calendar工程で期限を利用できるよう、owner／完了状態／期限のIndexを追加。
- 新規DB用`database/schema.sql`、CLI apply／verify、preflight／postflight、専用Regressionを追加・更新。

## RSS Reader Modernization 1.1.0-dev.6 — V1.1-G

- Memo本文を保存する`memo`Tableと、既存DB向けMigration `004_v1_1_memo.sql`を追加。
- Memo Widgetの追加・変更・論理削除、見出し、本文、見出し色、横幅1〜4へ対応。
- Memo本文は`memo`、配置・幅・色・並び順は`dashboard_widget`へ分離して保存。
- Memo CRUDをowner scope、CSRF、Transaction、論理削除で保護し、他UserのMemo操作を拒否。
- 改行を保持しながらHTMLとして解釈しない出力、1〜32文字の見出し、1〜4,000文字の本文Validationを追加。
- 新規DB用`database/schema.sql`、CLI apply／verify、preflight／postflight、専用Regressionを追加・更新。

## RSS Reader Modernization 1.1.0-dev.5 — V1.1-F

- 既存の`dashboard_widget`へClock Widgetを追加。
- Clockの追加・変更・論理削除、12／24時間、日付・秒表示、見出し色、横幅1〜4へ対応。
- Browserの現在時刻を1本のTimerで更新し、時刻表示のための継続API通信は行わない。
- ClockをFeedと同じ4タブへ配置し、V1.1-Eの並び替えに対応。
- Clock設定は`widget_config`へ制限付きJSONとして保存し、owner scope、CSRF、Transactionを維持。
- Clock専用Table、Column、Migration、必須設定の追加なし。

## RSS Reader Modernization 1.1.0-dev.4 — V1.1-E

- Feed Widgetのタイトルバーへ並び替えHandleを追加し、同一タブ内のDrag & Dropに対応。
- Mouse、Touch／Pen、Keyboardの矢印・Home・End操作を用意。
- `widget.reorder`APIでowner scope、CSRF、Transaction、重複ID拒否、古い画面との競合検出を追加。
- 並び替え失敗時は画面順を戻し、再読み込みを案内。
- 新規Feed Widgetは現在の並び順の末尾へ追加。
- DB Table／Columnの追加なし。V1.1-D postflight R2修正を取り込み。
- Follow-up R2〜R7でHandle、挿入位置表示、保存通知、見出し高さ、新着Bell表示を調整。


## RSS Reader Modernization 1.1.0-dev.3 — V1.1-D

- `dashboard_widget`を追加し、Feed、Clock、Memo、Task、Calendarの共通配置基盤を追加。
- 既存Feedを4タブ、Style、表示順を維持したFeed Widgetへ安全にBackfill。
- Feed CRUDとWidget配置を同じTransactionで同期し、owner scopeとRollbackを維持。
- `widget.list`API、Widget幅、TEXT保存の設定JSON、V1.1-E向けData Attributeを追加。
- Drag & Dropは実装せずV1.1-Eへ分離。
- Prefix、Migration再実行、M2 Dashboard render、V1.1-B/C Regressionを追加・更新。


## RSS Reader Modernization 1.1.0-dev.2 — V1.1-C

- `feed_item_state`を追加し、既存Item Identityを使った新着NEW表示を追加。
- 初回成功取得はBaseline扱いとし、2回目以降に初めて現れた記事だけをNEWにする。
- 記事単位とFeed単位の明示操作でNEWを解除し、画面表示だけでは自動解除しない。
- Cache hit、HTTP 304、stale-if-errorを含むFeed経路で同じ状態判定を使用。
- Table Prefix、owner scope、CSRF、Transaction、Migration再実行、Rollback手順を追加。


## RSS Reader Modernization 1.1.0-dev.1 — V1.1-B

- 記事URLから既知のTracking Parameterを除去。
- Feed表示前、Stock保存前、Item Identity生成前へ適用。
- 一般Query Parameterと登録済みFeed URLは維持。
- DB schema、Migration、必須設定の変更なし。
- V1.1-B専用Testと既存Regressionを追加・更新。


## RSS Reader Modernization 1.0.0 — 2026-08-02

### First stable release

- `APP_VERSION`を`1.0.0`、表示を`RSS Reader Modernization 1.0.0`へ確定。
- M4-F RC1からApplication Runtime、DB schema、公開API、Security境界、Frontend Runtime Assetを変更せず正式版へ昇格。
- deterministic builderの`final` modeで正式Release ZIPと外部SHA-256を生成。
- Final Packageを`package_status=FINAL`、`publishable=yes`としてRC / Previewと分離。
- Source全回帰、Checkpoint ZIP再展開、内部Manifest、外部SHA-256、秘密情報除外、Version整合を再確認。
- 実MySQL、実Feed、実Browser、Restore drill、GitHub hosted CIのPrivate Evidenceはこの作業環境では未収録であることをRelease Notesへ明記。
- `v1.0.0` TagとGitHub Releaseは、利用者がCommit / Push / CI確認後に作成する手順として確定。
- 新機能追加、DB Migration、必須設定追加、Cache clear、旧file削除はなし。

## RSS Reader Modernization 1.0.0-RC1 — 2026-08-02

### Release Candidate and real-environment gate

- `APP_VERSION`を`1.0.0-rc1`、表示を`RSS Reader Modernization 1.0.0-RC1`へ変更。
- deterministic builderの`rc` modeでRelease Candidate ZIPと外部SHA-256を生成。
- RCを`package_status=RELEASE_CANDIDATE`、`publishable=no`として正式版と分離。
- PHP Version、必須Extension、PDO driver、Runtime directoryを秘密情報なしで確認する環境Probeを追加。
- 実MySQL、実Feed、実Browser、GitHub hosted CI、Backup / Restore、RollbackのEvidence Templateを追加。
- Evidence形式、必須項目、Secret混入、PASS / HOLD / FAILを確認するGate Toolを追加。
- Build環境にない`pdo_mysql`、cURL、SimpleXML、mbstring、MySQL Server、完走しないChromiumはPASSへ読み替えずHOLDを維持。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、RSS Engine、Frontend Runtime Assetは変更なし。

## Release M4-E / R1 — 2026-08-02

### Release package, manifest, notes and tag procedure

- Checkpoint ZIPと利用者向けRuntime Release ZIPを分離。
- preview / rc / finalを分けるdeterministic release package builderを追加。
- ZIP entry順、timestamp、permissionを固定し、同一Sourceから同じSHA-256になるBuildを追加。
- Package内部の`RELEASE_MANIFEST.sha256`と、ZIP全体の`.zip.sha256`を追加。
- CRC、unsafe path、Private設定、実DB系file、Secret、Version markerを確認するVerifierを追加。
- Version 1.0.0向けRelease Notes準備版と、annotated Tag / GitHub Release手順を追加。
- M4-E Previewを`publishable=no`とし、M4-F / M4-G前の誤公開を防止。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、RSS Engine、Frontend Runtime Assetは変更なし。

## Release M4-D / R1 — 2026-08-02

### GitHub repository, portfolio and minimum CI

- GitHub ActionsへPHP 8.1 / 8.4の既存Regressionを追加。
- Workflow permissionを`contents: read`へ限定し、Secret、Deploy、Release処理を持たせない。
- SECURITY.md、CONTRIBUTING.md、Bug report templateを追加。
- Repository Description / Topics / Settings / Ruleset / hosted CIの確認手順を整理。
- Portfolio用の短文、長文、技術要点、Screenshot注意、AI支援説明例を追加。
- CIで確認する範囲と、実MySQL / Browser / Feed / Restore drillをM4-Fへ残す範囲を分離。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、RSS Engine、Frontend Runtime Assetは変更なし。

## Release M4-C / R1 — 2026-08-02

### Installation, update, backup and recovery procedures

- 新規空DBへの設置、Legacy DB migration、Git / ZIP更新手順を整理。
- Runtime設定の読込順、Default、制約を実コードへ合わせた。
- `local.php.example` と `.env.example` を既存Runtime対応Keyへ同期。
- Database、Private設定、APP_HASH_KEY、Code VersionのBackup / Restore drillを整理。
- Code-only rollbackとDB migrationを含むrollbackを分離。
- 配置ChecklistとM4-C専用testを追加。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、RSS Engine、Frontend Runtime Assetは変更なし。

## Release M4-B / R1 — 2026-08-02

### Documentation and third-party license alignment

- README、CHANGELOG、Documentation indexをM4-Bへ同期。
- Third-party noticeを実Assetへ合わせ、jQuery 3.7.1とFont Awesome Free 6.7.2へ更新。
- M2-Eで削除済みのFont Awesome配布PathをNoticeから除去。
- jQuery License copyをOpenJS Foundation表記へ更新。
- Font Awesome License copyを6.7.2の内容とfile名へ更新。
- Dependency / License対応表とM4-B専用testを追加。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、RSS Engine、Frontend Runtime Assetは変更なし。

## Release M4-A / R1 — 2026-08-02

### Version 1.0.0 release baseline and inventory

- GitHub mainのM2-G commitと添付Checkpoint ZIPをM4のBaselineとして固定。
- M3成果物が存在しないことを記録し、Releaseに必要な運用・実環境確認をM4-D〜Fへ吸収。
- Version 1.0.0のQuality Gate、公開物、配布物、Release Blocker、手動確認項目を整理。
- M2-Gから変更していない重要領域をSHA-256で固定するM4-A testを追加。
- GitHub mainに存在していたLICENSE、Third-party notice、license copyをCheckpoint ZIPへ復元。
- DB、Migration、公開API、Authentication、Authorization、Session、CSRF、SSRF、XSS、RSS Engine、Frontend動作は変更なし。


## Frontend M2-G / R1 — 2026-08-02

### M2 final regression and documentation

- M2-A〜F、M1-A〜G、Secure Baselineの全testを横断実行。
- M2完了用のfinal regression testとDocumentation整合testを追加。
- 現在Version、Frontend Asset allowlist、8テーマ、依存Version、主要UI / Accessibility invariantを再確認。
- README、Roadmap、Version policy、配置ChecklistをM2完了状態へ更新。
- M2全体の実施内容、維持した契約、既知の保留事項、手動Browser確認Matrixをまとめた。
- DB、公開API、Authentication / Session / CSRF / SSRF / XSS、M1 RSS Engine、画面処理、Frontend依存Versionは変更なし。

## Frontend M2-F / R1 — 2026-08-02

### Compatible Frontend dependency refresh

- jQueryを3.3.1から3.7.1 full buildへ更新し、既存のAJAX処理を維持。
- Font Awesome Freeを5.3.1から6.7.2 LTSへ更新し、旧icon class aliasとlocal WebFontを維持。
- Font AwesomeのWebFontを現在のCSSが参照するTTF / WOFF2 8ファイルへ入替え。
- Bootstrap / Bootswatch 4.1.3、Popper 1系、Drawer 3.2.2、iScroll 5.2.0-snapshotは互換性を優先して据え置き。
- Bootstrap 5移行はdata属性、jQuery plugin、Drawer、8テーマを横断するmajor migrationとなるため、この工程へ混在させない。
- script読込順、jQuery AJAX、Bootstrap Modal / Collapse、Drawer、8テーマ、Font Awesome icon / fontを回帰testへ追加。
- DB、公開API、Authentication / CSRF / SSRF / XSS、M1 RSS Engine、M2-Dの表示と操作は変更なし。

## Frontend M2-E / R2 — 2026-08-02

### Windows PowerShell cleanup helper correction

- `tools/apply_m2e_cleanup.ps1` がWindows PowerShell 5.1で文字化けし、Parser Errorになる問題を修正。
- UTF-8 BOMなしの日本語messageを廃止し、Script本体をASCIIのみ・CRLFで保存。
- 削除対象、`-WhatIf`、Git working tree確認、`public/index.php`確認、安全境界はR1から変更なし。
- Parser Errorは削除処理開始前に発生するため、R1実行時にAssetは削除されない。
- cleanup helperの文字コード回帰testと、配置文書内のPowerShell path表記を修正。

## Frontend M2-E / R1 — 2026-08-02

### Unused Frontend asset cleanup

- PHP / HTML、Theme resolver、CSS `url()` から実際に参照されるAssetを一覧化。
- Bootstrapの非圧縮版、bundle、grid / reboot単独版、未使用Source Mapを削除。
- Font Awesomeの未使用JavaScript版、個別CSS、SCSS / LESS、metadata、SVG spriteを削除。
- Drawerの非圧縮版を削除し、実行時に使用する圧縮版を維持。
- Font AwesomeのWebFontは `all.css` の参照互換を優先し、全形式を維持。
- 使用中vendor fileのLicense header、8テーマ、Frontend library Versionを維持。
- 既存Git作業フォルダ向けに、安全確認付きPowerShell cleanup helperと完全削除一覧を追加。
- DB、公開API、Authentication / CSRF / SSRF / XSS、M1 RSS Engine、M2-Dの表示と操作は変更なし。

## Frontend M2-D / R2 — 2026-08-02

### Feed column and Drawer density correction

- fixed layout tableでStock操作列と記事列が均等幅になる回帰を修正。
- Feed tableへ `colgroup` を追加し、Stock操作列を44px、記事列を残り幅へ固定。
- Drawerの通常項目を36pxへ戻し、section見出しとpaddingをコンパクト化。
- coarse pointer環境では44pxの操作領域を維持。
- Responsive 1 / 2 / 4列、Keyboard / Focus / ARIA、Feed / Stock API、DB、M1 RSS Engineは変更なし。
- M2-D R2専用のlayout regression testとFake PDO render確認を追加。

## Frontend M2-D / R1 — 2026-08-01

### Responsive layout and UI feedback

- Feed / StockをMobile 1列、Tablet 2列、Desktop 4列のBootstrap gridへ変更。
- PHP側の4件単位row生成を外し、長いタイトル・URLを折返す表示へ変更。
- Feed cardの初期高さ、Navbarの長いタブ名、Modal、Page Top、Drawer、Touch targetを調整。
- Feed / Stockそれぞれの空画面を分け、RSS追加先をModal内へ表示。
- RSS削除を「URLを空欄」から確認付きの明示Buttonへ変更し、既存 `content.delete` APIを継続。
- Feed取得失敗Cardへ再読込Buttonを追加。
- `alert()`を画面内noticeへ置換し、Stock保存成功とMutation失敗を表示。
- Modal / Drawerの主要文言と明らかな表記揺れを整理。
- DB、公開API Response、Authentication / CSRF / SSRF / XSS、M1 RSS Engine、Frontend library Versionは変更なし。
- Responsive / UI static test、Mutation runtime test、Feed retry runtime、Feed / Stock render testを追加。

## Frontend M2-C / R2 — 2026-08-01

### Login layout correction

- semantic `main` 追加後にLogin / Register formが左寄せになる回帰を修正。
- Login用 `main.login-main` を画面幅へ広げ、既存の `.form-signin { margin: auto; }` による中央配置を復元。
- Login / RegisterのForm、認証処理、CSRF、Collapse切替、M2-CのKeyboard / Focus / ARIA対応は変更なし。
- 同じ回帰を防ぐLogin layout testを追加。

## Frontend M2-C / R1 — 2026-08-01

### Semantic HTML and accessibility

- `<!doctype html>`、`lang="ja"`、`header`、`main`、`footer`、Skip link、page headingを追加。
- Feed cardを名前付きregionとし、Loading中の `aria-busy`、状態messageのlive region、Errorのalert semanticsを追加。
- Feed編集、Stock保存、Drawer内Modal起動をkeyboard操作可能なButtonへ変更。
- RSS追加・変更ModalをForm化し、Enter submitと既存AJAX / pending guardを一つの経路へ統一。
- SettingsのNavbar URL / 表示名へLabel、icon radio groupへfieldset / legend / unique idを追加。
- Drawerの `aria-expanded` / label更新、Open時Focus、Escape Close、Tab循環、Close後Focus returnを追加。
- Modal終了後は起動元へFocusを戻し、Page Topはscrollと同時にmainへFocusを移動。
- visible focus indicatorと `prefers-reduced-motion` 対応を追加。
- Feed API、DB schema、Authentication / CSRF / SSRF / XSS、M1 RSS Engine、Frontend library Version、基本画面構成は変更なし。
- M2-C専用のsemantic / accessibility static testとNode runtime testを追加。

## Frontend M2-B / R1 — 2026-08-01

### Feed rendering and state handling

- Feed取得、状態判定、Channel title描画、Item描画を小さな関数へ分離。
- Feed cardへ `loading` / `ready` / `empty` / `error` の状態を追加。
- 初期表示にLoading、0件Feedに「記事はありません」、Timeout / 404 / upstream failureに制御済みmessageを表示。
- 不正・不足Responseや配列以外のFeed / Itemを安全側で処理。
- Channel / Item title欠損時のfallbackを追加し、記事表示は従来どおり最大5件。
- 長い記事タイトルは絵文字のUTF-16 surrogate pairを分断せず64文字相当で省略。
- Feed linkはFrontendでもhttp / httpsだけを使用し、`.text()` と `noopener noreferrer` を維持。
- 同じFeed cardのRequest pending中は重複取得を開始しない。
- `favicon.png` を明示的に参照し、HTTPS環境のfavicon 404 / Mixed Content経路を回避。
- DB schema、公開API Response、M1 RSS Engine、Frontend library Version、画面構成は変更なし。
- M2-B専用のFeed structure testとNode runtime testを追加。

## Frontend M2-A / R1 — 2026-08-01

### Frontend script foundation

- Dashboard固有のインラインJavaScriptを `public/js/dashboard.js` へ分離。
- Dashboard固有のstyle blockを `public/css/dashboard.css` へ分離。
- PHPが `fetch_content()` 呼出しを生成する方式を廃止し、Feed cardの `data-feed-content-id` から初期化。
- API Request、error処理、Event登録を一つの外部JS内へ整理。
- Event namespaceと初期化済み判定を追加し、二重Event登録を防止。
- Content / Stock / Settings / Tabsの通信中はpending状態を保持し、連続送信を防止。
- Feed描画は `.text()`、validated link、`noopener noreferrer`、最大5件を維持。
- DB schema、公開API Response、M1 RSS Engine、Frontend library Version、画面構成は変更なし。
- M2-A専用のFrontend structure testとNode runtime testを追加。

## RSS Engine M1-G / R1 — 2026-08-01

### Fetch state, Retry-After, Backoff and bounded stale-if-error

- Feed URL hash単位のprivate state JSONへ最終試行、最終成功、結果種別、HTTP status、短いerror code、失敗回数、次回試行時刻を保存。
- stateへraw Feed URL、query token、Feed本文、詳細なtransport messageを保存しない。
- transient errorへ60秒 / 300秒 / 900秒 / 最大3600秒の段階的Backoffを追加。
- HTTP 429 / 503の安全なRetry-After（delta-seconds / HTTP-date）を優先し、上限を適用。
- timeout、DNS、一時HTTP error、temporary parse errorでは、最後の正常確認から最大24時間以内のstale Cacheを利用。
- HTTP 404等のpermanent error、TLS、private address、invalid redirect、response size超過等のSecurity errorではstaleを使用しない。
- 同一URLの同時障害はURL単位Lock内で1回だけFetch・state更新し、待機processはBackoff stateを再確認。
- 新しいRepository / Factory / Queue等を追加せず、小さなhelper関数と既存FeedCache / FeedFetchServiceの拡張に留めた。
- DB、Frontend、公開API、Stock、Parser、Adapter、Item identityは変更なし。
- HTTP / state / stale boundary / concurrency / architecture / security regression testを追加。

## RSS Engine M1-F / R1 — 2026-08-01

### Conditional Feed requests and HTTP 304 reuse

- ETag / Last-Modifiedを安全に取得し、Cache schema 2へ保存。
- TTL経過後は `If-None-Match` / `If-Modified-Since` を使い、HTTP 304時は既存Feed本文を再利用。
- `body_fetched_at` と `validated_at` を分離し、304では本文取得時刻を変更しない。
- Validatorは前回のeffective URLと今回の送信先が完全一致するときだけ送信し、redirect先変更時の漏えいを防止。
- 条件なしHTTP 304を拒否し、HTTP 200では新本文をParse成功後にCache置換。
- M1-E Cache schema 1の読み込み互換を維持し、次回200取得時にschema 2へ更新。
- `APP_FEED_CONDITIONAL_REQUEST_ENABLED` を追加し、Cacheを維持したまま条件付きRequestだけ無効化可能。
- Validator専用class hierarchyは追加せず、小さなhelper関数と既存Cache/Serviceの拡張に留めた。
- stale-if-error、Retry、Fetch state、Cache-Control / Expiresは後続工程へ分離。
- HTTP / Cache / redirect / concurrency / architecture / security regression testを追加。

## RSS Engine M1-E / R1 — 2026-08-01

### Server-side Feed cache and duplicate Fetch suppression

- `FeedFetchService` を追加し、owner-scoped `FeedSource` 後の安全Fetch・Parse・Cacheを一つのorchestration boundaryへ集約。
- 正常なHTTP responseかつRSS 2.0 / RSS 1.0 / AtomとしてParse成功したFeed本文だけを `var/cache/feed/` へ保存。
- Cache key / Lock keyはconfigured Feed URLのSHA-256とし、raw URLやquery tokenをファイル名へ露出しない。
- Cache本文はversioned JSON、strict Base64、SHA-256 integrityで保持し、PHP serialize/unserializeを不使用。
- TTL初期値60秒、Cache無効化、URL単位Lock timeoutをprivate configurationとして追加。
- `flock()`によるdouble-checked lockingで、同一URLの同時Requestを1回のupstream Fetchへ抑制。
- Cache破損、書込み不能、Lock timeoutではApplicationを停止せず、SB-09 hardened transportへfail-open。
- Cache hitでもParser / Adapter / Item identityを毎回実行し、公開API、Frontend、DB、Stockのcontractを維持。
- stale-if-error、ETag / Last-Modified / HTTP 304、Fetch state / Retryは後続工程へ分離。
- Cache lifecycle / corruption / permission / symlink / concurrency / architecture / security regression testを追加。

## RSS Engine M1-D / R1 — 2026-08-01

### Deterministic Feed Item identity

- RSS 2.0 `guid`、RSS 1.0 `rdf:about`、Atom `id` を形式別Adapterから内部 `sourceItemId` へ抽出。
- `ItemIdentity` と `ItemIdentityResolver` を追加し、`source-id → link → fingerprint` の優先順位を明示。
- configured Feed URLをscopeに含む `m1i:v1:` + SHA-256形式の不透明で決定的なidentityを導入。
- `content_id` / owner IDをidentityから除外し、同一Feedの複数登録で同じItem identityを維持。
- raw source ID / URL / title / contentをidentity値、公開API、Frontendへ露出しない。
- `NormalizedItem::toArray()` と既存APIの5項目contract、DB、Stock、Frontend、Fetcher、SSRF/XSS境界を維持。
- 重複Item削除、新着判定、永続化、cache、ETag、Retryは実装せず後続工程へ分離。
- Identity priority / stability / scope / boundary / malformed input / fixture / architecture regression testを追加。

## RSS Engine M1-C / R1 — 2026-08-01

### RSS / Atom adapters and date normalization

- `FeedParser` をsecure XML loadとAdapter dispatch中心へ縮小。
- `Rss2Adapter`、`Rss1Adapter`、`AtomAdapter` と共通 `FeedAdapterInterface` を追加。
- namespace、channel/entry/item、description、`content:encoded`、Dublin Core date等の形式別処理をAdapterへ分離。
- `FeedDateNormalizer` を追加し、既存 `Y-m-d H:i:s` 出力とsource timezone非変換を維持。
- Atomは従来の `updated` を優先し、未設定時に標準 `published` をfallbackとして使用。
- `FeedLinkSelector` と `FeedXmlHelper` を追加し、Qiita/Publickey型alternate link、text link、`url` fallbackを共通化。
- `rss_parse`、`parse_start()`、`rss_normalize_date()`、`rss_select_link_candidate()` の互換境界を維持。
- DB、Frontend、FeedSource、Fetcher、API response、SSRF/XSS、cache/ETag/Retryは変更なし。
- Adapter/Date/fixture/architecture/security regression testを追加。

## RSS Engine M1-B / R1 — 2026-08-01

### Feed Source model

- `FeedSource` を追加し、既存 `content_id` / `content_owner` / 検証済みURLをimmutableなFeed Engine modelとして表現。
- `FeedSourceMapper` を追加し、owner-scoped active content rowを認証済みownerと再照合してmodel化。
- Mapperはraw `content_value` を使用せず、`app_validate_feed_url()` 後のURLだけを受け取る構造に変更。
- `FeedFetcher` は任意URL文字列ではなく `FeedSource` のみを受け取るinterfaceへ変更。
- 不正・欠損DB rowはoutbound fetch前にfail-closedし、generic 500 responseとserver logへ分離。
- DB schema、Frontend、API response shape、SSRF/XSS boundary、cache/ETag/Retryは変更なし。
- M1-B専用のmodel/mapper/transport/API failure/static architecture testを追加。

## RSS Engine M1-A / R1 — 2026-08-01

### Fetcher / Parser responsibility split + Normalized Item

- `FeedFetcher` を追加し、`feed.fetch` のHTTP取得をSB-09 `app_safe_http_fetch()` 経由の明示的境界へ分離。
- `FeedParser` を `app/feed/` へ分離し、RSS 2.0 / RSS 1.0 / Atomの既存解析behaviorを維持。
- `NormalizedItem` を導入し、Parser内部では共通Item modelを生成。
- `parse_start()` と `rss_parse` compatibility aliasを残し、既存API array contractを維持。
- APIのowner lookup → stored URL validation → hardened fetch → parse → XSS-safe payloadの順序を維持。
- M1-A専用の実行test / architecture static testを追加。
- DB schema、Frontend、cache、ETag、retryは変更なし。

## Secure Baseline SB-15 / R3 — 2026-07-30

- PHP fallbackとDB schemaのUI defaultを統一。
- schemaのNavbar URL defaultを明示的HTTPSへ統一。
- Legacy hash evidenceからBuild環境固有の `/mnt/data/` pathを除去。
- `APP_HASH_KEY` の継続保持・安全なbackupに関する運用注意を追加。
- Version / tests / package manifest / Initial Commit資料をR3へ同期。

## Secure Baseline SB-15 / R2 — 2026-07-30

### Git pre-commit cleanup

- 未使用のLegacy暗号化関数削除後の状態を正式Checkpoint化。
- 削除済みTweet UIに対するdead JavaScript、空のlist item、古いコメントを削除。
- 未使用Vue 2.5.17 asset/runtime依存の削除済み状態へREADME/Roadmap等を同期。
- Visible version markerをSB-15 R2へ更新。
- Package manifestを最終内容から再生成。
- ProductのRSS/Auth/API/DB behavior変更なし。

## Secure Baseline SB-15 / R1 — 2026-07-30

### Documentation / Initial Commit gate

- READMEをSecure Baseline完成時点へ更新。
- Legacy解析、Modernization、Security、Change map、Roadmap、Initial Commit gateを文書化。
- Production deployment / new DB / table prefix手順を整理。
- Secure Baselineで意図的に残した制約を明示。
- GitHub Initial Commit対象からsecret/data/log/session等を除外する条件を再確認。
- Runtime機能変更なし。Visible version markerのみSB-15へ更新。

## Secure Baseline SB-14 / R1 — 2026-07-30

### Final regression/security matrix

- Authentication transaction rollback試験を追加。
- SSRF special-use address matrixを拡張。
- XSS、Parser fixture、CSRF surface、4-tab、repository leak scanを横断確認。
- 拡張試験で検出した特殊用途IPv4/IPv6判定の不足を明示CIDR拒否で補強。
- ZIP再展開後の全回帰試験・secret scan・manifest照合をRelease Gate化。

## Secure Baseline SB-13 / R2 — 2026-07-30

### Schema / data integrity / table prefix

- MySQL 8向けsanitized schemaを整備。
- `utf8mb4_unicode_ci`、relationship IDのUNSIGNED化、query pattern用Index、`user_conf.user_id` UNIQUEを定義。
- `DB_TABLE_PREFIX` を導入し、Runtimeの固定 `ig_*` 依存を除去。
- `schema.sql` / audit / migration / fixtureをprefix対応。
- 新しい空DBから開始する経路を追加。
- Existing Legacy DB向けpreflight / migration / postflightを用意。
- Legacy duplicate/orphanを自動削除・統合しない方針を維持。

## Secure Baseline SB-12 / R2 — 2026-07-30

### Atom link hotfix

- Atomの `<link href="...">`、`rel="alternate"`、複数linkを安全に選択する処理を修正。
- Qiita型 / Publickey型のAtom fixtureを追加。

## Secure Baseline SB-11〜12 / R1 — 2026-07-30

### Legacy bug fixes / PHP 8 stabilization

- 4タブlocationを0/1/2/3へ統一。
- Feed 0件・5件未満・4の倍数以外の表示不具合を修正。
- Feed type判定、設定値保持、二重submit、HTML構造等のLegacy不具合を整理。
- PHP 8.1+をRuntime最低要件として明示。
- Warning/Notice/Deprecated/TypeErrorにつながる境界を整理。
- RSS 2.0 / RSS 1.0 / Atom parserの失敗扱いを改善。

## Secure Baseline SB-08〜10 / R1 — 2026-07-30

### Validation / SSRF / XSS

- ID、enum、length、URLのstrict validationを追加。
- Feed fetchをserver-side registered URLから実行。
- HTTP/HTTPS限定、DNS/IP検証、redirect再検証、TLS verification、timeout、body上限を導入。
- Stock保存時の記事ページ再Fetchを廃止。
- Feed/DB/UI出力をescapeし、Feed payloadをplain text + validated URLへ正規化。

## Secure Baseline SB-05〜07 / R1 — 2026-07-30

### API / authorization / CSRF

- `public/api_v1.php` をthin HTTP boundaryへ縮小し、`app/api.php` にdispatcherを分離。
- POST-only explicit action APIへ整理。
- ownerはrequest値ではなく認証済みSessionから決定。
- Content/settings/tabs/stock/feed fetchへownership enforcementを追加。
- Login / Register / API / LogoutへCSRFを適用。

## Secure Baseline SB-03〜04 / R2 — 2026-07-29

### Session / authentication

- Session処理を中央化し、private `var/session/` へ保存。
- strict mode、cookie-only、HttpOnly、SameSite=Lax、HTTPS時Secureを設定。
- Login時Session IDを再生成。
- idle / absolute timeoutを導入。
- `password_hash()` / `password_verify()` へ移行。
- normalized email identity + HMACを採用。
- Duplicate identityをfail closed。
- Registration switchとLogin throttleを追加。
- Legacy credential自動移行は行わない方針へ確定。

## Secure Baseline SB-00〜02 — 2026-07-29

### Legacy freeze / boundary / PDO foundation

- Legacy sourceのhash / treeを凍結記録。
- `public/` を唯一のWeb公開領域へ分離。
- secrets / DB dump / logs / sessionを公開対象から分離。
- PDO exception mode、native prepare、assoc fetch、MySQL `utf8mb4`を設定。
- SQL parameter bindingを導入。
- user + user_conf作成をtransaction化。
- 日時formatを `Y-m-d H:i:s` へ修正。


## V1.2-C
Search Feed（登録RSS横断検索、共通RSS、AND/OR、カード個別更新）を追加。DB Schema変更なし.