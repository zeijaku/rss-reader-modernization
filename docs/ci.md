# GitHub Actions CI

## 目的

V1.23では、GitHub Actionsを次の2本へ整理します。

- `.github/workflows/ci.yml`
  - 現在のApplicationを継続的に検証するCI
- `.github/workflows/release.yml`
  - 正式Release時だけ実行する共通Release workflow

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
  - Version固有runnerがCurrent CI / Releaseへ再混入することを検出
- `tests/test_workflow_hygiene.py`
  - 現役workflowを `ci.yml` / `release.yml` に限定
  - Version固有workflow、Version固定release branchの再混入を検出
  - Releaseのbrowser fallbackが `.github/release-request.txt` のmain pushだけに限定されていることを確認
- `tests/test_release_flow.py`
  - Release workflowのVersion非依存性
  - tag上書き禁止
  - 既存GitHub Releaseを変更しない契約
  - package toolへの明示Version入力
  - clean-room / secret scan維持

その後、`tests/run-current.sh` と `tests/run-current-features.sh` を実行します。

過去VersionのFinal Release testは削除しません。Release当時のimmutable contractを確認する資料として残しますが、Current CIからは実行しません。

## Standard Release workflow

`.github/workflows/release.yml` は、次の2経路だけで起動します。

1. `workflow_dispatch`
   - GitHub Actions画面の `Run workflow` またはGitHub CLI / APIから実行
   - `version` を `X.Y.Z` 形式で明示入力
2. browser-only fallback
   - `main` 上の `.github/release-request.txt` が変更されたpushだけで起動
   - ファイル内容を `X.Y.Z` としてRelease Versionに使用

通常のApplication code pushではRelease workflowは起動しません。

BrowserだけでReleaseする場合は、release-ready sourceを `main` へ反映しCurrent CIを確認した後、GitHubのCode画面から `.github/release-request.txt` を対象Versionへ変更してcommitします。Branch protectionが有効な場合はPull Request経由でmergeします。

Release workflowはSourceを書き換えたり、自動commitしたりしません。実行前にSourceをrelease-readyな状態へ整え、`main`へ反映しておく必要があります。

Workflowは次を確認します。

1. 実行元が `main` であること
2. 手動入力またはrelease request fileのVersionが `X.Y.Z` であること
3. 対象Versionと `app/version.php` / README / CHANGELOG / RELEASE_NOTESが一致すること
4. 実行開始時の `main` SHAとRemote `main` SHAが一致すること
5. 既存Tagがある場合は同じCommitを指していること
6. PHP 8.1 / 8.4のCurrent regression
7. 高signal secret scan
8. Runtime / Complete Source package生成と独立Verifier
9. SHA-256
10. clean-room展開確認
11. 公開直前にRemote `main` SHAとTagを再確認
12. immutable tag作成とGitHub Release作成

既存Tagが別Commitを指す場合は失敗します。force updateは行いません。

同じCommitを指すTagが既に存在する場合はそのTagを再利用します。GitHub Releaseが既に存在する場合は内容やAssetを変更せず、そのまま残します。

## Browser-only release request

`.github/release-request.txt` はRelease起動専用です。

- 内容は1行の正式SemVer `X.Y.Z` のみ
- このファイルの変更だけがReleaseのpush trigger対象
- Source側のVersionやRelease Notesと一致しない場合は `tools/check_release_ready.py` で停止
- Tag / Release作成前にCurrent regression、secret scan、package verify、clean-room確認をすべて実施

そのため、ローカルGitやGitHub CLIがない環境でもGitHubブラウザーだけで正式Releaseを要求できます。

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
3. 必要な場合だけCurrent regressionへ範囲を広げる。
4. `continue-on-error` や無条件SKIPで緑にしない。
5. Release workflow失敗時はTag / Releaseの有無を確認し、Sourceを修正した場合は新しい `main` SHAから再実行する。

V1.23では各段階でFull Regressionを繰り返さず、Full regression / Release gateは最終段階でまとめて確認します。
