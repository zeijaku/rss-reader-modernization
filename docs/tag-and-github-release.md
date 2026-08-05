# Git Tag / GitHub Release手順

GitHubへのCommit、Push、Tag、Releaseは成果物確認後に利用者が実行します。Tagは必ず`main`へ統合したVersion 1.4.0 Release Commitへ付けます。

## 1. Feature Branchの最終確認

```powershell
git switch feature/v1.4-mini-game-widget
git status
git diff --check
bash tests/run.sh
```

`config/local.php`、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものがStage対象になっていないことを確認します。

## 2. Release Branchを作成

```powershell
git switch -c release/v1.4.0
```

Version 1.4.0正式版FileをRelease Branchへ反映した後にCommitします。正式版Fileより先にTagを作成しません。

## 3. Release Artifact生成・検証

```powershell
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py `
  ../release-output/rss-reader-modernization-1.4.0-complete.zip `
  ../release-output/rss-reader-modernization-1.4.0-complete.zip.sha256
python tools/verify_release_package.py `
  ../release-output/rss-reader-modernization-1.4.0.zip `
  ../release-output/rss-reader-modernization-1.4.0.zip.sha256
```

## 4. Release BranchでCommit・Push

```powershell
git add -A
git status
git diff --cached --check
git commit -m "release: finalize version 1.4.0"
git push -u origin release/v1.4.0
```

## 5. mainへ統合

```powershell
git fetch origin --prune --tags
git switch main
git pull --ff-only origin main
git merge --ff-only release/v1.4.0
git push origin main
```

Fast-forwardできない場合は無理に`--force`せず、差分とBranch履歴を確認します。

## 6. mainのVersionとCommit確認

```powershell
git show main:app/version.php
git log -1 --oneline
git status
```

`APP_VERSION = '1.4.0'`で、Working TreeがCleanであることを確認します。

## 7. Annotated Tag

```powershell
git tag -a v1.4.0 -m "RSS Reader Modernization 1.4.0"
git show --no-patch --decorate v1.4.0
git push origin v1.4.0
git ls-remote --tags origin | Select-String "v1.4.0"
```

`refs/tags/v1.4.0^{}`が`main`のRelease Commitと一致することを確認します。

## 8. GitHub Release

- Tag: `v1.4.0`
- Title: `RSS Reader Modernization 1.4.0`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.4.0.zip`
  - `rss-reader-modernization-1.4.0.zip.sha256`
  - 必要に応じて`rss-reader-modernization-1.4.0-complete.zip`とSHA-256
- Pre-release: OFF
- Latest release: ON

## 9. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.4.0で一致する。
- `v1.4.0^{}`がmainのRelease Commitを指す。
- Runtime ZIPとComplete ZIPのSHA-256が手元のArtifactと一致する。
- Runtime ZIPへPrivate設定やRuntime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.4.0`。
- GitHub ActionsがPASSする。
