# RSS Reader Modernization

**Current checkpoint:** `Release M4-A / R1`

約10年前に作成されたPHP製RSSリーダーを、Legacy版を解析資料として凍結したまま段階的に近代化するProjectです。Security / Authentication / Session / CSRF / SSRF / XSS / PDO / Validation / PHP 8 / DB integrity / regression testは `Secure Baseline SB-15 / R3` で確立し、Initial Commitとして公開済みです。

M1: Source / RSS Engine ModernizationはM1-Gまで完了し、**M2: Frontend Modernization** もM2-Gまで完了しました。M2-A〜M2-DでFrontend構造、Feed表示、Accessibility、Responsive、UI / UXを整理し、M2-Eで未使用Frontend配布物を削除、M2-FでjQueryを3.7.1、Font Awesome Freeを6.7.2へ更新しています。M2-GではSecure Baseline、M1、M2を横断する最終回帰、配布物・Asset・Documentationの整合確認を行いました。Bootstrap / Bootswatchは4.1.3の組合せ、Drawer 3.2.2、iScroll 5.2.0-snapshotを維持し、Bootstrap 5への移行は別のmajor migrationとして保留しています。Navbar、4タブ、Feed CRUD、Stock、Settings、公開API、DB、M1 RSS Engineの契約は維持しています。

## 現在できること

- ユーザー登録 / ログイン / ログアウト
- ユーザーごとのFeed URL登録・変更・論理削除
- 4タブ（location 0〜3）へのFeed配置
- RSS 2.0 / RSS 1.0 / Atomの表示
- 正常Feed本文の短時間Server-side cache（初期TTL 60秒）
- 同一Feed URLへの同時Fetch抑制
- ETag / Last-Modified / HTTP 304による本文再送抑制
- 一時障害時の段階的BackoffとRetry-After対応
- 最後の正常確認から24時間以内に限るstale-if-error
- 記事リンクのStock保存と一覧表示
- Bootstrapテーマ、Navbarリンク、タブ名のユーザー設定
- MySQL 8系での新規DB構築
- configurable table prefix（例: `rss_`）

Feed item本文はDBへ永続化せず、登録されたFeed URLから表示時に取得します。

## Secure Baselineで完了した範囲

| Work unit | 内容 | 状態 |
|---|---|---|
| SB-00 | Legacy evidence freeze | 完了 |
| SB-01 | Public/private boundary・秘密情報分離 | 完了 |
| SB-02 | PDO / DB access foundation | 完了 |
| SB-03 | Session foundation | 完了 |
| SB-04 | Authentication / password | 完了 |
| SB-05 | API contract / dispatcher | 完了 |
| SB-06 | Authorization / ownership | 完了 |
| SB-07 | CSRF | 完了 |
| SB-08 | Validation | 完了 |
| SB-09 | SSRF-safe outbound fetch / TLS | 完了 |
| SB-10 | XSS-safe output | 完了 |
| SB-11 | Legacy functional bug fixes | 完了 |
| SB-12 | PHP 8 runtime stabilization / Atom link fix | 完了 |
| SB-13 | Schema / integrity / table prefix | 完了 |
| SB-14 | Final security / regression matrix | 完了 |
| SB-15 | Documentation / Initial Commit gate | 完了 |

詳細は [`docs/modernization.md`](docs/modernization.md) と [`docs/change-map.md`](docs/change-map.md) を参照してください。


## M1 progress

M1 completion checkpoint: `RSS Engine M1-G / R1`

| Work unit | 内容 | 状態 |
|---|---|---|
| M1-A | Fetcher / Parser責務分離 + Normalized Item | 完了 |
| M1-B | Feed Source model | 完了 |
| M1-C | RSS 2.0 / RSS 1.0 / Atom Adapter整理 + Date normalization | 完了 |
| M1-D | Item identity | 完了 |
| M1-E | Server-side cache + 重複Fetch抑制 | 完了 |
| M1-F | ETag / Last-Modified / HTTP 304 | 完了 |
| M1-G | Fetch state + Retry / stale-if-error | 完了 |

M1-Aの詳細は [`docs/m1-a-implementation.md`](docs/m1-a-implementation.md)、M1-Bは [`docs/m1-b-implementation.md`](docs/m1-b-implementation.md)、M1-Cは [`docs/m1-c-implementation.md`](docs/m1-c-implementation.md)、M1-Dは [`docs/m1-d-implementation.md`](docs/m1-d-implementation.md)、M1-Eは [`docs/m1-e-implementation.md`](docs/m1-e-implementation.md)、M1-Fは [`docs/m1-f-implementation.md`](docs/m1-f-implementation.md)、M1-Gは [`docs/m1-g-implementation.md`](docs/m1-g-implementation.md) を参照してください。

## M2 progress

| Work unit | 内容 | 状態 |
|---|---|---|
| M2-A | Frontend基盤整理 | 完了 |
| M2-B | Feed表示処理整理 | 完了 |
| M2-C | HTML構造・Accessibility | 完了 |
| M2-D | Responsive・UI / UX | 完了 |
| M2-E | 不要Frontend Asset整理 | 完了 |
| M2-F | Frontend依存関係更新 | 完了 |
| M2-G | 最終回帰・Documentation | 完了 |

M2-Aの詳細は [`docs/m2-a-implementation.md`](docs/m2-a-implementation.md)、M2-Bは [`docs/m2-b-implementation.md`](docs/m2-b-implementation.md)、M2-Cは [`docs/m2-c-implementation.md`](docs/m2-c-implementation.md)、M2-Dは [`docs/m2-d-implementation.md`](docs/m2-d-implementation.md)、M2-Eは [`docs/m2-e-implementation.md`](docs/m2-e-implementation.md)、M2-Fは [`docs/m2-f-implementation.md`](docs/m2-f-implementation.md)、M2-Gは [`docs/m2-g-implementation.md`](docs/m2-g-implementation.md) を参照してください。M2全体の要約は [`docs/m2-completion-summary.md`](docs/m2-completion-summary.md)、test結果は [`docs/test-report-m2-a.md`](docs/test-report-m2-a.md) から [`docs/test-report-m2-g.md`](docs/test-report-m2-g.md) に記録しています。

## M4 progress

M4は新機能追加ではなく、Version 1.0.0の正式公開準備です。M4-AではM2-GをRelease Baselineとして固定し、公開物、残課題、Quality Gate、M3未実施範囲の扱いを整理しました。Application機能、DB、公開API、Security境界は変更していません。

| Work unit | 内容 | 状態 |
|---|---|---|
| M4-A | Release基準・公開物・残課題の棚卸し | 完了 |
| M4-B | README・CHANGELOG・License・Third-party notice | 未着手 |
| M4-C | 設置・更新・Backup・復旧手順 | 未着手 |
| M4-D | GitHub公開状態・Repository・Portfolio | 未着手 |
| M4-E | 配布ZIP・Release Notes・SHA-256・Tag手順 | 未着手 |
| M4-F | Release Candidate全回帰・実環境確認 | 未着手 |
| M4-G | 最終Quality Gate・Version 1.0.0確定 | 未着手 |

詳細は [`docs/m4-a-implementation.md`](docs/m4-a-implementation.md) と [`docs/release-gate-v1.0.0.md`](docs/release-gate-v1.0.0.md) を参照してください。

## Runtime requirements

- PHP 8.1+
- PDO + `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQL / MariaDB（新規環境ではMySQL 8系で確認）
- WebサーバーのDocumentRootを `public/` に設定できる構成

`tools/healthcheck.php` はCLI専用です。コマンドを利用できない環境では、PHP拡張・DB接続・書込み権限はホスティング側の管理画面とアプリの実動作で確認してください。

## Installation — new empty database

データ保全が不要な新規環境では、Legacy DBをALTERするより新しい空DBを作る方法を推奨します。

1. 配布物を配置する。
2. WebサーバーのDocumentRootを `public/` にする。
3. `config/local.php.example` を参考に、公開領域外の `config/local.php` を作成する。
4. MySQL 8側で空DBを作成する。
5. `DB_NAME` と `DB_TABLE_PREFIX` を設定する。
6. `database/schema.sql` 冒頭の `@table_prefix` を同じ接頭辞にする。
7. phpMyAdminで新DBを選択し `database/schema.sql` を実行する。
8. アプリから新規ユーザー登録し、ログインして動作確認する。
9. 必要なら `database/audit/postflight.sql` でSchemaを確認する。

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

`schema.sql`:

```sql
SET @table_prefix = 'rss_';
```

Prefix `rss_` の場合、次の4テーブルを作成します。

```text
rss_user_info
rss_user_conf
rss_content
rss_content_stock
```

SQLファイルはPHP設定を直接参照できないため、**`DB_TABLE_PREFIX` と `@table_prefix` は同じ値にしてください。**

## Existing Legacy DB migration

既存DBを保持して移行する場合だけ、次の順序を使用します。

```text
database/audit/preflight.sql
→ 結果確認
→ database/migrations/001_sb13_integrity.sql
→ database/audit/postflight.sql
```

Migration前に必ずDB全体をバックアップしてください。Duplicate identityやorphan等を自動削除・統合する設計にはしていません。

新DBから開始する場合、`preflight.sql` と `001_sb13_integrity.sql` は不要です。

## Production configuration

実環境では少なくとも次を確認してください。

- `APP_DEBUG=false`
- `APP_HASH_KEY` は十分に長いランダム値を使用し、運用開始後は安易に変更しない
- `APP_HASH_KEY` は既存ユーザーのログインIdentity生成に必要なため、紛失しないよう安全にバックアップする
- `config/local.php` はGit管理外・DocumentRoot外
- `REGISTRATION_ENABLED` は運用方針に合わせて設定
- `var/session/`、`var/security/login-throttle/`、`var/cache/feed/` がPHPから書込み可能
- Feed cacheは `APP_FEED_CACHE_ENABLED=true`、`APP_FEED_CONDITIONAL_REQUEST_ENABLED=true`、`APP_FEED_CACHE_TTL_SECONDS=60`、`APP_FEED_CACHE_LOCK_TIMEOUT_MS=9000` が初期値
- Retryは `APP_FEED_RETRY_ENABLED=true`、最大待機 `3600` 秒、stale-if-errorは有効・最大 `86400` 秒が初期値
- `var/log/` を利用する場合もDocumentRoot外
- HTTPSを使用

詳細: [`docs/security.md`](docs/security.md)

## Tests

```bash
bash tests/run.sh
```

SB-14の最終Matrixでは、Authentication、Authorization/IDOR、CSRF、SSRF、XSS、Parser、4タブ、DB integrity、table prefix、repository leak scan、PHP 8 runtimeを横断して検証しています。

Build環境では `pdo_mysql` / cURL / SimpleXML / mbstringが揃わないため、実MySQL/cURL/SimpleXML E2Eはローカルでは完全実行できません。代替としてFake PDO/transport、fixture、static invariantを使用し、M1-AではFetcher境界・Normalized Item・API contract・Security ordering、M1-BではFeedSource/Mapper、owner再検証、異常DB rowのfail-closed、SSRF継承、M1-CではAdapter dispatch、Date normalization、Atom `published` fallback、namespace/link/content/date fixture、XML network禁止、M1-DではGUID / `rdf:about` / Atom `id`抽出、link/fingerprint fallback、Feed URL scope、identity安定性・非公開API契約、M1-EではTTL境界、破損Cache復旧、atomic write、権限・symlink拒否、Cache無効化、5 process同時実行時の単一Fetchを確認し、M1-FではETag / Last-Modified検証、redirect時の非漏えい、HTTP 304本文再利用、schema 1互換、5 process同時revalidationを確認し、M1-GではRetry-After、エラー分類、Fetch state、Backoff境界、stale age境界、Security error非隠蔽、5 process同時障害時の単一Fetchと単一失敗記録を専用testで確認しています。M2-Cではdoctype / lang / landmark / Form / Button / Label / fieldset、Feedのaria-busy / live region、DrawerのEscape / Tab循環 / focus return、ModalとPage Topのfocus移動を確認しています。M2-Dではresponsive class、長いURL、Touch target、明示的な削除、画面内通知、Feed再読込、Feed / Stockの実描画をstatic test、Node runtime、Fake PDO renderで確認しています。M2-Eでは直接参照Asset、Theme、CSS内Font参照、Icon定義、削除対象、License header、HTTP 200を確認し、M2-FではjQuery full build、Bootstrap plugin互換、8テーマ、Font Awesome alias / WebFont、script読込順、旧Asset不存在を専用testで確認しています。M2-GではM2-A〜GのDocumentation、現在Version、Asset allowlist、主要Frontend invariant、Runtime公開面、配布手順を横断して再確認しています。headless browser smokeはharnessを用意していますが、Build環境ではruntime不足のためSKIPします。配置先ではMySQL 8のCRUDと実RSS/Atomを手動確認してください。

詳細: [`docs/test-report-sb14.md`](docs/test-report-sb14.md) / [`docs/test-report-sb15.md`](docs/test-report-sb15.md) / [`docs/test-report-m1-a.md`](docs/test-report-m1-a.md) / [`docs/test-report-m1-b.md`](docs/test-report-m1-b.md) / [`docs/test-report-m1-c.md`](docs/test-report-m1-c.md) / [`docs/test-report-m1-d.md`](docs/test-report-m1-d.md) / [`docs/test-report-m1-e.md`](docs/test-report-m1-e.md) / [`docs/test-report-m1-f.md`](docs/test-report-m1-f.md) / [`docs/test-report-m1-g.md`](docs/test-report-m1-g.md) / [`docs/test-report-m2-a.md`](docs/test-report-m2-a.md) / [`docs/test-report-m2-b.md`](docs/test-report-m2-b.md) / [`docs/test-report-m2-c.md`](docs/test-report-m2-c.md) / [`docs/test-report-m2-d.md`](docs/test-report-m2-d.md) / [`docs/test-report-m2-e.md`](docs/test-report-m2-e.md) / [`docs/test-report-m2-f.md`](docs/test-report-m2-f.md) / [`docs/test-report-m2-g.md`](docs/test-report-m2-g.md)

## Security model

主な境界は以下です。

- 認証済みSessionの `user_id` を所有者の唯一の根拠にする
- APIはPOST + explicit action + CSRF
- SQLはPDO parameter binding
- Passwordは `password_hash()` / `password_verify()`
- Login throttle
- Feed fetchはHTTP/HTTPSのみ、DNS/IP/redirect/TLS/size/timeoutを検証
- Feed/DB由来データはvalidate/escapeして描画
- Stock作成時に記事ページを再Fetchしない
- Runtime/session/log/secrets/DB dumpを公開物から分離

詳細: [`docs/security.md`](docs/security.md)

## Legacy and data policy

Legacy版は比較・解析対象として保持し、Secure BaselineのRuntimeへ混在させません。旧DB dumpには運用データやcredential情報が含まれていたため、GitHub対象から除外します。

既存ユーザーcredentialの互換性は要件から外し、不明なLegacy形式を推測して移行しません。Secure Baselineでは新規 `password_hash()` 形式を基準とします。

詳細: [`docs/legacy-analysis.md`](docs/legacy-analysis.md)

## Current limitations / deferred modernization

Secure Baseline以降も、M2-G完了時点では次を残しています。

- Server-side cacheは固定TTL + ETag / Last-Modified + 最大24時間のstale-if-error。FrontendはLoading / Empty / Errorを表示するが、Cache / Retryの内部状態は公開しない
- Feed提供元がValidatorを返さない場合はTTL経過後に通常のHTTP 200取得
- Feed取得は表示時の同期処理
- Foreign Key未導入
- Dashboard固有JS/CSS、Feed描画、semantic HTML、Keyboard / Focus / ARIA、Responsive layout、基本的な表示文言と通知は整理済み
- 未使用Frontend配布物はM2-Eで整理済み。jQueryとFont AwesomeはM2-Fで互換更新済み。Bootstrap / Bootswatch 4.1.3、Popper 1系、Drawer、iScrollのmajor migrationと、全Themeのcontrast最終調整は別途検討
- Source abstractionはFetcher / FeedSource / Parser dispatcher / RSS 2.0・RSS 1.0・Atom Adapter / Normalized Item / deterministic Item identity / cache-aware Feed serviceまで導入済み

これらはM2完了後の別工程へ意図的に分離しています。

## Roadmap

```text
Secure Baseline SB-15 / R3
  ↓
M1 Source / RSS Engine (M1-G complete)
  ↓
M2 Frontend (M2-G complete)
  ↓
M4 Release preparation (M4-A complete)
  ↓
Version 1.0.0 / Portfolio
```

M1ではRSS専用処理に固定しすぎず、将来のJSON Feed、REST API、HTML等も同じItemモデルへ正規化できるSource / Fetcher / Parser(Adapter)構成へ段階的に移行します。M1-AでFetcher / Parser分離と共通Itemモデル、M1-Bでowner-scoped contentからFeedSourceへの変換境界、M1-Cで形式別Adapterと共通Date normalizer、M1-DでFeed URL scope付きの決定的Item identity、M1-Eで正常Feed本文のServer-side cacheとURL単位の重複Fetch抑制、M1-FでETag / Last-Modified / HTTP 304による再確認、M1-GでFetch state、Retry-After、Backoff、期限付きstale-if-errorまで完了しています。

詳細: [`docs/roadmap.md`](docs/roadmap.md)

## Repository safety

Gitへ入れないもの:

- `config/local.php`
- real `.env`
- production DB dump / backup
- Legacy `rss.sql`, `rss.zip`
- logs
- PHP session files
- login throttle state
- migration snapshots
- runtime Feed cache / lock / fetch state files
- private keys / API keys

Sanitizedされた `database/` のschema/audit/migration/fake fixtureだけを例外としてVersion管理します。

詳細: [`docs/sensitive-data-manifest.md`](docs/sensitive-data-manifest.md)

## Initial Commit status

SB-15のInitial Commit gateは合格と判定しています。根拠と公開前に残る作業は [`docs/initial-commit-gate.md`](docs/initial-commit-gate.md) を参照してください。

**注意:** Initial Commit可能と「公開GitHub Release可能」は同義ではありません。公開前にはライセンス方針、Frontend依存整理の進捗、公開URL/スクリーンショット等を別途判断します。
