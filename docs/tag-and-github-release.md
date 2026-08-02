# Git Tag / GitHub Release手順

## M4-Gでの扱い

Versionと正式ArtifactはM4-Gで確定します。Tag `v1.0.0` とGitHub Releaseは、利用者がM4-G commitをpushし、GitHub Actionsと公開内容を確認してから作成します。

## Tag作成前の必須条件

- M4-GのRelease commitがmainへpush済み。
- `app/version.php` が正式な `1.0.0`。
- `git status` がclean。
- Local全RegressionがFAIL 0。
- GitHub Actions PHP 8.1 / 8.4が成功。
- 実MySQL、実Feed、実Browser、Restore drillを確認するか、未収録範囲がRelease Notesに正しく記載されている。
- 正式Release ZIPとSHA-256を再生成済み。
- `tools/verify_release_package.py` がPASS。
- Release Notesが正式版で、Verification limitsを削除・誇張していない。

## Release commit確認

```powershell
git switch main
git pull --ff-only
git status --short
git log -1 --oneline
php tools/healthcheck.php
bash tests/run.sh
```

Version確認:

```powershell
php -r "require 'app/version.php'; echo APP_VERSION, PHP_EOL, APP_VERSION_LABEL, PHP_EOL;"
```

期待値:

```text
1.0.0
RSS Reader Modernization 1.0.0
```

## 正式Package生成・検証

```powershell
python tools/build_release_package.py --mode final --output-dir ..\release-output
python tools/verify_release_package.py `
  ..\release-output\rss-reader-modernization-1.0.0.zip `
  ..\release-output\rss-reader-modernization-1.0.0.zip.sha256
```

SHA-256を別Commandでも照合します。

```powershell
Get-FileHash ..\release-output\rss-reader-modernization-1.0.0.zip -Algorithm SHA256
Get-Content ..\release-output\rss-reader-modernization-1.0.0.zip.sha256
```

## Annotated Tag

TagはRelease commit SHAを確認してから作成します。作業中の未Commit状態へTagを付けません。

```powershell
$releaseCommit = git rev-parse HEAD
git tag -a v1.0.0 $releaseCommit -m "RSS Reader Modernization 1.0.0"
git show --no-patch --decorate v1.0.0
git rev-list -n 1 v1.0.0
git rev-parse HEAD
```

TagとHEADのSHAが一致することを確認してからpushします。

```powershell
git push origin v1.0.0
```

`git push --tags` は関係のないLocal Tagまで送る可能性があるため使用しません。

## GitHub Release

GitHubの **Releases** → **Draft a new release** から作成します。

```text
Tag: v1.0.0
Title: RSS Reader Modernization 1.0.0
Target: main上のRelease commit
```

本文は最終化した `RELEASE_NOTES.md` を基にします。次の2fileを添付します。

```text
rss-reader-modernization-1.0.0.zip
rss-reader-modernization-1.0.0.zip.sha256
```

公開前にDraft画面でTag、Commit、file名、SHA-256、Release Notesを再確認します。

## 誤ったTagへの対応

### Remoteへpushする前

Local Tagだけなら削除して、正しいRelease commitへ作り直せます。

```powershell
git tag -d v1.0.0
```

### Remoteへpushした後

公開済みTagを別Commitへ黙って移動しません。GitHub Releaseを公開済み、または第三者が取得済みの場合はTagを不変として扱い、修正版は `v1.0.1` 等の新しいVersionで出します。

Release公開前で誰も利用していないことを確認できる場合だけ、影響を確認してからRemote Tag削除を検討します。

```powershell
git push --delete origin v1.0.0
```

Force push、同名Tagの上書き、公開後のArtifact差替えを通常手順にしません。

## Release後確認

- GitHub ReleaseのTagとRelease commitが一致。
- ZIPとSHA-256 sidecarがDownload可能。
- Download後のSHA-256が一致。
- `RELEASE_BUILD.txt` が `package_status=FINAL`、`publishable=yes`。
- 展開後のFooterが `RSS Reader Modernization 1.0.0`。
- `config/local.php`、実DB、Log、Session、Cacheが含まれていない。
