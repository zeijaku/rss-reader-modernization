# Security Policy

## Supported version

Version 1.0.0の正式Release前は、GitHub `main` の最新Checkpointだけを確認対象とします。正式Release後は、原則として最新Releaseを対象にします。

古いCheckpoint、Legacy evidence、利用者が独自変更した環境は、修正対象外になる場合があります。

## Reporting a vulnerability

Security上の問題は、公開Issueへ詳細を書かないでください。

GitHub Repositoryの **Security** → **Advisories** → **Report a vulnerability** が利用できる場合は、Private Vulnerability Reportingを使用してください。

Private reportingが表示されない場合は、攻撃手順、Credential、個人情報、実URLを含めず、「Security issueの連絡方法を確認したい」ことだけを公開Issueで知らせてください。公開IssueへSecretや再現用の実データを貼らないでください。

報告には、可能な範囲で次を含めてください。

- 対象VersionまたはCommit
- 影響する画面、API、処理
- 期待する動作と実際の動作
- 再現に必要な最小手順
- 影響範囲の見立て
- SanitizedしたLogまたはRequest / Response

## Scope

主なSecurity境界はAuthentication、Authorization / owner scope、Session、CSRF、SSRF-safe Feed fetch、XSS-safe output、PDO、Validation、Private runtime dataです。

第三者のFeed提供元、Hosting事業者、GitHub、Browser、PHP / MySQL本体の脆弱性は、このRepositoryだけでは修正できません。ただし、このApplicationの使い方によって影響が生じる場合は調査対象にします。

## Safe testing

- 自分が管理していないServerやFeedへ負荷をかけない。
- 実Credential、実DB、Session、Token、個人情報を送らない。
- Data破壊、継続的なService妨害、Social engineeringを行わない。
- 再現はLocalまたは許可を得た環境で行う。

Security modelの詳細は [`docs/security.md`](docs/security.md) を参照してください。
