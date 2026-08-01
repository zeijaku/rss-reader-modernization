# Roadmap after Secure Baseline

## Current milestone

`Secure Baseline SB-15 / R3` でSecurity、major Legacy bugs、PHP 8、DB integrity、test、documentationの土台まで完了し、Initial Commitとして公開済みです。

現在は `M1-G / R1`。Fetcher / Parser責務分離、Normalized Item、Feed Source、RSS 2.0 / RSS 1.0 / Atom Adapter、Date normalization、deterministic Item identity、Server-side cache、重複Fetch抑制、ETag / Last-Modified / HTTP 304、Fetch state、Retry / stale-if-errorまで完了しています。現行M1計画は完了し、次はM2 Frontendを扱います。

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

- Bootstrap dependency刷新
- jQuery削減/撤去検討
- Drawer / iScroll再評価
- Font Awesome assets整理
- mobile UX
- loading / error / retry UI
- accessibility
- keyboard / focus
- HTML semantics
- old LESS/SCSS/map/metadata等の配布資産整理

Frontend刷新はSecurity behaviorを変えないよう、SB-14 regressionを継続します。

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
