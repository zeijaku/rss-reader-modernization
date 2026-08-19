# Git Tag / GitHub Release手順

GitHubへの正式反映は、Version 1.17.2 Release Gateと成果物確認後に実行します。

## 1. 差分とTest確認

```bash
git status --short
git diff --check
bash tests/run-current.sh
bash tests/run-v117.sh
bash tests/run-v1171.sh
bash tests/run-v1172.sh
```

`config/local.php`、実Bearer Token、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものが意図せずStage対象になっていないことを確認します。

## 2. Release Artifact生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.17.2-complete.zip \
  ../release-output/rss-reader-modernization-1.17.2-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.17.2.zip \
  ../release-output/rss-reader-modernization-1.17.2.zip.sha256
```

## 3. PR / CI確認

Release branchのPull Requestで、PHP 8.1／8.4のCurrent Regression、V1.17 focused tests、V1.17.1 compatibility tests、V1.17.2 focused testsがすべてGreenであることを確認します。

V1.17.2 Release workflowではRuntime／Complete packageのBuild／Verifierも実行し、生成ArtifactのSHA-256を控えます。

X Timelineは外部X APIとPay Per Use環境に依存するため、実Bearer TokenをCIへ登録せず、Production／StagingのSmoke確認はCredentialを公開しない形で別途行います。

## 4. Merge

Release Gateと成果物確認が完了してから、`release/v1.17.2-final`を`main`へMergeします。

MergeはRelease準備とは分離し、明示的に承認してから実行します。

## 5. Annotated Tag

```bash
git status --short
git log -1 --oneline
git tag -a v1.17.2 -m "RSS Reader Modernization 1.17.2"
git show --no-patch --decorate v1.17.2
git push origin v1.17.2
```

TagはRelease commitと同じCommitを指すことを確認します。既存Tagがある場合は上書きしません。

## 6. GitHub Release

- Tag: `v1.17.2`
- Title: `RSS Reader Modernization 1.17.2`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.17.2.zip`
  - `rss-reader-modernization-1.17.2.zip.sha256`
  - `rss-reader-modernization-1.17.2-complete.zip`
  - `rss-reader-modernization-1.17.2-complete.zip.sha256`
- Pre-release: OFF
- Latest release: ON

## 7. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.17.2で一致する。
- SHA-256がRelease Gateで生成したArtifactと一致する。
- Runtime ZIPへPrivate設定、Bearer Token、X API Cache、その他Runtime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.17.2`。
- X Timeline追加Modalに「上級者向け機能」とBearer Token状態が表示される。
- Token未設定／Local形式不正ではX Timelineの追加が無効になる。
- 実X API取得成功後は現在Tokenが確認済みとなり、HTTP 401を受けた場合は認証失敗を案内する。
- GitHub ActionsがGreenのままである。
- Version 1.17.1からDB Migrationが不要であることを再確認する。
