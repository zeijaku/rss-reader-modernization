## RSS Reader Modernization 1.4.0-dev.1 — V1.4-B / R1 — 2026-08-05

### Mini Game Widget foundation

- 既存`dashboard_widget` Tableを利用したGame Widget登録・変更・論理削除を追加。
- DrawerのWidget追加へ`Game追加`を追加し、既存Tab、横幅、見出し色、並べ替え処理へ統合。
- 第一候補`Icon Quest`用の5×5 Mock盤面を、既存Font AwesomeとSemantic HTMLで表示。
- Game進行状態をDBへ保存せず、User ID／Widget ID／Game Versionで分離するBrowser Storage Wrapperを追加。
- `localStorage`、`sessionStorage`、Memoryの順にFallbackし、JSON Parse失敗、未知Schema、Storage利用不能を安全に処理。
- 複数Game Widgetの状態分離、Reset、Widget削除成功後のStorage削除へ対応。
- Game盤面でDashboardのTab Swipeが開始されないよう除外属性を追加。
- V1.4-Bでは盤面移動、Enemy AI、勝敗、Scoreを実装せず、V1.4-Cの対象として維持。
- DB Table／Column、Migration、SQL、外部API、外部Library、Build環境の追加はなし。
- V1.4-B専用Test 109件、分割Full回帰 4,987件を実行し、FAIL 0を確認。

## RSS Reader Modernization 1.3.0 — 2026-08-05

### Version 1.3.0 finalization

- V1.3-A～Dの調査・実装・確認済み内容を統合し、Application Versionを`1.3.0`へ確定。
- Drawerを5区分へ整理し、現在地、Hover、Focus、Keyboard、Touch操作領域を統一。
- HeaderをBrand／現在地／外部Link／Menuへ整理し、56px高とResponsive表示を統一。
- 記事Title、三点リーダー、通常RSS／Search Feed／Clock／Memo／Task／Calendarの見出し余白を整理。
- Version 1.3でDB Table／Column、Migration、SQL、API、RSS解析Engine、外部Libraryの追加はなし。
- Full回帰、Release Documentation、Complete／Runtime Package、Manifest、SHA-256、再展開確認を実施。
- V1.3-DからのApplication Runtime変更は`app/version.php`のみ。

## RSS Reader Modernization 1.3.0-dev.3 — V1.3-D / R1 — 2026-08-05

### Common spacing and detail alignment

- BootstrapのTable Cell既定余白より強いSelectorへ変更し、記事Titleの`7px 2px 7px 6px`を実表示へ反映。
- 三点リーダー列の余分なCell paddingを除去し、PCは36px、Touch端末は44pxの操作幅へ整理。
- 通常RSS、Search Feed、Clock、Memo、Task、CalendarのWidget見出し開始位置・文字サイズ・Ellipsisを統一。
- Search Feed見出しへ共通`widget-title-text` Hookを追加。
- 新着Bell、記事Actions、RSS概要、Header、Drawerの既存挙動を維持。
- DB、SQL、Migration、API、JavaScript、外部Libraryの変更なし。

# Changelog

## RSS Reader Modernization 1.3.0-dev.2 — V1.3-C / R1 — 2026-08-05

### Header organization

- Headerを「Brand／現在地／設定済み外部Link／Menu」の役割へ分離。
- Brand Linkから現在のTab名を切り離し、現在地を独立した省略表示へ変更。
- Header高をThemeに左右されない56pxへ統一し、Menu ButtonはPC／Smartphoneとも44pxを維持。
- SmartphoneではBrand、現在地、Menuだけを表示し、長いTab名は1行Ellipsisで処理。
- PCでは外部Linkを右側へまとめ、長い表示名はLink単位で省略し、Headerの横Overflowを防止。
- Navbar背景`primary`はContrast Schemeを`dark`として扱い、Bootstrapに存在しない`navbar-primary`依存を解消。
- Bootstrap既定のToggler画像をやめ、既存Font AwesomeのBars Iconへ統一。
- Menu Buttonへ専用Focus Outlineを追加し、Bootstrapの`.btn:focus`による打ち消しを回避。
- 全8 Theme、Dark／Primary／Light、360／420／1024pxでHeader実寸確認を実施。
- DB、Table、Column、Migration、SQL、API、Session、RSS解析、JavaScript、外部Libraryの変更なし。

## RSS Reader Modernization 1.3.0-dev.1 — V1.3-B / R1 — 2026-08-05

### Drawer menu organization

- Drawerを「表示／Widget追加／カスタマイズ／リンク／Account」の5区分へ整理。
- タブ1～4とStockへ現在地表示を追加し、`aria-current=page`、左Border、控えめな背景色で選択中を明示。
- Link、Modal Button、LogoutのIcon列、文字位置、Row高、Hover、Focusを共通化。
- 通常Pointerは40px、Touch端末は44px以上の操作領域を維持。
- Account SettingsとLogoutをAccount区分へまとめ、表示設定をカスタマイズ区分へ分離。
- Navbar設定LinkはPCではHeader、991px以下ではDrawerに表示し、同一導線の重複を解消。
- Header側の外部Linkから不適切な`active`指定を削除。
- Esc、外側Click、Tab循環、Focus復帰は既存JavaScriptを変更せず維持。
- DB、Table、Column、Migration、SQL、API、Session、RSS解析、外部Libraryの変更なし。

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
Search Feed（登録RSS横断検索、共通RSS、AND/OR、カード個別更新）を追加。DB Schema変更なし。
