# Modernization Roadmap

## Current milestone

`Secure Baseline SB-15 / R3` でSecurity、major Legacy bugs、PHP 8、DB integrity、tests、documentationの土台まで完了しました。

このSecure BaselineをGit履歴のInitial Commitとして公開し、以後の改修を段階的に積み上げます。

```text
Secure Baseline SB-15 / R3
  ↓
GitHub Initial Commit / Repository publication  ← current
  ↓
Source / RSS Engine modernization
  ↓
Frontend modernization
  ↓
v1.0 release
  ↓
Portfolio integration
```

## Source / RSS Engine modernization

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

主な検討項目:

- Fetcher / Parser責務分離
- normalized item model
- Feed source model
- server cache
- duplicate fetch suppression
- ETag / Last-Modified / HTTP 304
- fetch status / last fetched / error state
- retry strategy
- date normalization
- item identity
- per-source adapter設計

最初はRSS 2.0 / RSS 1.0 / Atomを対象とし、JSON Feed、REST API、HTML source adapter等への拡張余地を残します。

## Frontend modernization

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

Frontend刷新でもSecure BaselineのSecurity behaviorとregression testsを維持します。

## v1.0 release

Engine / Frontendの主要Modernization後に、完成版としてのreleaseを検討します。

- README / screenshot更新
- production secret/data再scan
- dependency / license再確認
- release tag / version policy確定
- public demoの有無判断

Repositoryの公開開始と、完成版`v1.0` Releaseは分けて扱います。

## Portfolio integration

Git履歴を含め、Legacy → Secure Baseline → Engine → Frontendの改善過程を成果として整理します。

- Security / PHP 8 / MySQL 8 / testing
- Before / After architecture
- Legacyのriskを分離しながら改修した経緯
- 実装判断と検証方法
