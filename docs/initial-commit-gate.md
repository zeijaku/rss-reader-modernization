# Initial Commit Gate

Build: `Secure Baseline SB-15 / R3`

## Verdict

**PASS — Secure BaselineをGitのInitial Commit候補にできる状態。**

ここでいうInitial Commitは、Modernization Projectの安全な出発点としてGit履歴を開始できるという意味です。公開GitHub Releaseは後工程です。

## Gate conditions

| 条件 | 判定 | 根拠 |
|---|---|---|
| Legacy evidence freeze | PASS | `docs/legacy/` のhash/tree record |
| P0 security issues closed | PASS | SB-01〜10、SB-14 matrix |
| P1 Auth/API/Input/major bug/PHP8 | PASS | SB-03〜12 |
| DB safety/integrity | PASS | SB-13 schema/audit/prefix tests |
| SB-14 security negative tests | PASS | `docs/test-report-sb14.md` / `docs/test-report-sb15.md` |
| Secret/data/log/session exclusion | PASS | `.gitignore` + repository leak scan |
| Production configuration documented | PASS | README + `docs/security.md` |
| Legacy unresolved data policy documented | PASS | `docs/legacy-analysis.md` |
| Required SB-15 docs | PASS | README / CHANGELOG / legacy-analysis / modernization / security |
| Legacy-to-Baseline traceability | PASS | `docs/change-map.md` |

## Environment limitation

Build環境では `pdo_mysql` / cURL / SimpleXML / mbstringを用いた完全なproduction E2Eを実行できませんでした。

代替testと静的検査に加え、配置先ではMySQL 8の新DB、prefix付き4テーブル、register/login、Feed CRUD、Stock/settings、実RSS/Atom linkを手動確認しています。

そのため、この制約はInitial Commit blockerとはしません。ただし今後のCI/CD環境では該当extensionを含むintegration test環境を用意する価値があります。

## Not blockers for Initial Commit

以下はSecure Baseline後の計画項目であり、Initial Commitを阻害しません。

- Feed cache / ETag / Last-Modified
- Source abstraction
- Frontend dependency modernization
- UI/UX / accessibility overhaul
- Foreign Key policy
- Public GitHub license decision
- Portfolio assets

## Before public GitHub release

公開前には少なくとも次を再確認します。

- license方針
- repository secret scan
- actual `config/local.php` / `.env` がtrackingされていないこと
- Legacy dump/archive/log/sessionがtrackingされていないこと
- public screenshot/demoに個人/運用データが含まれないこと
- READMEのCurrent/Requirements/Roadmapがrelease時点と一致すること

## Distribution-only handoff files

ZIP配布では作業者向けに `CHECKLIST_FOR_USER.md` と `UPDATED_FILES_SB*.md` を含めますが、これらは`.gitignore`でInitial Commit対象外にしています。公開repository側の履歴説明は `CHANGELOG.md` と `docs/` に集約します。
