# M4-C / R1 新規設置・更新・設定・Backup・復旧手順

## 目的

Version 1.0.0へ向けて、設置方法だけでなく、更新前Backup、復旧、Rollbackまで一つの運用経路として確定する。Application機能やDBを変更する工程ではない。

## 実施内容

- 新規空DBへの設置手順を実コードと `database/schema.sql` に合わせた。
- Legacy DB migrationはpreflight → Backup確認 → migration → postflightの順に限定した。
- Git更新とZIP更新を分け、Private設定、Database、Runtime dataを上書きしない手順を作成した。
- `config/local.php` と環境変数の優先順位を明記した。
- `.env` fileをApplication自身が読込まないことを明記した。
- Runtimeが既に対応していた設定を `local.php.example` と `.env.example` へ揃えた。
- `APP_HASH_KEY`、Table prefix、Session、HTTP、Cache / RetryのDefaultと制約を整理した。
- Database dump、Private設定、Code VersionのBackupとRestore drillを定義した。
- Code-only rollbackとDB migrationを含むrollbackを分けた。
- 配置前後のChecklistを作成した。
- M4-C専用testで設定Key、Default、文書手順、危険Command、Markdown link、healthcheck、Packageを検査した。

## 変更していない範囲

DB schema / Migration、Public API、Authentication、Authorization / owner scope、Session処理、CSRF、SSRF、XSS、Validation、RSS / Atom、Cache format、Retry、Item identity、Frontend Runtime Assetは変更していない。

## 設定への影響

必須設定の追加はない。`config/local.php.example` と `config/.env.example` に追加した行は、M4-B以前からRuntimeがDefault付きで対応していたKeyを見える形にしたもの。

既存 `config/local.php` はそのまま使用できる。

## M4-Bからの更新

- DB migration不要
- Cache clear不要
- 削除fileなし
- Private設定の上書き禁止

## Release Gate

`Installation / Update / Recovery` はDocumentationとStatic / Runtime contract testとしてPASSへ進める。実Hosting、実MySQL、実Browser、実Restore drillはM4-Fの `Real environment / RC` に残す。
