# Roadmap after Secure Baseline

## Current milestone

`Secure Baseline SB-15 / R3` でSecurity、major Legacy bugs、PHP 8、DB integrity、test、documentationの土台まで完了し、Initial Commitとして公開済みです。

正式版は `RSS Reader Modernization 1.7.0`です。Version 1.7ではAsset／HTTP Cache、Security Header、固定30日のRemember Token、Widget縦2段、RSS表示件数、日本の祝日表示まで正式化しました。

## Version 1.1 — Dashboard機能追加

- [x] V1.1-A Baseline・DB・工程分析
- [x] V1.1-B Tracking Parameter除去
- [x] V1.1-C 新着NEW表示・Feed item state
- [x] V1.1-D Dashboard Widget配置基盤・既存Feed移行
- [x] V1.1-E タイトルバーのDrag & Drop・並び順保存
- [x] V1.1-F Clock
- [x] V1.1-G Memo
- [x] V1.1-H Task
- [x] V1.1-I Calendar
- [x] V1.1-I / R2 Mobile swipe・Loading Spinner
- [x] V1.1-I / R3 Mobile Task期限欄調整
- [x] V1.1-J Account Settings
- [x] V1.1-K 統合回帰・Version 1.1.0 Release

V1.1-JではDB構造を変えず、既存のUser IdentityとPassword Hashを安全に更新するAccount Settingsを追加しました。Account削除、確認Mail、Password Resetは別工程とします。


## Version 1.2 — Authentication / Feed UX / Search / Actions

- [x] V1.2-A Login・Registration、Honeypot、Logout／Session expiry通知、共通Error
- [x] V1.2-B 記事Title、全文Tooltip、RSS概要、Feed Card個別更新
- [x] V1.2-C Search Feed
- [x] V1.2-D 記事Actions、Stock、URL Copy、X、Task追加
- [x] V1.2-D / R2～R5 操作領域と記事Title表示幅の調整
- [x] Version 1.2統合回帰・Version 1.2.0 Release

Version 1.2ではDB Table／Column、Migration、SQL、必須設定の追加はありません。ヘッダーやDrawerの追加改善はVersion 1.2へ混在させず、次Versionの候補として扱います。

## Version 1.3 — Common UI organization

- [x] V1.3-A Header／Drawer／Widget見出し／記事操作部の現状調査
- [x] V1.3-B Drawer分類、現在地、Hover／Focus、Account配置
- [x] V1.3-C HeaderのBrand／現在地／外部Link／Menu整理
- [x] V1.3-D title-wrap、三点リーダー、Widget見出しの余白調整
- [x] V1.3-E Full回帰・Version 1.3.0 Release

Version 1.3ではDB Table／Column、Migration、SQL、API、RSS解析Engine、外部Libraryを追加していません。ゲーム性のあるWidgetはVersion 1.4以降の別工程とします。


## Version 1.4 — Mini Game Widget

- [x] V1.4-A Widget／Storage調査、第一ゲーム仕様決定
- [x] V1.4-B Mini Game Widget基盤
- [x] V1.4-C Icon Quest本体実装
- [x] V1.4-D 操作性、保存Recovery、Tutorial、Theme調整
- [x] V1.4-D / R2 Game Header余白修正
- [x] V1.4-E Full回帰・Version 1.4.0 Release

Version 1.4は既存`dashboard_widget` Tableだけを利用し、Game進行状態はBrowser Storageへ保存します。新しいTable／Column、Migration、SQL、外部API、外部Libraryは追加していません。

## Version 1.5 — Clock Timer

- [x] V1.5-A Clock Timer調査・工程設計
- [x] V1.5-B Timer基本実装
- [x] V1.5-C Storage Recovery・複数Tab同期・Theme・操作性
- [x] V1.5-C / R2～R5 Smartphone表示・Cache切り分け・終了表示調整
- [x] V1.5-D Full回帰・Version 1.5.0 Release

Version 1.5は既存Clock Widgetと`dashboard_widget` Tableを利用し、Timer実行状態はBrowser Storageへ保存します。新しいTable／Column、Migration、SQL、必須設定、外部Library、音、Browser通知は追加していません。

## Version 1.6 — Swipe表示／Lights Out

- [x] V1.6-A Baseline・Swipe・Game Widget・Storage調査
- [x] V1.6-B Smartphone Tab Swipe方向Indicator
- [x] V1.6-C Lights Out基本実装
- [x] V1.6-D Lights Out状態保持・品質調整
- [x] V1.6-E Full回帰・Version 1.6.0 Release

V1.6-Bは既存Swipe判定と操作除外を維持し、表示だけを追加しました。V1.6-Cは既存Game Widget subtypeとしてLights Outを追加し、V1.6-Dで状態保持、Storage Recovery、Keyboard／Focus品質を追加しました。V1.6全体は原則DB変更なしで進め、Asset Cache Bustingの一元化、Remember Token、Passkey、Timer音／通知は別工程として保留します。

## Version 1.7 — Cache／Login persistence／Widget Grid／Security Header

- [x] V1.7-A 総合事前調査・設計
- [x] V1.7-B GitHub開発再開Baseline
- [x] V1.7-C Asset Cache Busting一元化
- [x] V1.7-D HTTP Cache／Security Header
- [x] V1.7-E Remember Token DB・Backend
- [x] V1.7-F 30日間ログインUI／Session統合
- [x] V1.7-G Widget Grid Prototype
- [x] V1.7-H Widget縦幅正式実装
- [x] V1.7-H / R2 Scrollbar・RSS表示件数・Migration互換調整
- [x] V1.7-H / R3 標準Row・RSS件数・Clock／Game高さ1互換調整
- [x] V1.7-H / R4 Calendar日本祝日・60日Cache・Fallback
- [x] V1.7-I Full回帰・Version 1.7.0 Release／GitHub main反映準備

V1.7-BはVersion 1.6.0 Complete版を正として`1.7.0-dev.1`へ進め、GitHubの`feature/v1.7-modernization`を作成しました。V1.7-CではLocal Asset URLを`APP_VERSION`ベースのHelperへ一元化し、V1.7-DでStatic Asset Cache、動的Response no-store、限定的なSecurity Headerを追加しました。V1.7-Eでは固定30日期限のRemember Token TableとToken Domain処理を追加し、V1.7-FでLogin Checkbox、Cookie、Session自動復元、Logout／Password変更時失効へ接続しました。V1.7-GではDB・APIを変更せず、4列／2列／1列、縦2段、Drag／Keyboard、全8 ThemeをFixtureで比較しました。V1.7-HではMigration 008、全Widget CRUD、追加／編集画面、固定Rowを正式実装し、Smartphoneは自動高を維持しました。R2では実機確認を受けて一律Scrollを撤去し、RSSの自動／1～30件表示と`information_schema`非依存SQLへ調整しました。R3では標準Rowを320px下限へ引き上げ、RSS自動表示を5件／10件へ単純化し、Clock／Gameは高さ1でも従来どおり主要操作を切らない自然拡張へ戻しました。R4ではCalendarへ日本の祝日表示を追加し、内閣府CSVを設定可能URLから60日間隔でBackground更新、取得失敗時は既存Cache／SnapshotへFallbackします。V1.5／V1.6の機能はV1.7へ統合しますが、V1.6 TagやV1.5／V1.6 GitHub Releaseを追加しません。

## M1 — Source / RSS Engine

### Goal

現在のRSS/Atom処理を、将来RSS以外のsourceも扱える構造へ整理します。

```text
Source definition
      ↓
Fetcher
      ↓
Parser / Adapter
      ↓
Normalized Item[]
      ↓
UI / Stock
```

最初はRSS 2.0 / RSS 1.0 / Atomを対象としますが、内部の共通Item modelは将来のsource typeを想定します。

候補:

```text
RSS / Atom
JSON Feed
REST API
HTML source adapter
local/manual data
```


### Progress

- [x] M1-A Fetcher / Parser責務分離 + Normalized Item
- [x] M1-B Feed Source model
- [x] M1-C RSS 2.0 / RSS 1.0 / Atom Adapter整理 + Date normalization
- [x] M1-D Item identity
- [x] M1-E Server-side cache + 重複Fetch抑制
- [x] M1-F ETag / Last-Modified / HTTP 304
- [x] M1-G Fetch status / error state + Retry strategy + stale-if-error

### Planned topics

- Fetcher / Parser責務分離
- normalized item model
- Feed source model
- server cache
- duplicate fetch suppression
- ETag / Last-Modified / HTTP 304
- fetch status / last fetched / error state
- retry strategy
- date normalization
- item count policy
- GUID/hash等のitem identity検討
- per-source adapter設計

HTML scrapingはサイト構造変更の影響が大きいため、generic parserとして無制限に扱うのではなく、adapter単位のopt-inを前提に検討します。

## M2 — Frontend

### Progress

- [x] M2-A Frontend基盤整理
- [x] M2-B Feed表示処理整理
- [x] M2-C HTML構造・Accessibility
- [x] M2-D Responsive・UI / UX
- [x] M2-E 不要Frontend Asset整理
- [x] M2-F Frontend依存関係更新
- [x] M2-G 最終回帰・Documentation

M2-AではインラインJavaScript / CSSを外部Assetへ分離し、PHP生成JavaScriptをdata属性ベースへ変更しました。M2-BではFeed通信とDOM描画を分け、Loading、0件、取得失敗、不正Response、欠損タイトル、長いUnicodeタイトルを扱います。M2-Cではdoctype / lang / landmark、Form / Button、Keyboard、Focus、ARIAを改善しました。M2-DではMobile 1列、Tablet 2列、Desktop 4列のgrid、長い文字列の折返し、空画面、RSS削除、Feed再読込、画面内通知を改善しました。M2-Eでは画面から参照されないCSS / JavaScript、SCSS / LESS、metadata、SVG spriteを削除しました。M2-FではjQuery 3.3.1を3.7.1、Font Awesome Free 5.3.1を6.7.2へ更新しました。M2-Gでは全工程の回帰、Asset allowlist、Documentation link、配布除外、手動確認Matrixを整理しました。Bootstrap / Bootswatch 4.1.3、Popper 1系、Drawer 3.2.2、iScroll 5.2.0-snapshotは、既存MarkupとThemeの組合せを壊さないため維持しています。

Frontend刷新はSecurity behaviorを変えないよう、SB-14以降とM1のregressionを継続します。

## M4 — Version 1.0.0 Release preparation

### Progress

- [x] M4-A Release基準・公開物・残課題の棚卸し
- [x] M4-B README・CHANGELOG・License・Third-party notice整理
- [x] M4-C 新規設置・更新・設定・Backup・復旧手順
- [x] M4-D GitHub公開状態・Repository構成・Portfolio説明・最小CI
- [x] M4-E 配布ZIP・Release Notes・SHA-256・Tag / Release手順
- [x] M4-F Version 1.0.0候補版の全回帰・実環境確認（RC作成・自動検証完了、実環境Evidence未収録）
- [x] M4-G 最終Quality Gate・正式Release

M4へ新機能、大規模Refactor、Bootstrap 5移行、npm / Composer等のbuild tool追加は混在させません。

## 06 — GitHub public release

- repository history整理
- license方針決定
- README最終化
- sample screenshot
- public demoの有無判断
- production secret/data再scan
- release tag / version policy

Secure Baseline終了後からGit履歴を開始し、04/05の改修を1 commit = 1 purposeに近い形で積み上げる予定です。

## 07 — Portfolio

- Legacy → Secure Baseline → Engine → Frontendの改善ストーリー
- Security / PHP 8 / MySQL 8 / testingの技術要点
- Before/After architecture
- 課題発見と修正方針
- AI支援を利用した場合の適切な説明

Portfolioでは「古いコードが悪かった」だけでなく、Legacyの仕様を把握し、riskを分離しながら段階的に近代化した点を示します。

## M1-G — Fetch state / Retry / stale-if-error

- Feed URL hash単位で成功・失敗時刻、HTTP status、短いerror code、失敗回数、次回試行時刻をprivate JSONへ保存。
- 一時障害は60秒、5分、15分、最大1時間の段階的Backoff。HTTP 429 / 503の有効なRetry-Afterを優先。
- 最後の正常確認から24時間以内のCacheだけをtransient error時に利用。Security / permanent errorではstaleを使用しない。
- 同一URLの同時障害はURL単位Lock内で1回だけFetch・state更新し、待機processはBackoffとstale Cacheを利用。
- DB / Frontend / 公開API / Parser / Item identityは変更しない。

詳細: [`m1-g-implementation.md`](m1-g-implementation.md)
