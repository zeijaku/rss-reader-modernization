# V1.19 Tag / GitHub Release

V1.19-FでSourceと正式Packageを確定しますが、Commit / Push / Tag / GitHub Releaseはユーザーの明示的なGitHub反映指示があるまで実行しません。

想定する正式Tagは`v1.19.0`です。既存Tagは上書きしません。

## Git登録前

```powershell
# 現在Branchと変更状態を確認します。
git status

# 既存v1.19.0 TagがRemoteに存在しないことを確認します。
$ExistingTag = git ls-remote --tags origin refs/tags/v1.19.0
if ($ExistingTag) { throw "v1.19.0 already exists on origin. Do not overwrite it." }

# 変更内容を確認します。
git diff --check
git diff --stat
```

## Commit / Push

実際のGitHub反映時は、まず`git status`と`git diff --name-status`でV1.19対象Pathを確定します。その確認結果を基に**対象Pathだけ**を`git add -- <path...>`でStageし、無関係なLocal変更を含めません。`git add .`、`git add -A`、`git add --all`は使用しません。

Stage後は次を確認します。

```powershell
# Stage済み差分にWhitespace Errorがないことを確認します。
git diff --cached --check

# StageしたFile一覧と差分量を確認します。
git diff --cached --stat
```

Commit / Pushコマンドは、実際の差分確認後にユーザーから明示的なGitHub反映指示があった時点で確定します。

## Tag

```powershell
# Push後のHEADを確認します。
git log -1 --oneline

# Annotated Tagを作成します。
git tag -a v1.19.0 -m "RSS Reader Modernization 1.19.0"

# TagだけをRemoteへPushします。
git push origin refs/tags/v1.19.0
```

GitHub Releaseを作成する場合は、Tagが意図したCommitを指していることを確認した後、正式Runtime ZIP、Runtime SHA-256、Complete ZIP、Complete SHA-256を添付します。
