# GitHub Actions CI

## 目的

V1.23では、GitHub Actionsを次の2本へ整理します。

- `.github/workflows/ci.yml`
  - 現在のApplicationを継続的に検証するCI
- `.github/workflows/release.yml`
  - 正式Release時だけ手動実行する共通Release workflow

V1.14〜V1.22で使用したVersion固有のFocused Check / Release GateはGit履歴・各Release tagから参照し、現在の `.github/workflows/` には戻しません。

## Current CI

Trigger:

- `main` へのpush
- `main` 向けPull Request
- GitHub画面からの手動実行

`pull_request_target` は使用しません。Workflow tokenは `contents: read` に限定し、Repositoryへ書込みません。Secretも参照しません。

Runtime:

- PHP 8.1
- PHP 8.4
- Python 3.12
- Node.js 20
- PHP extension: curl、mbstring、pdo_mysql、pdo_sqlite、simplexml

Current CIでは、Repository maintenance上の軽量guardを先に実行します。

- `tests/test_version_dependency_hygiene.py`
  - Current-following testへの古いasset revision固定を検出
  - 過去Release final gateがCurrent CIへ戻ることを検出
- `tests/test_workflow_hygiene.py`
  - 現役workflowを `ci.yml` / `release.yml` に限定
  - Version固有workflow、Version固定release branchの再混入を検出
- `tests/test_release_flow.py`
  - Release workflowのVersion非依存性
  - tag上書き禁止
  - 既存GitHub Releaseを変更しない契約
  - package toolへの明示Version入力
  - clean-room / secret scan維持

その後、`tests/run-current.sh` と必要なCompatibility runnerを実行します。

過去VersionのFinal Release testは削除しません。Release当時のimmutable contractを確認する資料として残しますが、Current CIからは実行しません。

## Standard Release workflow

`.github/workflows/release.yml` は `workflow_dispatch` だけで起動します。

正式Releaseでは、GitHub Actions画面で `main` を選択し、対象Versionを `X.Y.Z` 形式で明示入力します。

Release workflowはSourceを書き換えたり、自動commitしたりしません。実行前にSourceをrelease-readyな状態へ整え、`main`へ反映しておく必要があります。

Workflowは次を確認します。

1. 実行元が `main` であること
2. 入力Versionと `app/version.php` / README / CHANGELOG / RELEASE_NOTESが一致すること
3. 実行開始時の `main` SHAとRemote `main` SHAが一致すること
4. 既存Tagがある場合は同じCommitを指していること
5. PHP 8.1 / 8.4のCurrent / Compatibility regression
6. 高signal secret scan
7. Runtime / Complete Source package生成と独立Verifier
8. SHA-256
9. clean-room展開確認
10. 公開直前にRemote `main` SHAとTagを再確認
11. immutable tag作成とGitHub Release作成

既存Tagが別Commitを指す場合は失敗します。force updateは行いません。

同じCommitを指すTagが既に存在する場合はそのTagを再利用します。GitHub Releaseが既に存在する場合は内容やAssetを変更せず、そのまま残します。

## Historical workflowの扱い

V1.14〜V1.22で使用したVersion固有workflowは、現在のGitHub Actions運用には参加させません。

過去workflowを確認する場合は、該当Release tagまたはGit履歴を参照します。新Versionを作るために古いworkflow YAMLをコピーしてVersion文字列だけ置換する運用には戻しません。

## Branch protection / required checks

Branch protectionやrequired status checkはGitHub側のRepository設定であり、Source treeとは別管理です。

V1.23-D実施時点では `main` はprotected branchではなく、required status checkも設定されていません。

将来Branch protectionを有効化した場合は、Current CIの安定したJob名をrequired checkとして使用します。

## CIだけでは完了しない確認

- Production相当の実MySQL CRUD
- 外部の実RSS / Atomへの通信
- 実HostingのPermission、DocumentRoot、HTTPS
- 実BrowserでのTheme / Responsive確認
- Backupから別DBへのRestore drill
- Production反映後の実環境確認

これらはRelease Candidate / Final release確認として扱います。

## Failure時

1. 最初のFAILを優先する。
2. 最小の対象Testを先に実行する。
3. 必要な場合だけCompatibility / Current regressionへ範囲を広げる。
4. `continue-on-error` や無条件SKIPで緑にしない。
5. Release workflow失敗時はTag / Releaseの有無を確認し、Sourceを修正した場合は新しい `main` SHAから再実行する。

V1.23では各段階でFull Regressionを繰り返さず、Full regression / Release gateは最終段階でまとめて確認します。
