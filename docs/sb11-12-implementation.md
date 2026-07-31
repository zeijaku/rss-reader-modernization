# SB-11〜SB-12 Implementation Notes

Build: `Secure Baseline SB-12 / R2`  
Base: user-verified `Secure Baseline SB-10 / R1`

## Scope

SB-11ではLegacy analysisで特定したfunctional defectsを修正し、SB-12ではPHP 8.1+ runtimeを明示してwarning/deprecation/type boundaryを整理した。
Security boundary（SB-03〜10）は維持し、DB schema migrationはSB-13へ分離する。

## SB-11 — Legacy functional bugs

### 1. Four-tab mapping

位置規則を次に統一:

- Tab 1 -> location 0
- Tab 2 -> location 1
- Tab 3 -> location 2
- Tab 4 -> location 3

Drawer link、Navbar current label、RSS create hidden locationを同一規則へ揃えた。
LegacyのDrawer `0,2,3,3` とNavbar 1-based mismatchを解消した。

### 2. Feed type assignment/text fallback class of bug

APIは`rss_check_string()`のhintで成功種別を決めない。
HTTP取得成功後は必ずparserへ渡し、unsupported/malformed responseは `invalid_feed` / HTTP 502として扱う。
HTML error page等を架空の`Text` Feedとして成功させない。

### 3. Parser / item count

- RSS 2.0 / Atom / RSS 1.0 rootsを明示
- Atom default namespaceを明示的に参照
- RSS 1.0 default RSS namespaceを明示的に参照
- zero-item Feedをvalid resultとして許可
- frontendは `Math.min(5, items.length)`

### 4. Grid close

Feed / Stockともpartial final rowを明示的に閉じる。
Row stateをstringではなくintegerとして扱う。

### 5. Tabs update isolation / double submit

`tabs.update`はtab名更新だけを行う。
Formは`submit` event + `preventDefault()` + AJAXの1経路に統一。
Settingsも同じsubmit modelへ揃えた。

### 6. Setting persistence/current display

UI設定はSessionへmutable cacheしない従来方針を維持。
Theme / Navbar style / iconはDB由来current valueをselected/checked表示する。

### 7. Additional corrections discovered in SB-11 audit

- Content edit styleをmodalへ引継ぎ
- generic FontAwesome `.fa-edit` handlerをContent専用triggerへ限定
- modal title / Stock modal duplicate id・aria参照解消
- configured Navbar linkの誤`disabled`解除
- DBでDESC取得したStockへの`rsort()`削除
- invalid `<p><div>` empty-state nesting修正
- `$result_content_cnt` / `$window_load` 初期化を分岐前へ

### 8. Previously fixed items retained by regression

- `H:m:s` -> `H:i:s`
- Stock title page-refetch removal
- missing `HTTP_USER_AGENT` null-safe logging
- strict tab allowlist

## SB-12 — PHP 8 runtime

### Runtime floor

PHP 8.1+を明示。`app_runtime_status()` / healthcheckで古いruntimeをNot Readyにする。

### Function/signature boundary

`app/public/tools`の全PHP function declarationをtoken scanし、optional parameterの後にrequired parameterが来ないことを検証。
`update_setting()`のLegacy signature問題は解消済みであることをruntime reflectionでも確認。

### Feed parser runtime cleanup

- parser inputを`mixed` boundaryで安全にreject
- `DateTimeImmutable`変換失敗をnullへ
- `strtotime(null/false)`型の経路を廃止
- global `mb_language`, `mb_internal_encoding`, `mb_detect_order` mutation撤去
- strict encoding detection
- UTF-8変換後のXML declarationをUTF-8へ整合
- XML parse errorは明示的に扱う

### Error policy

- `error_reporting(E_ALL)`
- `display_errors` = APP_DEBUG
- `display_startup_errors` = APP_DEBUG
- `html_errors=0`
- `log_errors=1`
- production browserは詳細非表示、private log policyを維持

### Runtime config

`config/local.php`のbool/scalarは従来どおり受理するが、array/objectを暗黙に`"Array"`等へcastせず明示的なconfiguration errorへする。

## Not in scope

- DB schema/data cleanup -> SB-13
- final release-wide test matrix -> SB-14
- final GitHub docs / Initial Commit gate -> SB-15


## SB-12 R2 — Atom link hotfix

実環境でQiita / Publickey系Atom Feedについて、entry titleは取得できるがarticle linkが空になり、frontendがanchorを生成しない事象を確認した。

R2では`feed_link()`をnamespace view依存からdirect-child XPath (`local-name()="link"`) に変更し、属性は`attributes()`から明示取得する。

Link選択優先順位:

1. `rel=alternate` + `type=text/html`（またはtype省略）
2. その他`alternate`
3. relationなし（RSS2 `<link>URL</link>`を含む）
4. `related`
5. `self`
6. その他relation

さらに`<link>URL</link>`本文型を維持し、Qiita互換の`<url>` direct childを最終fallbackとして扱う。

APIの`app_validate_external_link()`とfrontendのanchor生成はR1から変更せず、抽出後URLが安全化処理で消えていないことを別テストで検証した。
