# V1.19 Public Endpoint Matrix

V1.19-Cで導入したPublic Endpoint Matrixを、現行Releaseの`public/`直下PHPと同期した一覧です。`.htaccess`のPHP whitelistもこの一覧と一致させます。

| Endpoint | Anonymous | Allowed / intended method | CSRF | Authentication | Authorization | DB access | External access | Notes |
|---|---|---|---|---|---|---|---|---|
| / | Yes | GET; POST(Login/Registration) | POST auth forms: Yes | Optional (page); POST establishes session | Authenticated dashboard data is Session-user scoped | Yes | No direct outbound request at entry page | Other methods are unsupported but do not perform mutation. |
| /stock | Yes (login view) | GET; POST(Login/Registration) | POST auth forms: Yes | Required for Stock data | Stock/tag queries are Session-user scoped | Yes | No direct outbound request at page entry | Anonymous request renders login view; mutation stays in API. |
| /settings | No (redirects to /) | GET (page); POST is not a mutation endpoint | API mutations: Yes at /api_v1.php | Required | Settings/keywords use Session user | Yes | No direct outbound request at page entry | Forms are intercepted by JS and write through API. |
| /rss-management | No (redirects to /) | GET (page); POST is not a mutation endpoint | API mutations: Yes at /api_v1.php | Required | RSS list / OPML operations use Session user and owner-scoped content | Yes | No direct outbound request at page entry | V1.22-A RSS management and OPML UI; mutations stay in API. |
| /api_v1.php | No | POST only | Yes | Required | Session user + owner-scoped handlers | Action dependent | Action dependent | 1 MiB default application request-size guard after Auth/CSRF. |
| /calendar_color_api.php | No | POST only | Yes | Required | Session user + Calendar event owner scope | Yes | No | Calendar event color list/create/update only; red/blue/green allowlist; request-size guard after Auth/CSRF. |
| /logout.php | No meaningful anonymous action | POST only | Yes | Current session/remember state | Current session only | Remember-token path may access DB | No | GET/other method => 405. |
| /connection_probe.php | Yes | GET only | No | No | N/A | No | No | Returns empty 204; no bootstrap/session. |
| /error.php | Yes (ErrorDocument) | Error subrequest/direct GET | No | No | N/A | No | No | Generic 403/404/500/503 rendering only. |

## Rules

- 新しい`public/*.php`を追加した場合は、このMatrixと`public/.htaccess` whitelistを同時に更新する。
- HTML pageのFormが`method="post"`でも、Application mutationが`api_v1.php`へ集約されているものは「page自体のPOST」と「API mutation」を区別する。
- `api_v1.php`のDB / External accessはActionによって異なる。外部通信ActionでもAuthentication / CSRF / Action validationを先に通す。
- `rss-management.php`はV1.22-Aで追加した認証必須のRSS管理Page。RSS一覧・OPML Import / ExportのMutationは`api_v1.php`へ集約し、Session userを所有者のAuthorityとする。
- `calendar_color_api.php`はV1.20.1で追加したCalendar色専用POST Endpoint。Authentication / CSRF / request-size / action allowlist / owner scope / `red|blue|green` allowlistを通し、外部通信は行わない。
- `connection_probe.php`と`error.php`は意図的にApplication bootstrapを読み込まない最小Endpoint。
- Apache以外では`.htaccess` whitelistが適用されないため、Server側で同等Ruleを設定する。
