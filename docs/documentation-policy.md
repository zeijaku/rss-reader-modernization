# Documentation policy

このRepositoryでは、実装履歴そのものはGit commit / tag / CHANGELOGを正本とし、Stageごとの一時的な引き渡し資料を恒久的に増やし続けない。

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

今後のA/B/C/D等のCheckpointでは、`APPLY_NOTE_*`、`CHECKLIST_FOR_USER_*`、`UPDATED_FILES_*`、`*_TEST_REPORT.md`をCheckpointごとに複数作成する運用は行わない。

必要な内容は原則として以下へ集約する。

- 実装履歴: Git commit / pull request
- 利用者向け変更: `CHANGELOG.md`
- 正式Release情報: `RELEASE_NOTES.md`
- 継続して参照する設計・Security・運用情報: `docs/`
- 一時的な本番確認手順: 配布ZIP内READMEまたはPR本文

## Historical checkpoint files

既存のCheckpoint資料のうち、現行コード・テスト・Manifest・他ドキュメントから参照されないものはRepositoryから整理してよい。削除後もGit履歴から確認できる。

過去Releaseの互換テストやManifestが直接参照する資料は、そのGate自体を整理するまでは残す。ファイル数だけを減らす目的で参照切れを作らない。

## New documentation

新しいMarkdownを追加する前に、既存の`README.md`、`CHANGELOG.md`、`RELEASE_NOTES.md`、または既存`docs/`文書へ追記できないかを先に確認する。
