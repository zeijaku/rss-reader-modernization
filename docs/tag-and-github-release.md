# Git Tag / GitHub Release手順

GitHubへの正式反映は、Release Gateと成果物確認後に実行します。

## 1. 差分とTest確認

```bash
git status --short
git diff --check
bash tests/run.sh
```

`config/local.php`、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものが意図せずStage対象になっていないことを確認します。

## 2. Release Artifact生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.14.0.zip \
  ../release-output/rss-reader-modernization-1.14.0.zip.sha256
```

## 3. Commit / Push

```bash
git add -A
git status --short
git diff --cached --check
git commit -m "release: finalize version 1.14.0"
git push origin main
```

## 4. Annotated Tag

```bash
git status --short
git log -1 --oneline
git tag -a v1.14.0 -m "RSS Reader Modernization 1.14.0"
git show --no-patch --decorate v1.14.0
git push origin v1.14.0
```

TagはRelease commitと同じCommitを指すことを確認します。

## 5. GitHub Release

- Tag: `v1.14.0`
- Title: `RSS Reader Modernization 1.14.0`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.14.0.zip`
  - `rss-reader-modernization-1.14.0.zip.sha256`
  - 必要に応じて`rss-reader-modernization-1.14.0-complete.zip`とSHA-256
- Pre-release: OFF
- Latest release: ON

## 6. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.14.0で一致する。
- SHA-256が手元のArtifactと一致する。
- Runtime ZIPへPrivate設定やRuntime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.14.0`。
- GitHub ActionsがPASSする。
