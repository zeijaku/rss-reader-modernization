# Contributing

このRepositoryは、古いPHP製RSS Readerを段階的にModernizationした個人Projectです。大規模な作り直しより、既存契約とDataを壊さない小さな変更を優先します。

## Before changing code

- Issueまたは変更目的を一つに絞る（1 purpose）。
- DB、公開API、Authentication、Session、Security境界への影響を確認する。
- `config/local.php`、実DB、Log、Session、Cache、CredentialをCommitしない。
- Legacy evidenceをRuntimeへ戻さない。
- 既存の書き方を必要以上に全面置換しない。

## Tests

変更前後で次を実行してください。

```bash
bash tests/run.sh
```

PHP、Python、Node.js、必要なPHP extensionが揃わない環境では一部TestがSKIPになります。SKIPをPASSへ読み替えず、理由をPull Requestへ記載してください。

実DB、実Feed、実Browserに影響する変更では、Local testだけでなくSanitizedした手動確認結果も残してください。

## Pull request

Pull Requestには次を簡潔に記載してください。

- 何を直したか
- なぜ必要か
- DB / API / Securityへの影響
- 実行したTestとSKIP
- 配置時に必要な作業

一つのCommitへ無関係な整形、Dependency更新、機能追加を混在させないでください。

Security問題は公開Pull Requestや公開Issueへ詳細を書かず、[`SECURITY.md`](SECURITY.md) の手順を使用してください。
