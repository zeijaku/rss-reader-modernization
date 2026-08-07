# Version 1.7.0 GitHub登録手順 — PowerShell

この手順は、`rss-reader-modernization-1.7.0-complete.zip`をGitHubの`zeijaku/rss-reader-modernization`へ登録するためのものです。Server配置用`rss-reader-modernization-1.7.0.zip`はSource登録には使用しません。

## 0. 使用するFolder

次の構成を推奨します。

```text
C:\git\rss-reader-v1.7.0\
├─ package\   ← Complete ZIP展開先
└─ repo\      ← Git clone先。ここだけがGit Repository
```

Complete ZIPを例えば`Downloads`へ保存した場合、最初にPowerShellを開いて次を実行します。

```powershell
$WorkRoot = 'C:\git\rss-reader-v1.7.0'
$PackageExtract = Join-Path $WorkRoot 'package'
$RepoRoot = Join-Path $WorkRoot 'repo'
$ZipPath = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.7.0-complete.zip'

New-Item -ItemType Directory -Force -Path $WorkRoot, $PackageExtract | Out-Null
Expand-Archive -LiteralPath $ZipPath -DestinationPath $PackageExtract -Force

$PackageRoot = Join-Path $PackageExtract 'rss-reader-modernization-1.7.0-complete'
Test-Path (Join-Path $PackageRoot 'app\version.php')
```

最後が`True`であることを確認します。

**以後Git commandを実行する場所は`C:\git\rss-reader-v1.7.0\repo`です。** `package` Folder内ではGit commandを実行しません。

## 1. RepositoryをClone

新しい作業Folderを使うため、既存のGit作業Folderを壊しません。

```powershell
Set-Location $WorkRoot

git clone https://github.com/zeijaku/rss-reader-modernization.git $RepoRoot
Set-Location $RepoRoot

git rev-parse --show-toplevel
git remote -v
git status
```

`origin`が`zeijaku/rss-reader-modernization`を指し、Working TreeがCleanであることを確認します。

## 2. V1.7 Branchを最新化

既存`feature/v1.7-modernization`を使用します。

```powershell
git fetch origin --prune --tags
git switch feature/v1.7-modernization
git pull --ff-only origin feature/v1.7-modernization
git status
```

ここでErrorが出た場合は先へ進まず履歴を確認します。`--force`は使用しません。

## 3. Complete ZIPのSourceをRepositoryへ反映

`robocopy /MIR`でComplete SourceをBranchへ同期します。`.git`は明示的に除外します。

```powershell
robocopy $PackageRoot $RepoRoot /MIR /XD '.git' /R:1 /W:1
if ($LASTEXITCODE -gt 7) {
    throw "robocopy failed. ExitCode=$LASTEXITCODE"
}
Set-Location $RepoRoot
```

Versionを確認します。

```powershell
Get-Content .\app\version.php
Select-String -Path .\app\version.php -Pattern "APP_VERSION = '1.7.0'"
Select-String -Path .\app\version.php -Pattern "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'"
```

Migration 007／008がRepositoryへ反映対象として見えることも確認します。

```powershell
git status --short -- .\database\migrations\007_v1_7_remember_token.sql
git status --short -- .\database\migrations\008_v1_7_widget_height.sql
git status --short
```

## 4. Commit前確認

```powershell
git diff --check
git diff --stat
git status --short
```

`config/local.php`、実DB、Log、Session、Cache、ZIPが表示されていないことを確認します。

次にStageします。

```powershell
git add -A
git diff --cached --check
git diff --cached --stat
git status --short
```

Migration 007／008とV1.7 Audit SQLがStageされていることを確認してください。

## 5. Version 1.7.0 Release Commit

```powershell
git commit -m "release: finalize version 1.7.0"
git status
git log -1 --oneline --decorate
```

Working TreeがCleanであることを確認してPushします。

```powershell
git push origin feature/v1.7-modernization
```

## 6. mainへFast-forward

```powershell
git switch main
git pull --ff-only origin main
git merge --ff-only feature/v1.7-modernization
```

`git merge --ff-only`が失敗した場合は**Pushせず停止**します。履歴が想定外に分岐しています。

成功した場合だけ、VersionとCommitを確認します。

```powershell
Get-Content .\app\version.php
git log -3 --oneline --decorate
git status
```

問題なければPushします。

```powershell
git push origin main
```

## 7. v1.7.0 Tag

既存Tagがないことを確認します。

```powershell
$ExistingTag = git ls-remote --tags origin refs/tags/v1.7.0
if ($ExistingTag) {
    throw 'v1.7.0 already exists on origin. Do not overwrite or move it.'
}
```

`main`がRelease Commitを指している状態でAnnotated Tagを作成します。

```powershell
git tag -a v1.7.0 -m "RSS Reader Modernization 1.7.0"
git show --no-patch --decorate v1.7.0
git push origin v1.7.0
git ls-remote --tags origin | Select-String 'refs/tags/v1.7.0'
```

既存Tagの削除・移動・Force pushは行いません。

## 8. GitHub Release — 任意

GitHub CLI `gh`がInstall済みなら、Release Artifactを添付できます。次はComplete ZIPとRuntime ZIPをDownloadsへ保存した例です。

```powershell
$CompleteZip = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.7.0-complete.zip'
$CompleteSha = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.7.0-complete.zip.sha256'
$RuntimeZip = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.7.0.zip'
$RuntimeSha = Join-Path $env:USERPROFILE 'Downloads\rss-reader-modernization-1.7.0.zip.sha256'

Set-Location $RepoRoot
gh release create v1.7.0 `
  $RuntimeZip $RuntimeSha $CompleteZip $CompleteSha `
  --repo zeijaku/rss-reader-modernization `
  --title 'RSS Reader Modernization 1.7.0' `
  --notes-file .\docs\github-release-notes-v1.7.0.md
```

`gh`を使用しない場合はGitHub Web画面からTag `v1.7.0`を選び、同じTitle／Release Notes／4 Artifactを登録します。

## 9. 最終確認

```powershell
Set-Location $RepoRoot
git switch main
git status
git log -1 --oneline --decorate
git tag --list 'v1.7.0'
git ls-remote origin refs/heads/main refs/tags/v1.7.0
```

GitHub Web上でも、mainの`app/version.php`が`1.7.0`、Tagが`v1.7.0`、GitHub ActionsがPASSであることを確認します。
