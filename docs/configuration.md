# 設定項目

## 読込順

Runtime設定は次の順で決まります。

```text
Environment variable > config/local.php > safe default
```

`config/local.php` はPHP arrayをreturnします。Environment variableはHosting control panel、Web server、PHP-FPM、Process manager等で設定します。

このProjectはdotenv libraryを使用せず、`.env` fileを自動読込しません。`config/.env.example` は設定名の一覧です。

## Productionで必ず確認する項目

| Key | Default | 内容 |
|---|---:|---|
| `APP_ENV` | `production` | Productionでは`production`。`testing`はTest用transport等に使用 |
| `APP_DEBUG` | `false` | Productionでは必ず`false` |
| `APP_HASH_KEY` | 空 | 32文字以上。Login identityに使用し、運用開始後は変更しない |
| `REGISTRATION_ENABLED` | `true` | Public registrationを許可するか |
| `DB_DRIVER` | `mysql` | Productionは`mysql` |
| `DB_HOST` | 空 | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_NAME` | 空 | Database名 |
| `DB_USER` | 空 | Database user |
| `DB_PASSWORD` | 空 | Database password |
| `DB_TABLE_PREFIX` | `ig_` | 1〜40文字。英字または`_`で開始し、英数字と`_`のみ |

新規設置例では `rss_` を使用しますが、Runtimeの後方互換Defaultは `ig_` です。設定を省略せず、実Tableと一致する値を明示してください。

## Log

| Key | Default | 制約 / 補足 |
|---|---:|---|
| `APP_LOG_ENABLED` | `false` | Application access logの有効化 |
| `APP_LOG_PATH` | `var/log/access.log` | `public/` 外の絶対Pathを推奨 |
| `APP_ERROR_LOG_PATH` | `var/log/error.log` | PHP error log。`public/` 外で書込み可能にする |

Logには運用情報が含まれるため、Gitや配布ZIPへ含めません。

## Authentication / Session

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `AUTH_PASSWORD_MIN_LENGTH` | `12` | 最小8 |
| `AUTH_PASSWORD_MAX_LENGTH` | `72` | Minimum以上。Default 72 bytes |
| `SESSION_COOKIE_NAME` | `iguguru_session` | 変更すると既存Browser sessionは継続しない |
| `SESSION_IDLE_TIMEOUT` | `7200` | 最小300秒 |
| `SESSION_ABSOLUTE_TIMEOUT` | `43200` | Idle timeout以上 |
| `LOGIN_RATE_WINDOW` | `900` | 最小60秒 |
| `LOGIN_RATE_MAX_PAIR` | `5` | 最小2 |
| `LOGIN_RATE_MAX_IP` | `30` | Pair上限以上 |
| `LOGIN_RATE_BLOCK_SECONDS` | `900` | 最小60秒 |
| `REGISTRATION_RATE_WINDOW` | `900` | Registration IP throttleのWindow。最小60秒 |
| `REGISTRATION_RATE_MAX_IP` | `10` | Window内のIP単位Registration試行上限。最小1 |
| `REGISTRATION_RATE_BLOCK_SECONDS` | `900` | 上限到達後のBlock時間。最小60秒 |

Session fileは `var/session/`、Login throttle stateは `var/security/login-throttle/` に保存します。Registration throttleも同じPrivate security directory内で、Raw IPをFile名へ出さないHMAC keyとして保存します。

## API request boundary

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_API_MAX_REQUEST_BYTES` | `1048576` | APIのApplication-level `Content-Length`上限。65536〜4194304 bytes |

`APP_API_MAX_REQUEST_BYTES`はAuthenticationとCSRF確認後に適用します。通常利用のPOSTを壊さず、認証済みAPIへの過大RequestをHTTP 413で停止するための上限です。PHPは通常POSTをApplication codeより前にParseするため、Hosting側の`post_max_size`やWeb Server request-body limitも別途適切に設定してください。

## Outbound HTTP / SSRF boundary

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_HTTP_CONNECT_TIMEOUT_MS` | `3000` | 最小500ms |
| `APP_HTTP_TIMEOUT_MS` | `8000` | Connect timeout以上 |
| `APP_HTTP_MAX_REDIRECTS` | `3` | 0〜5 |
| `APP_HTTP_MAX_BYTES` | `2097152` | 65536〜8388608 bytes |
| `APP_HTTP_USER_AGENT` | `iGuguru-RSS/1.0 (+Secure-Baseline)` | Feed提供元へ送るUser-Agent |

TimeoutやSize上限を緩めても、private address拒否、redirect再検証、TLS検証は無効になりません。

## Feed cache / Retry

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_FEED_CACHE_ENABLED` | `true` | Server-side Feed cache |
| `APP_FEED_CONDITIONAL_REQUEST_ENABLED` | `true` | ETag / Last-Modified / HTTP 304 |
| `APP_FEED_CACHE_TTL_SECONDS` | `60` | 1〜86400秒 |
| `APP_FEED_CACHE_LOCK_TIMEOUT_MS` | `9000` | 0〜30000ms |
| `APP_FEED_RETRY_ENABLED` | `true` | 一時障害のBackoff |
| `APP_FEED_RETRY_MAX_DELAY_SECONDS` | `3600` | 60〜86400秒 |
| `APP_FEED_STALE_IF_ERROR_ENABLED` | `true` | 一時障害時だけ期限付きstaleを使用 |
| `APP_FEED_STALE_MAX_AGE_SECONDS` | `86400` | TTL以上、最大604800秒 |
| `APP_FEED_ITEM_STATE_RETENTION_DAYS` | `90` | 削除済みFeedのNEW状態を整理する日数。1〜3650日。有効Feedは対象外 |

Cache / Lock / Fetch stateは `var/cache/feed/` に置きます。このPathはRuntimeで固定され、`public/` 外です。


## X API Widget（上級者向け / Optional）

X Timeline Widgetは、X Developer Platformで発行したServer-side Bearer Tokenを使って、指定した公開Accountの最近の投稿をRead Onlyで取得します。X APIはPay Per Useのため、利用量とCredit残高はX Developer Console側でも確認してください。

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_X_BEARER_TOKEN` | 空 | X Timelineを利用する場合だけ設定。Secretとして扱い、Git／配布ZIP／Browserへ出さない |
| `APP_X_CACHE_TTL_SECONDS` | `300` | 60〜3600秒。通常取得結果の短時間Cache |
| `APP_X_STALE_MAX_AGE_SECONDS` | `3600` | Cache TTL以上、最大86400秒。許可された一時障害時のstale上限 |
| `APP_X_TIMEOUT_MS` | `5000` | 1000〜10000ms。X API requestのTimeout |

`APP_X_BEARER_TOKEN`の状態は、追加Modalで次のように案内します。

| State | 意味 | 追加操作 |
|---|---|---|
| `missing` | Token未設定 | 無効 |
| `invalid_format` | 改行／制御文字等を含むLocal設定不正 | 無効 |
| `unverified` | Tokenは設定済みだが、現在のTokenでX API認証成功をまだ確認していない | 可 |
| `verified` | 現在のTokenで直近のX API認証成功を確認済み | 可 |
| `auth_failed` | 現在のTokenでHTTP 401を確認 | 可。ただしToken再発行／設定確認が必要 |

Modalを開くだけではX APIへ検証Requestを送りません。Pay Per Useの不要な消費を避けるため、実際のTimeline取得で得た認証結果だけをLocal connection stateへ反映します。状態保存にはTokenのSHA-256 fingerprintだけを使い、Raw TokenやfingerprintをBrowser API responseへ含めません。

`auth_failed`は、TokenをX Developer Console側で再発行したあとServer設定が古い場合などにも発生します。Server設定を更新するとfingerprintが変わるため、以前の確認状態は再利用せず`unverified`へ戻ります。

X Timelineは公開Accountの最近の投稿を対象とします。X本体の「おすすめ / For You」Feedは同じRecommendation結果を公式APIから取得出来ないため、このVersionの対象外です。

## Remote File Manager（V1.29 / Optional）

Remote File ManagerはFTP／明示FTPS／SFTP／HTTPS WebDAVのCredentialをServer側で暗号化して保持します。利用する場合は専用鍵とPrivate temporary directoryを設定してください。

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_REMOTE_CREDENTIAL_KEY_ID` | `primary` | Credential envelopeのKey ID。Key rotationを行う場合の識別子 |
| `APP_REMOTE_CREDENTIAL_KEY_B64` | 空 | 必須。32-byte乱数をBase64化した値。DB/Git/Browserへ出さない |
| `APP_REMOTE_ALLOWED_PORTS` | `21,22,443` | Remote接続を許可するPortの明示Allowlist |
| `APP_REMOTE_PRIVATE_NETWORK_ENABLED` | `false` | LAN/NAS接続のServer側Master switch |
| `APP_REMOTE_PRIVATE_NETWORK_CIDRS` | 空 | Private network利用時のCIDR Allowlist。必要最小限にする |
| `APP_REMOTE_CONNECT_TIMEOUT_MS` | `5000` | 接続Timeout |
| `APP_REMOTE_TRANSFER_TIMEOUT_MS` | `60000` | Transfer Timeout |
| `APP_REMOTE_TRANSFER_MAX_BYTES` | `104857600` | Remote transferのApplication上限 |
| `APP_REMOTE_UPLOAD_MAX_REQUEST_BYTES` | `105906176` | Multipart requestのApplication上限 |
| `APP_REMOTE_TEMP_DIR` | `var/remote-tmp`相当 | `public/`外のPrivate writable directoryを推奨 |
| `APP_REMOTE_SSH_KNOWN_HOSTS_FILE` | 空 | SFTP利用時に必須。Trusted channelで検証したknown_hosts |
| `APP_REMOTE_USER_AGENT` | `iGuguru-RemoteFiles/1.29` | WebDAV等で使うUser-Agent |

Credential keyは次で生成します。

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

適当な32/64文字を手入力する設定ではありません。Strict Base64 decode後が32 bytesちょうどである必要があります。Remote Connection登録後に鍵を変更・紛失すると、保存済みCredentialを復号できなくなります。

本番確認は `php tools/remote_file_env_check.php` を使います。Keyの実値は表示せず、cURL protocol、Sodium、SimpleXML、OpenSSL、SFTP option、Temporary directory等の準備状況を確認します。

Private/LAN接続は`APP_REMOTE_PRIVATE_NETWORK_ENABLED=true`だけでは許可せず、CIDR allowlistと個別Connection opt-inの両方が必要です。Loopback／link-local等はPrivate利用を有効にしても拒否します。

## `config/local.php` と環境変数

Shared hosting等で環境変数が使いにくい場合は `config/local.php` を使用します。ContainerやPHP-FPMでSecret管理を分離できる場合は環境変数を使用できます。

両方に同じKeyがある場合は環境変数が優先されます。想定外の古い環境変数が残っていると `local.php` の値が使われないため、切替時に確認してください。

## Backup対象

- `config/local.php`
- `APP_HASH_KEY`を保管するSecret store
- `APP_REMOTE_CREDENTIAL_KEY_B64`を保管するSecret store（Remote Files利用時）
- SFTPで使用する検証済みknown_hosts／private keyの保管場所
- Database接続情報
- Web server / PHP-FPM側の環境変数設定
- DocumentRoot、PHP Version、Extension、write permissionの記録

実値をRepositoryやDocumentationへ写さないでください。
