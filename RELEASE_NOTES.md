# RSS Reader Modernization 1.55.1

PHP 8.3環境でComposerの依存関係がPHP 8.4.1以上を要求し、共通APIがHTTP 500になる配布不具合を修正します。最低対応PHPは8.3。依存関係の選定基準を8.3.0に固定し、完成Runtime ZIPのautoload・Mailクラス・実HTTPをPHP 8.3/8.4で検証します。機能変更なし。

## 更新

v1.55.0からはSQL不要。v1.54.0からはMigration 038が必要です。バックアップ後、Runtime ZIPを展開し、旧vendorは別途保全して新vendor一式に置き換えます（古い依存ファイルの混在を避ける）。既存設定・DB・利用者データは維持します。PHP 8.3の設定変更は不要。更新後、フッター、RSS、Weather、Memo、Mailを確認してください。JSの503は別途調査対象です。実際の本番環境では未検証。

[更新手順](docs/update.md)

## Verification limits

CI uses synthetic databases, Chromium and local PHP HTTP fixtures. Production hosting, real external RSS/Weather/IMAP connections, physical mobile devices and the reported static JS HTTP 503 are not verified. PHP 8.1 source regression is retained solely for compatibility checks; the shipped vendor requires PHP 8.3 or later.
