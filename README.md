# RSS Reader Modernization

**Current checkpoint:** `Secure Baseline SB-15 / R3`

約10年前に自作したPHP製RSSリーダーを題材に、Legacy版を解析資料として保持しながら、Security、PHP 8互換性、DB構造、テスト基盤を段階的に立て直しているModernization Projectです。

現在は**Secure Baselineが完成し、ここをGit履歴の出発点として公開する段階**です。危険なLegacyコードや当時の秘密情報をGit履歴へ含めないため、Initial CommitはSecure Baselineから開始し、Legacyからの変更内容は公開ドキュメントで追跡できる構成にしています。完成版ではなく、今後はこのBaselineを壊さずにSource / RSS EngineとFrontendを近代化していきます。

解析、セキュリティレビュー、テスト設計、ドキュメント整理にはAI支援も利用しています。最終的な実装判断と検証は、実ソースコード、DB定義、テスト結果を基準に確認しています。

## Features

- ユーザー登録 / ログイン / ログアウト
- ユーザーごとのFeed URL登録・変更・論理削除
- 4タブ（location 0〜3）へのFeed配置
- RSS 2.0 / RSS 1.0 / Atom表示
- 記事リンクのStock保存と一覧表示
- Bootstrapテーマ、Navbarリンク、タブ名のユーザー設定
- MySQL / MariaDB対応
- configurable table prefix（例: `rss_`）

Feed item本文はDBへ永続化せず、登録されたFeed URLから表示時に取得します。

## Modernization highlights

Secure Baselineでは、Legacyの主要機能を維持しながら次の境界を先に整備しました。

- `public/` をWeb公開領域として分離
- Session / Authentication / Authorizationの再構築
- `password_hash()` / `password_verify()` への移行
- APIのPOST + explicit action + CSRF化
- PDO parameter binding
- SSRF対策を含むFeed fetch境界
- XSS-safe output
- PHP 8.1+ runtime stabilization
- sanitized schema / DB integrity / table prefix対応
- security / regression test matrix
- secrets / logs / sessions / production DB dumpのRepository除外

SB-00〜15の詳細は [`docs/modernization.md`](docs/modernization.md)、Legacyからの対応関係は [`docs/change-map.md`](docs/change-map.md) を参照してください。

## Requirements

- PHP 8.1+
- PDO + `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQL / MariaDB
- WebサーバーのDocumentRootを `public/` に設定できる構成

## Installation

新規環境では、Legacy DBを直接ALTERするより**新しい空DBから開始する方法を推奨**します。

1. Repositoryを配置する。
2. WebサーバーのDocumentRootを `public/` に設定する。
3. `config/local.php.example` を参考に、Git管理外の `config/local.php` を作成する。
4. MySQL / MariaDB側で空DBを作成する。
5. `DB_NAME` と `DB_TABLE_PREFIX` を設定する。
6. `database/schema.sql` の `@table_prefix` を同じ値にする。
7. `database/schema.sql` を実行する。
8. アプリから新規ユーザー登録し、ログインして動作確認する。

例:

```php
return [
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,
    'APP_HASH_KEY' => 'replace-with-a-long-random-secret',

    'DB_DRIVER' => 'mysql',
    'DB_HOST' => 'db-host',
    'DB_PORT' => '3306',
    'DB_NAME' => 'rss_reader',
    'DB_USER' => 'rss_user',
    'DB_PASSWORD' => 'replace-with-a-strong-password',
    'DB_TABLE_PREFIX' => 'rss_',
];
```

`database/schema.sql`:

```sql
SET @table_prefix = 'rss_';
```

`DB_TABLE_PREFIX` と `@table_prefix` は同じ値にしてください。

既存Legacy DBを保持して移行する場合は、[`docs/README.md`](docs/README.md) からDB migration関連資料を参照してください。

## Production security

実環境では少なくとも次を確認してください。

- `APP_DEBUG=false`
- `APP_HASH_KEY` は十分に長いランダム値を使用する
- `APP_HASH_KEY` はLogin identity生成に使うため、**運用開始後は安易に変更せず、安全にバックアップする**
- `config/local.php` と実 `.env` はGit管理しない
- `var/session/` と `var/security/login-throttle/` をDocumentRoot外で運用する
- HTTPSを使用する

設計と運用上の注意は [`docs/security.md`](docs/security.md) を参照してください。

## Tests

```bash
bash tests/run.sh
```

Secure BaselineではAuthentication、Authorization / IDOR、CSRF、SSRF、XSS、Feed parser、4タブ、DB integrity、table prefix、repository leak scan、PHP 8 runtimeを横断して検証しています。

SB-15 R3の検証結果は **740 PASS / 0 FAIL / 3 SKIP** です。環境依存の実MySQL/cURL/SimpleXML E2Eについては、配置先での手動確認と代替testを併用しています。

詳細: [`docs/test-report-sb14.md`](docs/test-report-sb14.md) / [`docs/test-report-sb15.md`](docs/test-report-sb15.md)

## Legacy policy

Legacy版は比較・解析対象として保持し、Secure BaselineのRuntimeへ混在させません。旧DB dumpには運用データやcredential情報が含まれていたため、Repositoryには含めません。

既存ユーザーcredentialの互換性は要件から外し、不明なLegacy形式を推測して移行しません。

詳細: [`docs/legacy-analysis.md`](docs/legacy-analysis.md)

## Current limitations

Secure Baselineでは次を意図的に後工程へ残しています。

- Feed itemのサーバーキャッシュなし
- ETag / Last-Modified未対応
- Feed取得は表示時の同期処理
- Foreign Key未導入
- Legacy由来のBootstrap / jQuery / Drawer / Font Awesome assets
- FrontendのUI/UX / accessibility刷新
- Source abstraction未実装

これらは今後のModernizationで段階的に対応します。

## Roadmap

```text
Secure Baseline SB-15 / R3
  ↓
GitHub Initial Commit / Repository publication  ← current
  ↓
Source / RSS Engine modernization
  ↓
Frontend modernization
  ↓
v1.0 release
  ↓
Portfolio integration
```

詳細: [`docs/roadmap.md`](docs/roadmap.md)

## Documentation

公開ドキュメントの入口は [`docs/README.md`](docs/README.md) です。

主要資料:

- [`docs/legacy-analysis.md`](docs/legacy-analysis.md) — Legacy解析
- [`docs/modernization.md`](docs/modernization.md) — SB-00〜15の改修記録
- [`docs/security.md`](docs/security.md) — Security model / deployment注意事項
- [`docs/change-map.md`](docs/change-map.md) — Legacy issueと修正・testの対応
- [`docs/roadmap.md`](docs/roadmap.md) — 今後のModernization計画

## License

このRepositoryのオリジナルコードとModernization Projectで追加・修正したコードは [`LICENSE`](LICENSE) のMIT Licenseで公開します。

同梱しているBootstrap、Bootswatch、jQuery、Popper.js、jquery-drawer、iScroll、Font Awesome Free等には、それぞれの上流ライセンスが適用されます。詳細は [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md) と [`licenses/`](licenses/) を参照してください。
