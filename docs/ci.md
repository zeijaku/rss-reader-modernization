# GitHub Actions CI

## 目的

M4-Dでは、GitHub上でSecure Baseline、M1、M2、M4の既存Regressionを自動実行する最小CIを追加します。DeployやReleaseを自動化するWorkflowではありません。

Workflow: `.github/workflows/ci.yml`

## Trigger

- `main` へのpush
- `main` 向けPull Request
- GitHub画面からの手動実行

`pull_request_target` は使用しません。Workflow tokenは `contents: read` に限定し、Repositoryへ書込みません。Secretも参照しません。

## Runtime matrix

- PHP 8.1: ApplicationのCompatibility floor
- PHP 8.4: 新しいPHP 8系でのRegression
- Python 3.12
- Node.js 20
- PHP extension: curl、mbstring、pdo_mysql、pdo_sqlite、simplexml

CIは各PHP Versionで `bash tests/run.sh` を実行します。

## CIが確認する範囲

- PHP syntax
- Authentication / Session / CSRF
- Authorization / owner scope
- SSRF / XSS / Validation
- Schema / table prefix / Repository scan
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Cache / Lock / Conditional request / Retry / stale-if-error
- Frontend structure / Runtime / Accessibility / Responsive
- Dependency / License / Documentation
- M4 Release gate、設置・更新・復旧資料

## CIだけでは完了しない確認

- Production相当の実MySQL CRUD
- 外部の実RSS / Atomへの通信
- 実HostingのPermission、DocumentRoot、HTTPS
- 実Browserでの8 ThemeとResponsive確認
- Backupから別DBへのRestore drill
- GitHub Release / Tag / 配布ZIPの最終確認

これらはM4-FのRelease Candidate確認として残します。

## First run

M4-Dをpushした後、GitHubの **Actions** → **CI** でPHP 8.1とPHP 8.4の両Jobを確認します。

初回Runが成功する前に、READMEのBadgeやRelease Gateを「GitHub hosted CI PASS」と判断しません。Local static testでWorkflow定義を確認した状態と、GitHub hosted runnerで成功した状態を分けて記録します。

## Failure時

1. 失敗したPHP VersionとStepを確認する。
2. 最初のFAILを優先し、後続の連鎖FAILと分ける。
3. Localで同じTestを実行する。
4. Runtime差の場合は、WorkflowかApplicationのどちらを直すかを判断する。
5. `continue-on-error` や無条件SKIPで緑にしない。
