# Initial Commit Gate

Build: `Secure Baseline SB-15 / R3`

## Verdict

**PASS — Secure BaselineをGitのInitial Commit候補にできる状態。**

このCheckpointをModernization Projectの安全なGit履歴の出発点とします。Legacy版そのものや秘密情報を過去commitとして持ち込まず、Secure Baselineから履歴を開始します。

## Gate conditions

| 条件 | 判定 | 根拠 |
|---|---|---|
| Legacy evidence freeze | PASS | `docs/legacy/` のhash/tree record |
| P0 security issues closed | PASS | SB-01〜10、SB-14 matrix |
| P1 Auth/API/Input/major bug/PHP8 | PASS | SB-03〜12 |
| DB safety/integrity | PASS | SB-13 schema/audit/prefix tests |
| Security negative tests | PASS | `docs/test-report-sb14.md` / `docs/test-report-sb15.md` |
| Secret/data/log/session exclusion | PASS | `.gitignore` + repository leak scan |
| Production configuration documented | PASS | README + `docs/security.md` |
| Legacy unresolved data policy documented | PASS | `docs/legacy-analysis.md` |
| Legacy-to-Baseline traceability | PASS | `docs/change-map.md` |
| Project / third-party licenses documented | PASS | `LICENSE` / `THIRD_PARTY_NOTICES.md` / `licenses/` |

## Environment limitation

Build環境では `pdo_mysql` / cURL / SimpleXML / mbstringを用いた完全なproduction E2Eを実行できませんでした。

代替testと静的検査に加え、配置先ではMySQL 8の新DB、prefix付き4テーブル、register/login、Feed CRUD、Stock/settings、実RSS/Atom linkを手動確認しています。

この制約はInitial Commit blockerとはしません。将来CI/CDを導入する場合は、該当extensionを含むintegration test環境を追加する予定です。

## Deferred modernization

以下はSecure Baseline後の計画項目であり、Initial Commitを阻害しません。

- Feed cache / ETag / Last-Modified
- Source abstraction
- Frontend dependency modernization
- UI/UX / accessibility overhaul
- Foreign Key policy
- Portfolio assets

## Repository publication check

Initial Commit / Push直前に次を再確認します。

- `LICENSE` / `THIRD_PARTY_NOTICES.md` とvendored assetsの整合性
- repository secret scan
- `config/local.php` / 実 `.env` がtrackingされていないこと
- Legacy dump/archive/log/sessionがtrackingされていないこと
- `.gitignore`適用後のtracking対象が意図した構成であること
- README / Roadmapが現在Checkpointと一致すること
- testsがPASSすること

Screenshotやpublic demoはRepository公開の必須条件にはせず、必要になった段階でデータをsanitizedした上で追加します。

## Distribution-only handoff files

ZIP配布時の作業者向け `CHECKLIST_FOR_USER.md` と `UPDATED_FILES_SB*.md` は `.gitignore` でInitial Commit対象外です。公開Repositoryの履歴説明は `CHANGELOG.md` と `docs/` に集約します。
