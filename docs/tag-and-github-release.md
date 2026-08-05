# Git Tag / GitHub Release手順

GitHubへのCommit、Push、Tag、Releaseは成果物確認後に利用者が実行します。作業BranchをPushしただけではGitHubの既定Branch`main`は更新されません。Tagは必ず`main`へ統合したRelease Commitへ付けます。

## 1. 現在地と差分確認

```powershell
git status
git branch --show-current
git diff --check
bash tests/run.sh
```

`config/local.php`、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものがStage対象になっていないことを確認します。

## 2. Release Artifact生成

```powershell
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py `
  ../release-output/rss-reader-modernization-1.3.0-complete.zip `
  ../release-output/rss-reader-modernization-1.3.0-complete.zip.sha256
python tools/verify_release_package.py `
  ../release-output/rss-reader-modernization-1.3.0.zip `
  ../release-output/rss-reader-modernization-1.3.0.zip.sha256
```

## 3. Release BranchでCommit

```powershell
git add -A
git status
git diff --cached --check
git commit -m "release: finalize version 1.3.0"
git push -u origin HEAD
```

## 4. mainへ統合

```powershell
git fetch origin --prune --tags
git switch main
git pull --ff-only origin main
git merge --ff-only <V1.3の作業Branch名>
git push origin main
```

Fast-forwardできない場合は無理に`--force`せず、差分とBranch履歴を確認します。

## 5. mainのVersionとCommit確認

```powershell
git show main:app/version.php
git log -1 --oneline
git status
```

`APP_VERSION = '1.3.0'`で、Working TreeがCleanであることを確認します。

## 6. Annotated Tag

```powershell
git tag -a v1.3.0 -m "RSS Reader Modernization 1.3.0"
git show --no-patch --decorate v1.3.0
git push origin v1.3.0
git ls-remote --tags origin | Select-String "v1.3.0"
```

注釈付きTagでは`refs/tags/v1.3.0^{}`が`git log -1`と同じRelease Commitを指すことを確認します。誤ったCommitへ同名Tagを作成した場合は、Remote Tagを削除して正しいCommitへ作り直します。

## 7. GitHub Release

- Tag: `v1.3.0`
- Title: `RSS Reader Modernization 1.3.0`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.3.0.zip`
  - `rss-reader-modernization-1.3.0.zip.sha256`
  - 必要に応じて`rss-reader-modernization-1.3.0-complete.zip`とSHA-256
- Pre-release: OFF
- Latest release: ON

## 8. 公開後確認

- GitHubトップのBranch表示が`main`である。
- main、Tag、GitHub ReleaseのVersionが1.3.0で一致する。
- `v1.3.0^{}`がmainのRelease Commitを指す。
- SHA-256が手元のArtifactと一致する。
- Runtime ZIPへPrivate設定やRuntime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.3.0`。
- GitHub ActionsがPASSする。
