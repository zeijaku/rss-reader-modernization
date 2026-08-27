# Documentation policy

このRepositoryでは、実装履歴そのものはGit commit / tag / CHANGELOG / GitHub Releaseを正本とし、Stageごとの一時的な引き渡し資料を恒久的に増やし続けない。

## Root directory

Rootに置くMarkdownは、原則として次の公開・運用ドキュメントに限定する。

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `SECURITY.md`
- `CONTRIBUTING.md`
- `THIRD_PARTY_NOTICES.md`
- 現行CIやRelease手順が明示的に必要とする互換資料

## Development checkpoints

今後のA/B/C/D等のCheckpointでは、`APPLY_NOTE_*`、`CHECKLIST_FOR_USER_*`、`UPDATED_FILES_*`、`*_TEST_REPORT.md`をCheckpointごとに作成する運用は行わない。

必要な内容は原則として以下へ集約する。

- 実装履歴: Git commit / pull request
- 利用者向け変更: `CHANGELOG.md`
- 正式Release情報: `RELEASE_NOTES.md` / GitHub Release
- 継続して参照する設計・Security・運用情報: `docs/`
- 一時的な本番確認手順: pull request本文またはRelease作業時の一時メモ

## Historical checkpoint files

既存のCheckpoint資料のうち、現行コード・テスト・Workflow・Manifest・他ドキュメントから参照されず、内容がGit履歴で代替できるものはRepositoryから整理してよい。削除後もGit履歴と該当tagから確認できる。

一方、次の資料は単に古いという理由では削除しない。

- 過去Releaseとの互換テストが直接参照する資料
- Migration / schema / Release contractの識別に必要な資料
- Security境界や設計判断を説明する資料
- Legacy evidenceや公開前監査の正当性を説明する資料

## Archive policy

`docs/archive/` を過去資料の退避先として無制限に使用しない。移動しただけで不要資料が増え続ける状態を避ける。

また、現行のRuntime package builderは `docs/` 以下を再帰的に収集するため、archiveを導入する場合は先にRelease packageの収集範囲を明示化し、historical資料がRuntime ZIPへ意図せず入らないことを確認する。

それまでは、保存価値が明確な資料は現在の場所に残し、Git履歴で十分な一時資料は削除する方針を優先する。

## Current documentation

現在の利用者・運用者・開発者が継続して参照する文書は [`README.md`](README.md) の索引に集約する。過去Checkpoint文書をすべて索引へ列挙し続けない。

Version固有のRelease文書は、現行Release contractまたは互換性確認に必要なものを残す。過去時点の詳細を調べる場合は、該当tag / GitHub Release / Git履歴を使用する。

## New documentation

新しいMarkdownを追加する前に、既存の`README.md`、`CHANGELOG.md`、`RELEASE_NOTES.md`、または既存`docs/`文書へ追記できないかを先に確認する。

一時資料をどうしても作成する場合は、正式化前にRepositoryへ残す理由を再確認する。
