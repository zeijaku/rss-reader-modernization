# M4-D / R1 GitHub公開状態・Repository・Portfolio・最小CI

## 目的

Version 1.0.0公開前に、GitHub Repositoryを読める状態へ整え、Security reporting、Contribution、Portfolio説明、最小CIを追加する。Application機能を変更する工程ではない。

## 実施内容

- GitHub Actionsで既存RegressionをPHP 8.1 / 8.4へ実行するWorkflowを追加。
- Workflow tokenを `contents: read` に限定し、SecretとDeploy処理を持たせない。
- `pull_request_target`、write permission、`continue-on-error`を使用しない。
- SECURITY.mdへPrivate vulnerability reportingと公開IssueへSecretを書かない方針を追加。
- CONTRIBUTING.mdへ1 purpose、既存契約、Test、秘密情報除外を記載。
- Bug report templateへPublic data確認を追加。
- GitHub Repository設定Checklistを追加。
- Portfolio用の短文、長文、技術要点、Screenshot注意、AI支援説明例を追加。
- CIの範囲と、実MySQL / Browser / Feed / Restore drillをM4-Fへ残すことを明記。
- M4-D専用testでWorkflow権限、Trigger、Runtime matrix、Documentation、Markdown link、公開面を確認。

## 変更していない範囲

DB schema / Migration、Public API、Authentication、Authorization / owner scope、Session、CSRF、SSRF、XSS、Validation、RSS / Atom、Cache / Retry、Item identity、Frontend Runtime Assetは変更していない。

## GitHub上で残る確認

ZIP内ではGitHub Settingsとhosted runnerの結果を確定できない。M4-Dをpushした後、次を利用者が確認する。

- Repository Description / Topics
- Private vulnerability reporting
- Force push禁止等のRuleset
- ActionsのPHP 8.1 / 8.4 Job
- README CI badge

Workflow定義とLocal regressionはPASSとするが、GitHub hosted CIは初回Run確認までHOLDとして扱う。

## Release Gate

`GitHub / Portfolio / CI definition` はPASSへ進める。GitHub Settingsとhosted CI初回成功は利用者確認として残し、正式Release可とは判断しない。
