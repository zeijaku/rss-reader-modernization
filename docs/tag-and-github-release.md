# Tag and GitHub Release Procedure

## Current formal release

- Version: `1.23.0`
- Tag: `v1.23.0`

V1.23-E以降は、VersionごとのRelease workflowや `release/vX.Y.Z-final` branchを標準手順として増やしません。

正式Releaseは、release-readyなCommitを `main` に反映した後、共通 `.github/workflows/release.yml` を使用します。

## Safety rules

- 既存の正式Release tagは移動・上書きしない。
- `git tag -f` / `git push --force` をRelease手順で使用しない。
- Tagは、Release gateを通過した `main` の完全に同じCommitを指す。
- Release実行中にRemote `main` が別Commitへ進んだ場合は公開を停止する。
- 既存Tagが別Commitを指している場合は公開を停止する。
- 同じCommitを指すTagは再実行時に利用してよい。
- 既存GitHub Releaseは再作成・Asset差し替え・上書きを行わない。
- `config/local.php`、runtime data、secret、private archiveはRelease assetへ含めない。

## Release-ready source

Release workflowはSourceを自動修正・自動commitしません。

正式Release実行前に、少なくとも次を同じVersionへ揃えます。

- `app/version.php`
  - `APP_VERSION`
  - `APP_VERSION_LABEL`
  - `APP_ASSET_REVISION`
- READMEのStable release / Release tag
- CHANGELOGの正式Version entry
- RELEASE_NOTES

`tools/check_release_ready.py --release X.Y.Z` がこれらを独立した入力Versionと照合します。

## GitHub Actionsでの実行

### A. Run workflowが使用できる場合

1. release-ready commitを `main` へ反映する。
2. `main` のCI結果を確認する。
3. GitHubの **Actions** → **Release** を開く。
4. Run workflowの対象branchとして `main` を選択する。
5. `version` に正式Versionを `X.Y.Z` 形式で入力する。
6. Release workflowを実行する。

### B. Browser-only fallback

Run workflowがGitHub UIに表示されない場合や、ローカルGit / GitHub CLIがない環境では `.github/release-request.txt` を使用します。

1. release-ready commitを `main` へ反映する。
2. `main` のCI結果を確認する。
3. GitHubのCode画面で `.github/release-request.txt` を開く。
4. 内容を対象Version `X.Y.Z` の1行だけへ変更する。
5. `main` へcommitする。Branch protection有効時はPull Request経由でmergeする。
6. `.github/release-request.txt` のmain pushだけを契機に同じRelease workflowが起動する。

通常のApplication code pushではRelease workflowは起動しません。

Release workflowは、Regression、secret scan、package build / verify、SHA-256、clean-room確認を完了した後でのみTagとGitHub Releaseを作成します。

## Browser request safety

`.github/release-request.txt` の変更はRelease要求として扱いますが、ファイル変更だけで無条件公開はしません。

- 内容が正式SemVer `X.Y.Z` でない場合は停止
- `app/version.php` / README / CHANGELOG / RELEASE_NOTESと不一致なら停止
- `main` SHAが実行中に動いた場合は停止
- 既存Tagが別Commitなら停止
- PHP 8.1 / 8.4 Current regression、secret scan、package verify、clean-room確認を通過してから公開

このため、ブラウザーだけの操作でも既存のRelease安全条件は維持されます。

## Immutable tag behavior

公開直前にRemote Tagを再確認します。

- Tagなし
  - 現在の `GITHUB_SHA` へannotated tagを作成
- Tagあり / 同じCommit
  - Tagは変更せず続行
- Tagあり / 別Commit
  - 即時失敗

Tag作成後にGitHub Release作成だけが失敗した場合は、Source Commitが変わっていなければ再実行できます。既存Tagは同じCommitとして検証され、force updateされません。

## Existing GitHub Release

`gh release view` で同じTagのGitHub Releaseが既に存在する場合は、Release本文やAssetを変更しません。

これは「再実行で既存正式Releaseが別内容になる」ことを防ぐためです。

## Formal assets

Versionを `X.Y.Z` とした場合、正式Assetは次の4つです。

- `rss-reader-modernization-X.Y.Z.zip`
- `rss-reader-modernization-X.Y.Z.zip.sha256`
- `rss-reader-modernization-X.Y.Z-complete.zip`
- `rss-reader-modernization-X.Y.Z-complete.zip.sha256`

Runtime ZIPはProduction配置向け、Complete ZIPはRepository / Tests / current workflowを含むSource保管向けです。
