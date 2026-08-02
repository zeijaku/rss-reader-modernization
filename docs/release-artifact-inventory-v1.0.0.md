# Version 1.0.0 公開物・配布物Inventory

## GitHub Repositoryへ含める

Application source、sanitized schema / Migration / fixture、test、README、CHANGELOG、RELEASE_NOTES、LICENSE、THIRD_PARTY_NOTICES、license copy、公開Documentation、example設定、空Runtime directoryの`.gitkeep`、Release package builder / verifier。

## Runtime Release ZIPへ含める

Application実行に必要なfile、設定example、DB schema / Migration、公開Documentation一式、Security資料、LICENSE、THIRD_PARTY_NOTICES、license copy、Release Notes、Release build metadata、Package Manifest。

M4-E以降のRuntime Release ZIP allowlistは `tools/build_release_package.py` と [`release-package.md`](release-package.md) で固定する。

## Runtime Release ZIPへ含めない

`.git/`、`.github/`、`.gitignore`、`tests/`、Checkpoint Checklist、過去Checkpoint package manifest、作業用diff記録。

## Checkpoint ZIPだけに含めてもよい

`CHECKLIST_FOR_USER.md`、作業者向けの更新file一覧、M4実装記録、M4 test report、全Regression test。

## Git・ZIP・Manifest例へ含めない

`config/local.php`、real `.env`、実DB接続情報、Password、Token、秘密鍵、実DB、DB Backup、Log、Session、Runtime Cache / Lock / State、Legacy `rss.zip`、`rss.sql`、入れ子ZIP、個人情報。

## M4-Aでの注意

GitHub mainとM2-G引渡しZIPの構成が完全には同一でなかったため、M4-A ZIPにはGitHub mainの公開License fileを復元した。Third-party Version表記の更新はM4-Bで行った。

## M4-Cで追加した運用資料

Runtime Release ZIPへ `installation.md`、`update.md`、`configuration.md`、`backup-and-restore.md`、`rollback.md`、`deployment-checklist.md` を含める。実値のPrivate設定、DB dump、Backup archiveは含めない。

## M4-Dで追加した公開Repository資料

GitHub Repositoryへ `.github/workflows/ci.yml`、Issue template、`SECURITY.md`、`CONTRIBUTING.md`、CI / Repository / Portfolio資料を含める。WorkflowはSecret、Deploy、Release artifactを扱わない。

`.github/`とPortfolio資料はRepository向けであり、Runtime Release ZIPからは除外する。

## M4-Eで追加したRelease Artifact

M4-E Preview:

```text
rss-reader-modernization-1.0.0-preview-m4-e.zip
rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
```

Preview ZIP内部:

```text
RELEASE_BUILD.txt
RELEASE_MANIFEST.sha256
RELEASE_NOTES.md
```

Previewは `publishable=no`。M4-FでRCを再Buildし、M4-Gで正式な次のArtifactを再Buildする。

```text
rss-reader-modernization-1.0.0.zip
rss-reader-modernization-1.0.0.zip.sha256
```

正式ArtifactへCheckpoint ZIPやPreview ZIPを入れず、入れ子ZIPを作らない。

## M4-F Release Candidate

```text
rss-reader-modernization-1.0.0-rc1.zip
rss-reader-modernization-1.0.0-rc1.zip.sha256
```

RCは `package_status=RELEASE_CANDIDATE`、`publishable=no`。`docs/m4-f-validation-template.json` は空の安全なTemplateとしてRelease ZIPへ含める。実際のEvidenceは `var/m4f-evidence/` へ保存し、RepositoryとRelease ZIPへ含めない。


## M4-G Final Release

```text
rss-reader-modernization-1.0.0.zip
rss-reader-modernization-1.0.0.zip.sha256
```

Final ZIPは`package_status=FINAL`、`publishable=yes`。Repository用Checkpoint、RC ZIP、Preview ZIP、Private Evidenceを内部へ入れない。`publishable=yes`はPackageの正式版境界であり、実環境EvidenceはRelease NotesのVerification limitsで別に扱う。
