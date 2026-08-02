# Documentation

RSS Reader Modernization Projectの公開ドキュメント索引です。

## Overview

- [`modernization.md`](modernization.md) — Secure Baseline SB-00〜15の実施内容
- [`roadmap.md`](roadmap.md) — Secure Baseline後のModernization計画
- [`change-map.md`](change-map.md) — Legacy issue → work unit → implementation → testの対応表

## M4 release preparation

- [`m4-a-implementation.md`](m4-a-implementation.md) — Release Baseline、公開物、残課題
- [`m4-b-implementation.md`](m4-b-implementation.md) — README、CHANGELOG、License、Third-party notice
- [`m4-c-implementation.md`](m4-c-implementation.md) — 新規設置、更新、設定、Backup、復旧
- [`installation.md`](installation.md) — 新規空DBとLegacy DB移行
- [`update.md`](update.md) — Git / ZIP更新
- [`configuration.md`](configuration.md) — Runtime設定、Default、制約
- [`backup-and-restore.md`](backup-and-restore.md) — Database / Private設定 / CodeのBackupと復旧
- [`rollback.md`](rollback.md) — Code / Configuration / DBのRollback
- [`deployment-checklist.md`](deployment-checklist.md) — 配置前後の確認
- [`test-report-m4-b.md`](test-report-m4-b.md) — M4-B test結果
- [`test-report-m4-c.md`](test-report-m4-c.md) — M4-C test結果
- [`dependencies.md`](dependencies.md) — Frontend dependencyとLicense copyの対応
- [`release-gate-v1.0.0.md`](release-gate-v1.0.0.md) — Version 1.0.0 Quality Gate
- [`m4-plan.md`](m4-plan.md) — M4-A〜Gの工程

## Security / deployment

- [`security.md`](security.md) — Security model、設定、運用上の注意
- [`sensitive-data-manifest.md`](sensitive-data-manifest.md) — Repositoryへ含める/含めないデータ方針
- [`initial-commit-gate.md`](initial-commit-gate.md) — Initial Commit判定と公開前確認事項

## Legacy analysis

- [`legacy-analysis.md`](legacy-analysis.md) — Legacy版の解析結果と対応方針
- [`legacy/README.md`](legacy/README.md) — Legacy evidenceの扱い
- [`legacy/legacy-tree-manifest.txt`](legacy/legacy-tree-manifest.txt) — Legacy tree記録
- [`legacy/source-sha256.txt`](legacy/source-sha256.txt) — Primary evidence hash記録

## Implementation records

- [`sb00-02-implementation.md`](sb00-02-implementation.md)
- [`sb03-04-implementation.md`](sb03-04-implementation.md)
- [`sb05-07-implementation.md`](sb05-07-implementation.md)
- [`sb08-10-implementation.md`](sb08-10-implementation.md)
- [`sb11-12-implementation.md`](sb11-12-implementation.md)
- [`sb13-implementation.md`](sb13-implementation.md)
- [`sb13-legacy-audit.md`](sb13-legacy-audit.md)

## Test records

- [`sb14-test-matrix.md`](sb14-test-matrix.md) — 最終Security / Regression matrix
- [`test-report-sb14.md`](test-report-sb14.md) — SB-14 test report
- [`test-report-sb15.md`](test-report-sb15.md) — SB-15 documentation / Initial Commit gate report
- その他の `test-report-*.md` は各Secure Baseline段階の検証記録です。

## Package verification

- `package-manifest.txt` — 配布ZIPのhash/size検証用manifest。ZIP引き渡し専用ファイルも含むため、`.gitignore` 適用後の公開Repositoryファイル一覧とは一致しません。

## Historical notes

Secure Baseline初期のdeployment / login / session / logout等のhotfix記録は `hotfix-r2.md`〜`hotfix-r6.md` として残しています。現在CheckpointのRevisionとは別系列の履歴です。

## Database migration

- `database/schema.sql` — 新規DB用sanitized schema
- `database/audit/preflight.sql` — Legacy DB migration前確認
- `database/migrations/001_sb13_integrity.sql` — SB-13 migration
- `database/audit/postflight.sql` — migration後確認

新規環境では `database/schema.sql` を使用し、Legacy DBを保持して移行する場合だけpreflight → migration → postflightの順に使用します。

## M4 Release preparation

- [`m4-a-implementation.md`](m4-a-implementation.md) — M4-A Baseline / inventory
- [`m4-a-baseline.json`](m4-a-baseline.json) — M2-G critical file hash
- [`m4-a-files.md`](m4-a-files.md) — 変更 / 新規 / 削除file
- [`m4-plan.md`](m4-plan.md) — M4-A〜Gの正式工程
- [`release-gate-v1.0.0.md`](release-gate-v1.0.0.md) — Version 1.0.0 Quality Gate
- [`release-artifact-inventory-v1.0.0.md`](release-artifact-inventory-v1.0.0.md) — GitHub / ZIP inventory
- [`test-report-m4-a.md`](test-report-m4-a.md) — M4-A test report
