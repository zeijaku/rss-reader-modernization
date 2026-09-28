# GitHub公開設定Checklist

この文書はPublic Repositoryとして維持するためのCurrent Checklistです。初回公開時のM4-D固有手順はHistorical EvidenceとしてGit履歴に残し、現在の運用ではCurrent CI / Release Workflowを基準にします。

## Repository概要

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

WebsiteはSanitizedしたPortfolioまたはDemo URLがある場合だけ設定します。

## Repository Settings

GitHub RepositoryのSettingsで定期的に確認します。

- Visibilityが意図したPublic
- Default branchが `main`
- GitHub Actionsが有効
- Workflow permissionはRead repository contentsを基本にする
- Private Vulnerability Reportingを利用できる場合は有効化
- Secret scanning等、利用可能なSecurity機能を確認
- Force push / branch削除を防ぐRulesetを検討
- Required checkを設定する場合はCurrent CIの安定したJobを使用

Branch Protection / RulesetはSource Treeとは別管理のため、この文書へ現在の有効・無効状態を固定値として書きません。

## Actions

Current Workflow:

- `.github/workflows/ci.yml`
- `.github/workflows/release.yml`

CIではPHP 8.1 / 8.4のCurrent Gateを確認します。

Release WorkflowはVerify / Publishを分離し、Runtime / Complete Source Package、SHA-256、clean-room、secret scan、GitHub Artifact Attestationを検証してからimmutable Tag / GitHub Releaseを公開します。

詳細は [`ci.md`](ci.md)、[`release-package.md`](release-package.md)、[`tag-and-github-release.md`](tag-and-github-release.md) を参照してください。

## Public file確認

公開Repositoryに必要な主要File:

- `README.md`
- `CHANGELOG.md`
- `RELEASE_NOTES.md`
- `LICENSE`
- `THIRD_PARTY_NOTICES.md`
- `SECURITY.md`
- `CONTRIBUTING.md`
- `docs/`
- `.github/workflows/ci.yml`
- `.github/workflows/release.yml`
- Issue template / Dependabot等のCurrent GitHub設定File

次がGitHubへ出ていないことも確認します。

- `config/local.php`
- real `.env`
- Production DB dump / Backup
- Log / Session / Cache / Lock / State
- Credential / Token / Private key
- Legacy archive
- Production-only file
- 一時的なCheckpoint ZIP

Sensitive dataの詳細は [`sensitive-data-manifest.md`](sensitive-data-manifest.md) を参照してください。

## Release前

正式Releaseでは次を確認します。

1. Release-ready Sourceが `main` にある
2. Current CIがGreen
3. Application Version / README / CHANGELOG / RELEASE_NOTES / Release Requestが対象Versionと一致
4. 共通Release Workflowを使用
5. Runtime / Complete Source Package Verifyが成功
6. SHA-256 / clean-room / secret scanが成功
7. Artifact Attestation生成と公開前再検証が成功
8. Tagが対象`main` Commitを指す
9. GitHub Release Assetが正式Packageと一致

Production DeploymentはGitHub Releaseとは別工程です。
