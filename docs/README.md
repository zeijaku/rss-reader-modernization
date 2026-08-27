# Documentation

RSS Reader Modernization Project の現行ドキュメント索引です。

この索引は、現在の利用・運用・開発・Releaseで継続して参照する文書を中心にします。Stageごとの適用メモ、変更File一覧、確認Checklist、Test reportを恒久的に列挙する場所にはしません。過去の作業履歴はGit commit / tag / CHANGELOG / GitHub Releaseを正本とします。

## Getting started / operations

- [`installation.md`](installation.md) — 新規設置とDatabase準備
- [`update.md`](update.md) — 既存環境の更新手順
- [`configuration.md`](configuration.md) — Runtime設定、Default、制約
- [`backup-and-restore.md`](backup-and-restore.md) — Database / Private設定 / CodeのBackupと復旧
- [`rollback.md`](rollback.md) — Code / Configuration / DBのRollback
- [`deployment-checklist.md`](deployment-checklist.md) — 配置前後の確認
- [`security.md`](security.md) — Security model、設定、運用上の注意
- [`dependencies.md`](dependencies.md) — Frontend dependencyとLicense copyの対応

## Release / repository maintenance

- [`release-package.md`](release-package.md) — Runtime Release ZIPのBuild / Verify
- [`tag-and-github-release.md`](tag-and-github-release.md) — annotated TagとGitHub Release手順
- [`versioning.md`](versioning.md) — Versionの扱い
- [`ci.md`](ci.md) — GitHub Actions / CIの範囲
- [`github-publication.md`](github-publication.md) — GitHub公開時の確認
- [`documentation-policy.md`](documentation-policy.md) — 文書をRepositoryへ残す基準
- [`../CHANGELOG.md`](../CHANGELOG.md) — 変更履歴
- [`../RELEASE_NOTES.md`](../RELEASE_NOTES.md) — 現行Release Notes

## Architecture / modernization reference

- [`modernization.md`](modernization.md) — Secure Baseline SB-00〜15の実施内容
- [`roadmap.md`](roadmap.md) — Secure Baseline後のModernization計画
- [`change-map.md`](change-map.md) — Legacy issue → work unit → implementation → testの対応表
- [`v1-19-architecture.md`](v1-19-architecture.md) — API / Dashboard Widgetの大分類境界
- [`v1-19-public-endpoints.md`](v1-19-public-endpoints.md) — Public Endpoint Matrix
- [`v1-19-public-endpoint-matrix.csv`](v1-19-public-endpoint-matrix.csv) — Public Endpoint Matrix CSV
- [`v1-19-security-boundary.md`](v1-19-security-boundary.md) — Deployment / HTTP / Runtime Security境界
- [`v1-19-security-checklist.md`](v1-19-security-checklist.md) — 機能追加時のSecurity Checklist

## Current formal release record

- [`v1-22-0-release.md`](v1-22-0-release.md) — Version 1.22.0 Final Releaseの基準とFinal Gate

過去Version固有のRelease資料やCheckpoint資料は、現在の契約・設計・Security判断を説明するために必要なものだけRepositoryに残します。単なる適用手順、変更File一覧、実行済みChecklist、Test結果の転記はGit履歴から確認します。

## Security / legacy evidence

- [`sensitive-data-manifest.md`](sensitive-data-manifest.md) — Repositoryへ含める/含めないデータ方針
- [`initial-commit-gate.md`](initial-commit-gate.md) — Initial Commit判定と公開前確認事項
- [`legacy-analysis.md`](legacy-analysis.md) — Legacy版の解析結果と対応方針
- [`legacy/README.md`](legacy/README.md) — Legacy evidenceの扱い
- [`legacy/legacy-tree-manifest.txt`](legacy/legacy-tree-manifest.txt) — Legacy tree記録
- [`legacy/source-sha256.txt`](legacy/source-sha256.txt) — Primary evidence hash記録

## Historical engineering records

Secure Baseline、M4、V1.1以降のImplementation / Migration / Test記録の一部は、設計判断やSecurity上の背景を追跡するためRepositoryに残しています。ただし、これらを現行手順として扱わないでください。

過去時点の正確な状態を確認する場合は、該当commitまたはtagをcheckoutして参照してください。現在の設置・更新・Release作業では、上記の現行ドキュメントを使用します。

## Documentation lifecycle

新しいStageを開始するたびに `APPLY_NOTE_*`、`CHECKLIST_FOR_USER_*`、`UPDATED_FILES_*`、`*_TEST_REPORT.md` を追加する運用は行いません。継続して必要な内容だけ、既存のREADME / CHANGELOG / RELEASE_NOTES / docsへ統合します。

詳細は [`documentation-policy.md`](documentation-policy.md) を参照してください。
