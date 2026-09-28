# GitHub Actions CI

## Purpose

Current GitHub Actionsは、Version固有Workflowを増やさず次の2本を標準とします。

- `.github/workflows/ci.yml`
  - 現在のApplication Contractを継続的に検証するCI
- `.github/workflows/release.yml`
  - 正式Release時だけ実行する共通Release Workflow

過去Versionで使用したFocused Check / Release GateはGit履歴・各Release tagのHistorical Evidenceとして保持し、現在の `.github/workflows/` へ戻しません。

## Current CI

Trigger:

- `main` へのpush
- `main` 向けPull Request
- `workflow_dispatch`

`pull_request_target` は使用しません。Workflow全体のPermissionは `contents: read` に限定し、Repositoryへ書込みません。Application Secretも参照しません。

Runtime Matrix / Tooling:

- PHP 8.1
- PHP 8.4
- Python 3.12
- Node.js 20
- PHP extension: curl、mbstring、pdo_mysql、pdo_sqlite、simplexml

CIは次を実行します。

```bash
bash tests/run-ci.sh
```

`tests/run-ci.sh` はCurrent Contract向けの標準Gateです。Current hygiene、Current regression、Current feature regressionをまとめて実行します。

主なMaintenance Guard:

- `tests/test_version_dependency_hygiene.py`
  - Current-following testへの古いVersion / Asset Revision固定の再混入を検出
- `tests/test_workflow_hygiene.py`
  - 現役Workflowを `ci.yml` / `release.yml` に限定
  - Version固有WorkflowやVersion固定Release Branch運用の再混入を検出
  - Release Trigger / Permission / Action pinning等のCurrent Contractを確認
- `tests/test_release_flow.py`
  - 共通Release WorkflowのVersion非依存性
  - Tag上書き禁止
  - 既存GitHub Release非変更
  - Package Build / Verify / clean-room / Attestation Flowを確認

過去Version固有のTestは削除しません。Release当時のimmutable contractを確認するHistorical Testとして残しますが、Current CIの通常Gateには含めません。

## Standard Release Workflow

`.github/workflows/release.yml` は次の2経路で起動します。

1. `workflow_dispatch`
   - GitHub Actions画面、GitHub CLI、APIから実行
   - `version` を `X.Y.Z` 形式で明示入力
2. browser-only fallback
   - `main` 上の `.github/release-request.txt` が変更されたpush
   - File内容をRelease Versionとして使用

通常のApplication Code pushだけではRelease Workflowは起動しません。

Release WorkflowはSourceを書き換えたり、自動Commitしたりしません。実行前にSourceをrelease-readyな状態へ整え、`main`へ反映しておく必要があります。

## Verify Job

最初のJobはRelease内容を検証し、公開前Artifactを生成します。

主な確認:

1. 実行元が `main`
2. Release Versionが正式SemVer `X.Y.Z`
3. `app/version.php` / README / CHANGELOG / RELEASE_NOTES等のRelease-ready整合
4. 実行開始時のRemote `main` SHA一致
5. 既存Tagがある場合は同一Commit
6. PHP 8.1 / 8.4 Current regression
7. high-signal secret scan
8. Runtime Package生成・Verify
9. Complete Source Package生成・Verify
10. SHA-256 sidecar確認
11. Runtime / Complete Sourceのclean-room確認
12. Runtime / Complete Source ZIPへのGitHub Artifact Attestation生成
13. 検証済みAssetをGitHub Actions Artifactへ引き渡し

Verify JobはRepository内容の公開変更を行わず、`contents: read`を基本に、Attestation生成に必要な `id-token: write` / `attestations: write` だけを追加します。

## Publish Job

Publish JobはVerify Job成功後だけ実行します。

主な処理:

1. Verify Jobが生成したArtifactを取得
2. SHA-256を再確認
3. `gh attestation verify` でRuntime / Complete Source ZIPのprovenanceを再確認
4. 公開直前にRemote `main` SHAを再確認
5. 既存Tagが別Commitを指していないことを再確認
6. immutable Tagを作成
7. GitHub Releaseを作成
8. Runtime / Complete Source ZIPとSHA-256をRelease Assetとして添付

Repositoryへの `contents: write` はPublish Jobだけに限定します。

既存Tagが別Commitを指す場合は失敗し、force updateしません。同じCommitを指すTagは再利用できます。

同じTagのGitHub Releaseが既に存在する場合は、Release本文やAssetを上書き・差し替えしません。

Productionへの自動Deployは行いません。

## Browser-only Release Request

`.github/release-request.txt` はRelease起動専用です。

- 内容は1行の正式SemVer `X.Y.Z`
- `main` 上でこのFileが変更されたpushだけがReleaseのpush trigger
- Source側のVersion / README / CHANGELOG / RELEASE_NOTESと一致しない場合はRelease-ready checkで停止
- Request Fileの変更だけで無条件公開しない
- Current regression、secret scan、Package verify、clean-room、SHA-256、Attestationを通過してから公開

これにより、Local GitやGitHub CLIがない環境でもGitHub Browserから同じRelease Gateを使用できます。

## Action Dependency Policy

CI / Releaseで使用する外部GitHub Actionは、Current WorkflowでFull Commit SHAへ固定します。

Major / Minor UpdateはApplication変更と混在させず、Dependabot PR等で差分とCIを確認して更新します。

## Historical Workflow

過去Version固有WorkflowはCurrent運用には参加させません。

確認が必要な場合は該当Release tagまたはGit履歴を参照します。新Versionを作るために過去WorkflowをコピーしてVersion文字列だけ置換する運用には戻しません。

## Branch Protection / Required Checks

Branch Protection、Ruleset、required status checkはGitHub Repository側の設定であり、Source Treeとは別管理です。

Source Documentationでは現在の有効・無効状態を固定値として扱いません。GitHub Settingsで現在状態を確認してください。

有効化する場合は、Current CIの安定したJobをrequired checkとして使用し、force pushや意図しないbranch削除を防ぐ方針を推奨します。

## CIだけでは完了しない確認

- Production相当の実MySQL CRUD
- 外部の実RSS / Atomへの通信
- 実HostingのPermission、DocumentRoot、HTTPS
- 実BrowserでのTheme / Responsive / Optional Service確認
- Backupから別DBへのRestore drill
- Production反映後の実環境確認

これらはCIと分け、Release / Deploymentの手動確認として扱います。

## Failure時

1. 最初のFAILを優先する。
2. 変更箇所に対応する最小Testを先に確認する。
3. 必要な場合だけCurrent Gate全体へ範囲を広げる。
4. `continue-on-error` や無条件SKIPでGreenにしない。
5. Release Workflow失敗時はTag / Release / Artifactの状態を確認する。
6. Sourceを修正した場合は、新しい `main` SHAからRelease Gateをやり直す。

Package構成は [`release-package.md`](release-package.md)、Tag / GitHub Release手順は [`tag-and-github-release.md`](tag-and-github-release.md) を参照してください。
