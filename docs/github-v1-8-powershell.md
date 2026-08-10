# Version 1.8.0 GitHub登録手順 — PowerShell

この手順は`rss-reader-modernization-1.8.0-complete.zip`を`zeijaku/rss-reader-modernization`へ登録するためのものです。Server配置用`rss-reader-modernization-1.8.0.zip`はSource登録には使用しません。

## 0. Folder

```text
C:\git\rss-reader-v1.8.0\
├─ package\   ← Complete ZIP展開先
└─ repo\      ← Git clone先
```

```powershell
# 作業Folderを定義します。
$WorkRoot = 'C:\git\rss-reader-v1.8.0'
$PackageExtract = Join-Path $WorkRoot 'package'
$RepoRoot = Join-Path $WorkRoot 'repo'
$ZipPath = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.8.0-complete.zip'

# Complete ZIPをGit Repositoryとは別Folderへ展開します。
New-Item -ItemType Directory -Force -Path $WorkRoot, $PackageExtract | Out-Null
Expand-Archive -LiteralPath $ZipPath -DestinationPath $PackageExtract -Force
$PackageRoot = Join-Path $PackageExtract 'rss-reader-modernization-1.8.0-complete'
Test-Path (Join-Path $PackageRoot 'app\version.php')
```

## 1. RepositoryをClone

```powershell
# GitHubの現在のmainを新しいFolderへCloneします。
Set-Location $WorkRoot
git clone https://github.com/zeijaku/rss-reader-modernization.git $RepoRoot
Set-Location $RepoRoot

# RepositoryとWorking Treeを確認します。
git remote -v
git status
git fetch origin --prune --tags
```

## 2. Version 1.8 Branch

```powershell
# mainをVersion 1.7.0の最新状態へ合わせます。
git switch main
git pull --ff-only origin main

# V1.8 Branchが既にある場合は切替、無い場合はmainから作成します。
$RemoteBranch = git ls-remote --heads origin refs/heads/feature/v1.8-stock
if ($RemoteBranch) {
    git switch feature/v1.8-stock
    git pull --ff-only origin feature/v1.8-stock
} else {
    git switch -c feature/v1.8-stock
}
```

## 3. Complete Sourceを同期

```powershell
# Complete ZIPの内容をBranchへ同期します。.gitは触りません。
robocopy $PackageRoot $RepoRoot /MIR /XD '.git' /R:1 /W:1
if ($LASTEXITCODE -gt 7) {
    throw "robocopy failed. ExitCode=$LASTEXITCODE"
}
Set-Location $RepoRoot

# Version 1.8.0のMarkerを確認します。
Get-Content .\app\version.php
Select-String -Path .\app\version.php -Pattern "APP_VERSION = '1.8.0'"
Select-String -Path .\app\version.php -Pattern "APP_VERSION_LABEL = 'RSS Reader Modernization 1.8.0'"
```

## 4. Commit前確認

```powershell
# 不正な空白、変更量、Private file混入を確認します。
git diff --check
git diff --stat
git status --short

# 全変更をStageし、Stage後も同じ確認を行います。
git add -A
git diff --cached --check
git diff --cached --stat
git status --short
```

`config/local.php`、実DB、Log、Session、Cache、ZIPがStageされていないことを確認します。

## 5. Version 1.8.0 Release Commit

```powershell
# Version 1.8.0を1つのRelease Commitとして記録します。
git commit -m "release: finalize version 1.8.0"
git status
git log -1 --oneline --decorate

# 確認後、V1.8 BranchをPushします。
git push -u origin feature/v1.8-stock
```

## 6. mainへFast-forward

```powershell
# mainを最新化し、分岐が無い場合だけFast-forwardします。
git switch main
git pull --ff-only origin main
git merge --ff-only feature/v1.8-stock
```

`--ff-only`が失敗した場合はPushせず停止してください。

```powershell
# main上のVersionと履歴を確認してからPushします。
Get-Content .\app\version.php
git log -3 --oneline --decorate
git status
git push origin main
```

## 7. v1.8.0 Tag

```powershell
# 既存Tagがある場合は上書きせず停止します。
$ExistingTag = git ls-remote --tags origin refs/tags/v1.8.0
if ($ExistingTag) {
    throw "v1.8.0 already exists on origin. Do not overwrite it."
}

# mainのRelease CommitへAnnotated Tagを付けます。
git tag -a v1.8.0 -m "RSS Reader Modernization 1.8.0"
git show --no-patch --decorate v1.8.0
git push origin v1.8.0
```

Force push、既存Tag削除／移動は行いません。
