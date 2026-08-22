# V1.19-C Production Check List

V1.19-CはSecurity boundary変更を含むため、全機能総当たりではなく、以下を本番で確認してください。

1. **Backup**
   - 現在のApplication filesを退避してからProduction ZIPをApplication Rootへ上書きする。
   - `config/local.php`、DB、`var/`は上書きしない。

2. **Basic pages**
   - `/` Login画面が表示できる。
   - Login後Dashboardが通常表示できる。
   - `/stock`、`/settings`が通常どおり開ける。
   - Logoutが通常どおり動く。

3. **Normal API writes**
   - MemoまたはTaskを1件追加・変更・削除する。
   - Stock保存/削除またはWidget並べ替えを1回確認する。
   - 通常操作でHTTP 413が発生しないことを確認する。

4. **Existing external/information path**
   - Connection MonitorまたはWeather等、既存Widgetを1つ更新して通常表示されることを確認する。

5. **Security headers**
   - Browser DevToolsのResponse HeaderでCSPに `object-src 'none'` が含まれることを確認する。
   - CSP violationがConsoleに大量発生していないことを確認する。

6. **Public PHP whitelist**
   - `connection_probe.php`がこれまでどおり動作することを確認する。
   - 任意確認: 一時的に`public/v119c_probe.php`を置ける場合、URL直Accessが403になることを確認して直ちに削除する。実施しなくてもよい。

7. **Registration**
   - Registrationを有効にしている場合、登録画面がこれまでどおり表示されることだけ確認する。
   - Throttle確認のために本番で10回以上登録を繰り返す必要はない。

8. **Server logs**
   - PHP/Apache Error Logに `Failed opening required`, `Cannot redeclare`, `undefined function`, 想定外HTTP 500がないことを確認する。

## Notes

- Apache以外（Nginx等）では`.htaccess`のCSP/PHP Whitelistは適用されません。その場合は同等RuleをWeb Server側へ設定する必要があります。
- `APP_API_MAX_REQUEST_BYTES`はApplication-level guardです。Upload/POSTのDoS対策としてはPHP `post_max_size`やWeb Server request-body limitも別途設定してください。
- HSTSは今回有効化していません。HTTP accessが残る環境で先に有効化しないためです。

9. **Camera / HLS SRI follow-up**
   - Browser Consoleに`Failed to find a valid digest in the integrity attribute`が出ないことを確認する。
   - `camera-video-streaming.js?v=1.18.0-r4`が読み込まれていることを確認する。
