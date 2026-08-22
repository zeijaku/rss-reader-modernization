# V1.19 Security Checklist for New Features

今後の機能追加時に、V1.19で整理した境界を壊さないための最小Checklistです。該当しない項目は理由を残してSkipします。

## Public / HTTP

- [ ] 新しい`public/*.php`が本当に必要か。既存`api_v1.php` Actionで済まないか。
- [ ] Public PHPを追加する場合、Anonymous可否、Allowed Method、CSRF、Authentication、Authorizationを決めた。
- [ ] `public/.htaccess` whitelistとPublic Endpoint Matrixを同時に更新した。
- [ ] GET/POST以外の想定外Methodを確認した。
- [ ] Error responseへStack trace、Server path、SQL、Secretを返さない。

## API / Authorization

- [ ] 既存Action namingとResponse contractを維持した。
- [ ] MutationはPOST + Authentication + CSRFを通る。
- [ ] Browserから渡されたowner/user IDをauthorityとして使っていない。
- [ ] Resource lookup/update/deleteにSession owner scopeがある。
- [ ] 存在しないID、他User ID、0/負数/配列/長大値を確認した。
- [ ] 大きいPayloadが必要なら`APP_API_MAX_REQUEST_BYTES`とのCompatibilityを確認した。

## Database

- [ ] SQL parameterはprepared statementへbindした。
- [ ] Dynamic ORDER BY / identifierはallowlistから生成した。
- [ ] DB変更が本当に必要か確認した。必要ならMigration / Backup / Rollbackを用意した。
- [ ] New table/entityにもowner scopeとIndex方針がある。

## External request / SSRF

- [ ] Server-side User URL fetchは共通`http_fetch.php` boundaryを再利用した。
- [ ] Redirect先も再検証する。
- [ ] localhost/private/link-local/metadata/special IPへ到達しない。
- [ ] Timeout、Response size、Retry/Cache、同時実行量を制限した。
- [ ] Secret/API keyをBrowser response、Log、URL queryへ出さない。

## Browser / XSS / CSP

- [ ] 外部/DB/User textはHTMLとして連結せずescapeまたはDOM text APIを使用した。
- [ ] `_blank` linkは`noopener noreferrer`。
- [ ] 新しいiframe / media / external connectionがCSP方針と衝突しないか確認した。
- [ ] External CDN scriptを追加する場合Version固定、License、SRI、CORSを確認した。
- [ ] SRI digestは配布URLの実bytesからSHA digestを計算して比較した。
- [ ] Versioned/immutable JS/CSSを変更した場合`APP_ASSET_REVISION`と動的loaderのquery revisionを更新した。

## Session / Credentials

- [ ] Password/API Token等をSession / HTML / JSへ不要に保持しない。
- [ ] Credential変更時のRemember Me無効化、Session rotation、CSRF rotationを確認した。
- [ ] Reverse Proxy Headerを無条件に信頼しない。

## Abuse / Polling

- [ ] Login/Registration/expensive actionへ必要なRate limitがあるか検討した。
- [ ] Pollingはrequest overlapを起こさず、複数Widgetで無制限に増えない。
- [ ] Background tabや失敗時に無駄なRequestを継続しない。

## Files / Runtime / Release

- [ ] Uploadを追加する場合、拡張子だけでなくSize/MIME/保存Path/実行不可を確認した。
- [ ] Cache/Log/Session/Secretは`public/`外。
- [ ] `config/local.php`、実DB、Log、TokenをPackageへ入れていない。
- [ ] 変更箇所のFocused Testを追加/更新した。
- [ ] Release GateではFull regression、Security tests、Package verifier、Secret scanを実行する。
