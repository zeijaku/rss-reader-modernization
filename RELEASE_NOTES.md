# RSS Reader Modernization 1.0.0 Release Notes

> **M4-F release candidate:** この文書は`1.0.0-rc1`向けです。実環境EvidenceとM4-Gの最終Gateが終わるまで正式Releaseではありません。

## Overview

約10年前に作成したPHP製RSS Readerを、Legacy版の仕様とDataを確認しながら段階的に近代化しました。Secure BaselineではSecurityとPHP 8対応を優先し、その後RSS Engine、Frontend、設置・更新・復旧、GitHub公開資料を整理しています。

新しいFrameworkへ全面移行するのではなく、既存の使い方、4タブ、Feed CRUD、Stock、Settings、公開API、MySQLのData構造を維持しながら更新したVersionです。

## Main changes

### Security and runtime baseline

- Authentication、Authorization、owner scope、Session、Password hashを整理。
- CSRF、SSRF、XSS、入力Validationを追加・強化。
- DB accessをPDOへ統一し、table prefixとDB integrityを整理。
- Private設定、実DB、Log、Session、Cacheを公開Repositoryと配布物から分離。
- PHP 8.1以降で動かせる状態へ修正。

### RSS / Atom engine

- Fetcher、Parser、Adapter、Normalized Itemの責務を分離。
- RSS 2.0、RSS 1.0、Atomの互換を維持。
- Feed item identity、Server-side cache、同時Fetch抑制を追加。
- ETag、Last-Modified、HTTP 304へ対応。
- Retry-After、段階的Backoff、範囲を限定したstale-if-errorを追加。
- Security errorではstale responseを使用しない境界を維持。

### Frontend

- Inline JavaScript / CSSを整理し、Dashboard処理を外部Assetへ分離。
- Feed描画、Loading、0件、error表示、再読込を整理。
- Semantic HTML、Keyboard、Focus、ARIAを改善。
- Mobile 1列、Tablet 2列、Desktop 4列のResponsive layoutを整理。
- jQuery 3.7.1、Font Awesome Free 6.7.2へ更新。
- Bootstrap / Bootswatch 4.1.3、Drawer 3.2.2、iScroll 5.2.0-snapshotは互換性を優先して維持。

### Operations and repository

- 新規設置、Legacy DB migration、更新、Backup、Restore、Rollback手順を整理。
- Runtime設定36項目のDefaultと制約をDocument化。
- Third-party noticeとLicense copyを実際の配布Assetへ同期。
- GitHub ActionsのPHP 8.1 / 8.4 Regression、Security reporting、Contribution方針を追加。
- Release ZIP、内部Manifest、外部SHA-256、Tag / GitHub Release手順をM4-Eで準備。
- M4-Fで`1.0.0-rc1`、環境Probe、実環境Evidence Gateを追加。

## Compatibility

- PHP 8.1+
- PDO + `pdo_mysql`
- cURL
- SimpleXML
- mbstring
- MySQL / MariaDB
- Web serverのDocumentRootを `public/` に設定できる構成

Frontendの主要Libraryは次の組合せです。

```text
Bootstrap / Bootswatch 4.1.3
jQuery 3.7.1
Popper.js 1.14.3
jquery-drawer 3.2.2
iScroll 5.2.0-snapshot
Font Awesome Free 6.7.2
```

## Installation and update

- 新規空DB: [`docs/installation.md`](docs/installation.md)
- 更新: [`docs/update.md`](docs/update.md)
- 設定: [`docs/configuration.md`](docs/configuration.md)
- Backup / Restore: [`docs/backup-and-restore.md`](docs/backup-and-restore.md)
- Rollback: [`docs/rollback.md`](docs/rollback.md)
- 配置確認: [`docs/deployment-checklist.md`](docs/deployment-checklist.md)

`config/local.php`、`APP_HASH_KEY`、実DBをBackupしてから更新してください。既存DBへ `database/schema.sql` を再投入しません。

## Distribution files

M4-Fでは、次のRelease Candidateを確認します。

```text
rss-reader-modernization-1.0.0-rc1.zip
rss-reader-modernization-1.0.0-rc1.zip.sha256
```

ZIP内部には `RELEASE_MANIFEST.sha256` と `RELEASE_BUILD.txt` を含めます。RCは次の状態であり、正式Releaseとして公開しません。

```text
package_status=RELEASE_CANDIDATE
application_version=1.0.0-rc1
publishable=no
```

M4-GではVersion marker、Tag、Release Notesを最終化し、正式な次の成果物を作り直します。

```text
rss-reader-modernization-1.0.0.zip
rss-reader-modernization-1.0.0.zip.sha256
```

## Known verification limits at M4-F

M4-FではSource全回帰、RC deterministic build、Manifest、SHA-256、Version guard、環境Probe、Evidence Gateを検証しています。このBuild環境では次を正式なPASSにできませんでした。

- 実Hosting上の`pdo_mysql`、cURL、SimpleXML、mbstring
- 実MySQL接続とSchema verify
- 実Feed URLを使ったRSS 2.0 / RSS 1.0 / Atom確認
- 実Browserでの8テーマとResponsive確認
- Backupから別DBへ戻すRestore drill
- GitHub hosted CIのPHP 8.1 / 8.4成功確認
- `APP_VERSION = 1.0.0` とTag `v1.0.0`

これらは[`docs/m4-f-validation.md`](docs/m4-f-validation.md)のEvidenceへ記録します。全必須項目PASSまではM4-Gへ進みません。

## Security notes

Security問題は公開IssueへCredential、個人情報、実Feed URL、Cookie、Server情報を書かず、RepositoryのSecurity policyに従ってください。第三者のFeed提供元へ負荷をかけるTestは行わないでください。

## License

Project本体はMIT Licenseです。Vendored frontend assetsは各上流Licenseに従います。詳細は `THIRD_PARTY_NOTICES.md` と `licenses/` を参照してください。
