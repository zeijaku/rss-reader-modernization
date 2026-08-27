# GitHub Actions CI

## 目的

V1.23では、GitHub Actionsを「現在のApplicationを継続的に検証するCI」と「Release時だけ使うRelease workflow」に分けます。

D完了時点で、現在有効なWorkflowは `.github/workflows/ci.yml` だけです。Versionごとに作成していたFocused Check / Release GateはGit履歴・各Release tagから参照できるため、現在の `.github/workflows/` には残しません。

V1.23-Eで、Version番号をファイル名に含めない共通Release workflowとして `.github/workflows/release.yml` を設計する予定です。

## Current CI

Workflow: `.github/workflows/ci.yml`

Trigger:

- `main` へのpush
- `main` 向けPull Request
- GitHub画面からの手動実行

`pull_request_target` は使用しません。Workflow tokenは `contents: read` に限定し、Repositoryへ書込みません。Secretも参照しません。

Runtime:

- PHP 8.1: ApplicationのCompatibility floor
- PHP 8.4: 新しいPHP 8系でのRegression
- Python 3.12
- Node.js 20
- PHP extension: curl、mbstring、pdo_mysql、pdo_sqlite、simplexml

## CIの構成

Current CIでは、最初にRepository maintenance上の軽量guardを実行します。

- `tests/test_version_dependency_hygiene.py`
  - Current-following testへの古いasset revision固定を検出
  - 過去Release final gateがCurrent CIへ戻ることを検出
- `tests/test_workflow_hygiene.py`
  - Version固有workflowが `.github/workflows/` に再追加されることを検出
  - 現役workflow名を `ci.yml` と共通 `release.yml` に限定
  - CI tokenがread-onlyであることを確認

その後、`tests/run-current.sh` と必要なCompatibility runnerを実行します。

過去VersionのFinal Release testは削除しません。Release当時のimmutable contractを確認する資料として残しますが、Current CIからは実行しません。

## Historical workflowの扱い

V1.14〜V1.22で使用したVersion固有workflowは、現在のGitHub Actions運用には参加させません。

過去workflowを確認する場合は、該当Release tagまたはGit履歴を参照します。新Versionを作るために古いworkflow YAMLをコピーしてVersion文字列だけ置換する運用には戻しません。

この方針により、次Versionで次のようなファイルが増え続ける状態を避けます。

- `vX.Y.Z-release.yml`
- `vX.Y-a-check.yml`
- `vX.Y-b-check.yml`

## Branch protection / required checks

Branch protectionやrequired status checkはGitHub側のRepository設定であり、Source treeとは別管理です。

Workflow名やJob名を変更・削除する前には、GitHub側でrequired checkに指定されていないことを確認します。V1.23-D実施時点では `main` はprotected branchではなく、required status checkも設定されていません。

将来Branch protectionを有効化した場合は、Current CIの安定したJob名をrequired checkとして使用します。

## CIだけでは完了しない確認

- Production相当の実MySQL CRUD
- 外部の実RSS / Atomへの通信
- 実HostingのPermission、DocumentRoot、HTTPS
- 実BrowserでのTheme / Responsive確認
- Backupから別DBへのRestore drill
- GitHub Release / Tag / 配布ZIPの最終確認

これらはRelease Candidate / Final release確認として扱います。

## Failure時

1. 失敗したPHP VersionとStepを確認する。
2. 最初のFAILを優先し、後続の連鎖FAILと分ける。
3. 最小の対象Testを先に実行する。
4. 必要な場合だけCompatibility / Current regressionへ範囲を広げる。
5. Runtime差の場合は、WorkflowかApplicationのどちらを直すかを判断する。
6. `continue-on-error` や無条件SKIPで緑にしない。

V1.23では各段階でFull Regressionを繰り返さず、Full regression / Release gateは最終段階でまとめて確認します。
