# V1.20 Tag / GitHub Release

V1.20-GでSourceと正式Packageを`1.20.0`へ確定し、V1.20-F RC1の本番確認結果を引き継いで正式公開します。既存Tagは上書きしません。

正式Tagは`v1.20.0`です。

## 公開前Gate

1. `main`がV1.20.0正式SourceのCommitを指していることを確認する。
2. Remoteに`v1.20.0`が存在しないことを確認する。存在する場合は上書きせず公開を停止する。
3. `bash tests/run-current.sh`、V1.17〜V1.19互換Gate、`bash tests/run-v120g.sh`がPASSしていることを確認する。
4. `python tools/build_release_package.py --mode final`とRuntime verifierをPASSさせる。
5. Complete Source builder / verifierをPASSさせる。
6. Production／Complete ZIPのSHA-256を記録する。

## Git登録前

```powershell
# 現在Branchと変更状態を確認します。
git status

# 既存v1.20.0 TagがRemoteに存在しないことを確認します。
$ExistingTag = git ls-remote --tags origin refs/tags/v1.20.0
if ($ExistingTag) { throw "v1.20.0 already exists on origin. Do not overwrite it." }

# 変更内容を確認します。
git diff --check
git diff --stat
```

Stageする場合は`git status`と`git diff --name-status`で対象Pathを確定し、`git add -- <path...>`で必要Pathだけを登録します。`git add .`、`git add -A`、`git add --all`は使用しません。

## GitHub Release

Tag `v1.20.0`は正式Source Commitだけを指します。GitHub Releaseには次の4 Assetを添付します。

- `rss-reader-modernization-1.20.0.zip`
- `rss-reader-modernization-1.20.0.zip.sha256`
- `rss-reader-modernization-1.20.0-complete.zip`
- `rss-reader-modernization-1.20.0-complete.zip.sha256`

Release titleは`RSS Reader Modernization 1.20.0`、本文は`RELEASE_NOTES.md`を使用します。
