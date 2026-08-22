# V1.19-D Production Check List

V1.19-DはDocumentation中心で、Runtime変更はAccount Password FormのBrowser autocomplete補助Fieldだけです。V1.19-B/C/SRI Hotfixを含む累積Production ZIPとして確認します。

1. **Backup / apply**
   - 現在のApplication filesをBackupする。
   - Production Update ZIPをApplication Rootへ相対Pathで上書きする。
   - `config/local.php`、実DB、`var/` Runtime dataは上書きしない。

2. **Basic pages**
   - Login後Dashboardが表示できる。
   - `/stock`、`/settings`が通常どおり開く。
   - Logoutが動作する。

3. **Account Settings**
   - Account Settings modalを開く。
   - Browser Consoleで`Password forms should have (optionally hidden) username fields`警告が出ないことを確認する。
   - Password変更を実際に行う必要はない。可能なら入力欄の表示・Buttonが従来どおりであることを確認する。

4. **V1.19-C carry-over**
   - Consoleにhls.jsの`Failed to find a valid digest in the integrity attribute`が出ない。
   - `camera-video-streaming.js?v=1.18.0-r4`が読み込まれる。
   - Response CSPに`object-src 'none'`が含まれる。

5. **Representative API**
   - MemoまたはTaskを1件追加・変更・削除する。
   - Stock保存/解除またはWidget並べ替えを1回確認する。

6. **Server log**
   - `Failed opening required`, `Cannot redeclare`, `undefined function`, 想定外HTTP 500がないことを確認する。

## Expected unchanged items

- Footer/Application Version: `1.18.0`
- Asset Revision: `1.18.0-r4`
- DB Migration / SQL: なし
- 必須config / Secret: 追加なし
