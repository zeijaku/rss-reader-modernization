# V1.17.2-B Apply Note

## Scope

V1.17.2-Bは、V1.17.2-Aで追加したX Timeline Widgetを実運用向けに仕上げ、Version 1.17.2としてRelease Gateへ進める段階です。

主な追加対応は次のとおりです。

- X Timeline追加／編集Modalへ「上級者向け機能」の案内を常時表示。
- `APP_X_BEARER_TOKEN`の状態を`missing`／`invalid_format`／`unverified`／`verified`／`auth_failed`へ分離。
- Token未設定／Local形式不正では追加Buttonを無効化し、Server側create APIでも拒否。
- Modal表示だけではX APIへ検証Requestを送らず、Pay Per Useの不要な消費を避ける。
- 実X API通信のHTTP 401を`auth_failed`、正常な2xx JSONを`verified`としてLocal statusへ反映。
- status Cacheへ保存するのはTokenのSHA-256 fingerprint、状態、確認時刻だけ。Raw Tokenは保存しない。
- Browserへ返すのはnormalized statusだけで、Raw Token／fingerprintは返さない。
- Release Version／Asset revision／Builder／Verifier／CI／Release workflowを1.17.2へ確定。
- X本体の「おすすめ / For You」再現とUser Context OAuth Home Timelineは今回の対象外。

## Database

DB Table／Column／Migrationの追加変更はありません。

V1.17.1のDBをそのまま使用します。

## Production apply

Server配置には`rss-reader-modernization-1.17.2.zip`を使用します。ZIP内のProduction fileはすべて更新済み実ファイルで、配置先でPHP／Python／PowerShell等のPatch適用Scriptを実行する必要はありません。

更新前にApplication codeをBackupし、次は置換しないでください。

- `config/local.php`
- 実Database
- `var/`配下のRuntime data
- Web server／PHP-FPM側のEnvironment variable設定

X Timelineを利用する場合は、既存Server固有設定へ実Bearer Tokenが保持されていることを確認します。

```php
'APP_X_BEARER_TOKEN' => '実Bearer Token',
```

実TokenをこのDocument、Git、Issue、Screenshot、Release ZIPへ記録しないでください。

## Cache / write permission

X Timelineは`var/cache/x/`を使用します。`var/cache/`はRelease ZIPへRuntime dataとして含めません。

配置先ではPHP processが`var/cache/x/`を作成／更新出来る権限を確認してください。

## Browser確認

更新後はBrowserを強制再読込します。

Footer等のVersion表示が`RSS Reader Modernization 1.17.2`になっていることを確認してください。
