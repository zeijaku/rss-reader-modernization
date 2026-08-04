# V1.2-A Authentication / Notice / Common Error

## 基準

- Repository: `zeijaku/rss-reader-modernization`
- Branch: `main`
- 基準Commit: `31e9d9f3fc594f8080d1962f10ac30985bd07881`
- Commit message: `V1.1-K: release version 1.1.0`
- 添付Source: `v1.1(2).zip`
- Application checkpoint: `RSS Reader Modernization 1.2.0-dev.1`

GitHub `main`をApplicationの基準とし、設置Server向けのRewriteを含む添付Root `.htaccess`は上書きせず、その内容を基準にErrorDocumentだけを追加した。

## 実装前分析で確認できた事実

### Login / Registration

- LoginとRegistrationは`public/index.php`で同じPOST入口を使用する。
- Form描画は`app/common/common_login.php`へ分離されていたが、Bootstrap sampleに近い`form-signin`、`form-control`、`btn-primary`、Collapse切替へ依存していた。
- Login成功時はLogin Throttle成功記録後に`app_session_login()`を呼び、Session IDを再生成して`303`で`./`へ遷移する。
- Login失敗時はメールアドレスまたはPasswordのどちらが原因かを分けない汎用Messageを表示する。
- Registrationは`auth_register()`で登録許可、Email Validation、Password長、重複Identity、Transactionを処理する。

### CSRF / Login Throttle / Session

- Login、Registration、Logoutは既存CSRF Tokenを使用する。
- Login ThrottleはRaw Emailを保存せず、HMAC化したIdentityとIP単位のJSON Bucketを使用する。
- SessionはCookie only、HttpOnly、SameSite=Lax、HTTPS時Secure、strict modeを使用する。
- Login時はSession IDを再生成する。
- Idle／absolute timeout判定は存在したが、期限切れ理由をLogin画面へ通知していなかった。

### Logout

- `public/logout.php`はPOST限定、CSRF検証、Session破棄、Cookie失効を行っていた。
- Logout後は`./`へ戻るだけで、完了Messageはなかった。

### Error handling

- `public/.htaccess`には403／404のErrorDocument指定があったが、共通画面として利用できる実体はなかった。
- 500はBootstrapの例外Handlerが英語Plain Textを返していた。
- 503の共通ErrorDocumentはなかった。
- APIは通常処理内の例外をJSON化していたが、Bootstrap読込中の例外をHTMLへ変えない境界は明示されていなかった。
- 添付Server用Root `.htaccess`にはDocumentRoot直下から`public/`へ内部Rewriteする設定と、`app`、`config`、`tools`、`var`の拒否設定がある。

## 設計判断

以下は確認済みの既存仕様ではなく、V1.2-Aで採用した判断である。

- Login／Registration専用の`auth.css`とVanilla JavaScript `auth.js`を追加し、Dashboard側のBootstrap 4は変更しない。
- Honeypotは中立名`contact_reference`を使用し、画面外配置、`tabindex=-1`、`aria-hidden`、`autocomplete=off`で通常操作から外す。
- Honeypot検出時もLogin Throttleへ失敗を記録し、通常のLogin失敗／Registration失敗と同じMessageを返す。
- Logout完了Messageは旧認証Sessionを破棄した後、新しい匿名SessionへFlashとして保存する。
- Session期限切れは認証情報を消去してSession IDを再生成した後、Logoutとは別のFlashを保存する。
- 403／404／500／503はDB、Session、通常Bootstrapへ依存しない最小PHP Rendererを共有する。
- Error画面CSSはInline化し、外部CSSが読めない状態でも最低限の表示を維持する。
- API入口はBootstrapより前にJSON Response Formatを宣言する。
- 未知のURLをDashboardへRewriteして200にする動作は、正しい404 Statusへ変更する。

## 実装内容

### Authentication UI

- Login／Registrationを同じCard Designへ統一。
- Visible Label、Focus表示、44px以上の操作領域、Smartphone breakpointを追加。
- Password表示／非表示Buttonを両Formへ追加。
- Native Form submitを維持し、Enter送信を保持。
- 送信開始後はButtonをDisableし、2回目のSubmit Eventを拒否。
- Login画面では不要になったjQuery、Popper、Bootstrap JavaScriptを読み込まない。

### Honeypot

- Login／Registrationの両方へ追加。
- Server側HelperでScalar／Array異常を判定。
- 入力値をResponse、Log、Throttle Storageへ保存しない。
- CSRF、Login Throttle、Password認証、Registration Validationは従来どおり維持。
- DB変更なし。

### Logout / Session expiry

- Logout後Message: `ログアウトしました。`
- Timeout Message: `セッションの有効期限が切れました。もう一度ログインしてください。`
- Messageは一度取得するとSessionから削除される。
- 未LoginでLogin画面を直接開いた場合はMessageを表示しない。
- Login失敗Messageは従来の汎用Messageを維持する。

### Common error

- 403、404、500、503で共通Layoutを使用。
- 各HTTP Statusは維持する。
- `noindex,nofollow` Metaと`X-Robots-Tag` Headerを設定。
- Stack Trace、File Path、DB情報、例外MessageをResponseへ表示しない。
- 500は安全な12桁Reference IDだけを表示する。
- APIのBootstrap／Configuration例外はJSON `internal_error`を維持する。

## `.htaccess`差分

### Root `.htaccess`

既存のServer用Rewriteと拒否Ruleを維持したまま、次を追加した。

```apache
ErrorDocument 403 /public/error.php
ErrorDocument 404 /public/error.php
ErrorDocument 500 /public/error.php
ErrorDocument 503 /public/error.php
```

### `public/.htaccess`

- 403／404だけだった指定を403／404／500／503へ拡張。
- 不明Pathを`index.php`へRewriteせず、`404`を返して共通Error画面へ渡す。

### 設置先ごとの手動調整

- 現在と同じ「Application rootの下に`public/`」構成: `/public/error.php`
- DocumentRootが直接`public/`: `/error.php`
- `/rss/`等のSubdirectory設置: `/rss/public/error.php`

503 ErrorDocumentは503表示先を定義するだけで、Maintenance modeを有効化する機能ではない。

完全なUnified Diffは[`v1-2-a-htaccess.diff`](v1-2-a-htaccess.diff)を参照。

## DB / Configuration

- DB Table追加: なし
- Column追加: なし
- Migration／SQL実行: なし
- `config/local.php`追加項目: なし
- Feed Cache仕様変更: なし
- Login Throttle仕様の解除／代替: なし

## Riskと対策

- **Password ManagerによるHoneypot誤入力**: 中立LabelではあるがCredential fieldと離し、画面外、tabindex、autocomplete、inputmodeを指定した。実Server上では利用中Password Manager数種で追加確認する。
- **ErrorDocument Path差異**: ApacheのDocumentRoot／Subdirectoryにより絶対Pathが変わるため、Deployment時の手動確認が必要。
- **Apache AllowOverride差異**: `.htaccess`が無効なServerではErrorDocument／Rewriteが反映されないため、Hosting側設定確認が必要。
- **致命的なRuntime障害**: DBや通常Bootstrapに依存しないError Rendererと早期例外Handlerを用意した。ただしPHP process自体が起動できない障害やMemory枯渇時はWeb Server標準画面になる可能性がある。
- **Browser Cache**: `auth.css`／`auth.js`追加後に旧Login画面が残る場合はBrowser Cache更新が必要。Feed Cache削除は不要。

## 第2段への影響

- Dashboard、Feed、Stock、Clock、Memo、Task、CalendarのHTML／API処理は変更していない。
- 第2段は本Checkpoint確認後に開始する。
