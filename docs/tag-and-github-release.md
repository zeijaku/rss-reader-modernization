# Git Tag / GitHub Release手順

GitHubへの正式反映は、Version 1.17.1 Release Gateと成果物確認後に実行します。

## 1. 差分とTest確認

```bash
git status --short
git diff --check
bash tests/run-current.sh
bash tests/run-v117.sh
bash tests/run-v1171.sh
```

`config/local.php`、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものが意図せずStage対象になっていないことを確認します。

## 2. Release Artifact生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.17.1-complete.zip \
  ../release-output/rss-reader-modernization-1.17.1-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.17.1.zip \
  ../release-output/rss-reader-modernization-1.17.1.zip.sha256
```

## 3. PR / CI確認

Release branchのPull Requestで、PHP 8.1／8.4のCurrent Regression、V1.17 focused tests、V1.17.1 focused testsがすべてGreenであることを確認します。

Runtime／Complete packageのBuild／VerifierもGreenであることを確認し、生成ArtifactのSHA-256を控えます。

## 4. Merge

Release Gateと成果物確認が完了してから、`release/v1.17.1-final`を`main`へMergeします。

MergeはRelease準備とは分離し、明示的に承認してから実行します。

## 5. Annotated Tag

```bash
git status --short
git log -1 --oneline
git tag -a v1.17.1 -m "RSS Reader Modernization 1.17.1"
git show --no-patch --decorate v1.17.1
git push origin v1.17.1
```

TagはRelease commitと同じCommitを指すことを確認します。既存Tagがある場合は上書きしません。

## 6. GitHub Release

- Tag: `v1.17.1`
- Title: `RSS Reader Modernization 1.17.1`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.17.1.zip`
  - `rss-reader-modernization-1.17.1.zip.sha256`
  - `rss-reader-modernization-1.17.1-complete.zip`
  - `rss-reader-modernization-1.17.1-complete.zip.sha256`
- Pre-release: OFF
- Latest release: ON

## 7. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.17.1で一致する。
- SHA-256がRelease Gateで生成したArtifactと一致する。
- Runtime ZIPへPrivate設定やRuntime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.17.1`。
- GitHub ActionsがGreenのままである。
- Version 1.17.0からDB Migration／必須config追加がないことを再確認する。
