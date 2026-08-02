# GitHub公開設定Checklist

## Repository概要

M4-DではSource、Documentation、License、Test、CIを公開Repositoryとして読める状態へ揃えます。GitHubのRepository設定そのものはZIPから変更できないため、push後に画面で確認します。

推奨Description:

```text
A legacy PHP RSS reader modernized with security hardening, PHP 8 support, MySQL 8 schema work, feed caching, accessibility, and regression tests.
```

推奨Topics:

```text
php
rss-reader
atom-feed
mysql
security
legacy-modernization
accessibility
testing
```

Websiteは、SanitizedしたPortfolioまたはDemo URLが用意できるまで空欄でも問題ありません。

## Settings

GitHub RepositoryのSettingsで次を確認します。

- Visibilityが意図したPublicである。
- Default branchが `main`。
- Actionsが有効。
- Workflow permissionはRead repository contentsを基本にする。
- Private vulnerability reportingを有効にする。
- Secret scanning等、利用できるSecurity機能を確認する。
- Force pushとbranch削除を禁止するRulesetを検討する。
- CI初回成功後、必要ならCI status checkをRulesetへ追加する。

個人Repositoryで直接pushを継続する場合、Pull Request必須化まで同時に行う必要はありません。まずForce push禁止とCI結果確認から始めます。

## Actions初回確認

M4-Dをpushした後、ActionsのCIを開きます。

- PHP 8.1 regressionが成功。
- PHP 8.4 regressionが成功。
- Workflow tokenが書込み権限を要求していない。
- Secretを設定していない。
- SKIPがある場合は理由を確認する。

GitHub hosted runnerの結果はLocal testとは別の証拠として、M4-Fまで保持します。

## Public file確認

- README
- CHANGELOG
- LICENSE
- THIRD_PARTY_NOTICES
- SECURITY
- CONTRIBUTING
- `docs/`
- `.github/workflows/ci.yml`
- Issue template

次がGitHubへ出ていないことも確認します。

- `config/local.php`
- real `.env`
- DB dump / Backup
- Log / Session / Cache / Lock / State
- Legacy archive
- Credential / Token / Private key
- Checkpoint ZIP

## Release前

M4-DではTag `v1.0.0` とGitHub Releaseを作りません。Release ZIP、Release Notes、SHA-256、Tag手順はM4-E、実環境確認はM4-F、正式VersionはM4-Gで扱います。
