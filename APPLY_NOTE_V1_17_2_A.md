# V1.17.2-A X API Widget — 適用メモ

## 対象

Version 1.17.1正式版を基準に、X APIをServer側から読み込み、Dashboardへ独自表示する `X Timeline` Widgetを追加します。

V1.17.2-Aは開発確認段階のため、表示Versionは`1.17.1`のまま、Asset Revisionのみ`1.17.2-a`です。V1.17.2-Bの実API確認／Release Gateで正式Versionを確定します。

## 適用方法

このZIPは差分確認用です。Application rootを基準に、ZIP内のRuntime対象ファイルを同じ相対Pathへ上書きしてください。

**本番動作に必要な変更はすべて更新済み実ファイルとして収録しています。**
`php tools/apply-*.php`、Python、PowerShell等の適用Scriptを実行して完成させる方式ではありません。

`config/local.php`、実DB、Log、Session、Cache等はZIPに含めません。

## DB

DB Table／Column／Migrationの追加変更はありません。
既存`dashboard_widget`の`widget_type`と`widget_config`を利用し、Widget typeは`x_timeline`です。

## X API private configuration

X APIのBearer TokenはBrowserへ渡さず、Server側だけに設定します。

既存のprivate `config/local.php`を利用する場合は、配列へ次を追加してください。

```php
'APP_X_BEARER_TOKEN' => '実際のBearer Token',
```

必要な場合だけ次も調整できます。未指定なら安全なDefaultを使用します。

```php
'APP_X_CACHE_TTL_SECONDS' => '300',
'APP_X_STALE_MAX_AGE_SECONDS' => '3600',
'APP_X_TIMEOUT_MS' => '5000',
```

Environment Variableでも同名設定を利用できます。

**実TokenをGitへCommitしたり、このレビューZIPへ追加したりしないでください。**

## V1.17.2-Aで追加する機能

- DrawerのRSS Catalogへ`X Timeline`を追加。
- X usernameは`@`付き／なしの両方を受け付け、英数字とunderscore 1〜15文字へValidation。
- X APIでusernameからUser IDを解決し、そのUserの最新Postsを取得。
- 表示件数3／5／10。
- 返信を含める／リポストを含める設定。
- Title、Width 1〜4、Height 1〜2、Header style設定。
- Account名、username、投稿本文、投稿日時、XへのLinkを独自HTMLで表示。
- 5分のServer-side timeline Cacheと、Transient failure時のbounded stale fallback。
- 手動更新Button。
- Create／Update／Delete／Refreshをページ全体Reloadなしで処理。
- X Card更新時は対象Cardだけを差し替え、他のYouTube／Video等を再構築しない。
- Bearer TokenはServer-side HTTP headerでのみ利用し、BrowserのHTML／JavaScript／Network requestへ渡さない。

## X APIについて

2026-08-19時点のX公式Documentationで、User lookupは`GET /2/users/by/username/{username}`、User Postsは`GET /2/users/{id}/tweets`を確認しています。User Posts timelineはApp-Only authenticationに対応しています。

User Postsの`max_results`は最低5件のため、Widget表示を3件にした場合もAPIからは最低5件を取得し、Browserへ渡す正規化結果を3件へ絞ります。

X APIはPay-per-use／Credit制です。通常表示では5分Cacheを利用しますが、「更新」Buttonは強制取得なのでAPI利用量が増えます。実環境確認前にDeveloper ConsoleのApp、Bearer Token、Credit／Usage状態を確認してください。

## Rollback

V1.17.1のBackupへ変更ファイルを戻し、新規ファイル`app/x_widget.php`、`public/js/x-widget.js`、`public/css/x-widget.css`を削除してください。

DB MigrationはないためDB rollbackは不要です。ただし作成済み`x_timeline` WidgetのDB recordを残した状態でV1.17.1へ戻す場合、V1.17.1側では未知Widgetとして表示対象にならない可能性があります。Rollback前にX WidgetをUIから削除しておくことを推奨します。
