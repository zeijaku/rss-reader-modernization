# V1.19 Architecture

V1.19-B以降のSource構造を、今後の機能追加時に迷わないための境界として整理します。目的は大規模なLayer化ではなく、V1.18までのFunction / API contractを維持したままFat fileへ処理を足し続けないことです。

## Request flow

```text
Browser
  |
  +-- HTML pages -------------------------> public/index.php / stock.php / settings.php
  |
  +-- authenticated mutation/read API ---> public/api_v1.php
                                               |
                                               +-- Authentication / CSRF / size / action validation
                                               |
                                               +-- app/api.php  (stable facade + dispatcher)
                                                       |
                                                       +-- app/api/content.php
                                                       +-- app/api/dashboard.php
                                                       +-- app/api/account.php
                                                       +-- app/api/integrations.php
                                               |
                                               +-- Camera/Mail dedicated dispatchers
```

`public/api_v1.php`はHTTP boundaryのまま維持し、Frontendから見たAction名やResponse contractはV1.18から変更しません。

## API responsibility groups

| File | 主な責務 |
|---|---|
| `app/api.php` | 共通API helper、stable dispatcher、Action table |
| `app/api/content.php` | Feed / Content / Stock等、記事・Feed中心のAction |
| `app/api/dashboard.php` | Dashboard Widget共通操作、Memo / Task / Link等のDashboard Action |
| `app/api/account.php` | Settings / Tabs / Account email・password |
| `app/api/integrations.php` | Weather / Sun-Moon / Air Quality / Earthquake / X等のIntegration Action |

Actionごとの1File化は行いません。新しいActionは、既存4分類へ自然に入る場合はそこへ追加し、分類自体が大きくなったときだけ新しい大分類を検討します。

## Dashboard Widget responsibility groups

```text
app/dashboard_widget.php
  +-- app/dashboard/feed_widgets.php
  +-- app/dashboard/personal_widgets.php
  +-- app/dashboard/utility_widgets.php
```

| File | 主な責務 |
|---|---|
| `app/dashboard_widget.php` | Widget type/config normalization、public projection、owner lock、generic reorder等の共通基盤 |
| `app/dashboard/feed_widgets.php` | Feed Widget persistence |
| `app/dashboard/personal_widgets.php` | Memo / Task persistence |
| `app/dashboard/utility_widgets.php` | Clock / Calculator / Blind Spot persistence |

Calendar、Search Feed、Weather、Links等、V1.18以前から独立Moduleを持つ機能は無理にこの3Fileへ移しません。

## Frontend boundary

`public/js/dashboard.js`、`public/js/utility-widgets.js`、`public/stock.php`、`public/css/dashboard.css`はSizeだけを理由にV1.19で分割していません。

今後は次を基本とします。

- 大きな新機能を`dashboard.js`へ無条件に追加しない。
- 独立したWidget runtimeは既存の`camera-video.js`、`connection-monitor.js`等と同じく専用Fileを検討する。
- 共通API helper、Notice、既存Event contractを重複実装しない。
- CSSもFeatureが独立している場合だけ専用Fileへ分け、既存Dashboard全体を機械的に分割しない。

## Stable boundaries

V1.19で維持する契約:

- Public API入口は`public/api_v1.php`。
- API Action名は既存名を維持。
- Session `user_id`がowner authority。
- Mutationは既存CSRF boundaryを通る。
- DB schema / MigrationはV1.19-B〜Dで変更しない。
- `app/bootstrap.php`はcomposition rootとして残し、細分化の対象にしない。
- `public/stock.php`等の大きなViewは、UI regression riskが高いためV1.19では大規模分割しない。

Security boundaryは[`v1-19-security-boundary.md`](v1-19-security-boundary.md)、Public entry pointsは[`v1-19-public-endpoints.md`](v1-19-public-endpoints.md)を参照してください。
