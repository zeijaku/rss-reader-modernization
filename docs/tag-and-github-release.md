# Git Tag / GitHub Release手順

GitHubへの正式反映は、Version 1.18.0 Release Gateと成果物確認後に実行します。

## 1. 差分とTest確認

```bash
git status --short
git diff --check
bash tests/run-current.sh
bash tests/run-v117.sh
bash tests/run-v1171.sh
bash tests/run-v1172.sh
bash tests/run-v118.sh
```

`config/local.php`、実Bearer Token、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものが意図せずStage対象になっていないことを確認します。

## 2. Release Artifact生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.18.0-complete.zip \
  ../release-output/rss-reader-modernization-1.18.0-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.18.0.zip \
  ../release-output/rss-reader-modernization-1.18.0.zip.sha256
```

## 3. PR / CI確認

Release branchのPull Requestで、PHP 8.1／8.4のCurrent Regression、V1.17 focused tests、V1.17.1／V1.17.2 compatibility tests、V1.18 focused testsがすべてGreenであることを確認します。

V1.18.0 Release workflowではRuntime／Complete packageのBuild／Verifierも実行し、生成ArtifactのSHA-256を控えます。

Connection Monitorは外部Credentialを必要としません。Production／Stagingでは同一Originの`connection_probe.php`、Offline→Recovery、複数Widget shared pollingをBrowserで確認します。

## 4. Merge

Release Gateと成果物確認が完了してから、`release/v1.18.0-final`を`main`へMergeします。

MergeはRelease準備とは分離し、明示的に承認してから実行します。

## 5. Annotated Tag

```bash
git status --short
git log -1 --oneline
git tag -a v1.18.0 -m "RSS Reader Modernization 1.18.0"
git show --no-patch --decorate v1.18.0
git push origin v1.18.0
```

TagはRelease commitと同じCommitを指すことを確認します。既存Tagがある場合は上書きしません。

## 6. GitHub Release

- Tag: `v1.18.0`
- Title: `RSS Reader Modernization 1.18.0`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.18.0.zip`
  - `rss-reader-modernization-1.18.0.zip.sha256`
  - `rss-reader-modernization-1.18.0-complete.zip`
  - `rss-reader-modernization-1.18.0-complete.zip.sha256`
- Pre-release: OFF
- Latest release: ON

## 7. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.18.0で一致する。
- SHA-256がRelease Gateで生成したArtifactと一致する。
- Runtime ZIPへPrivate設定、Bearer Token、X API Cache、その他Runtime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.18.0`。
- Connection MonitorがInformation catalogへ表示され、Online／Latency／History／Qualityを表示する。
- Offline→RecoveryとDowntime表示が動作する。
- 複数Connection MonitorでもPage全体のProbeが約5秒に1回である。
- GitHub ActionsがGreenのままである。
- Version 1.17.2からDB Migrationが不要であることを再確認する。
