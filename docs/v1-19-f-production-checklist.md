# V1.19.0 Production Checklist

## 更新前

- 現在のApplication code、`config/local.php`、実DB、必要な`var/`DataをBackupする。
- 正式Runtime ZIPのSHA-256を照合する。
- ZIPを別Folderへ展開し、`config/local.php`、実DB、生成済みRuntime Dataが含まれていないことを確認する。

## 反映

- Runtime ZIPのTop directory配下をApplication Rootへ相対Pathで上書きする。
- SQL / Migration / `schema.sql`は実行しない。
- `config/local.php`、実DB、既存`var/`生成Dataは維持する。

## 本番確認

- Footerが`RSS Reader Modernization 1.19.0`である。
- Login / Dashboard / Stock / Settings / Logoutが通常どおり動く。
- RSS更新、Stock保存/解除、MemoまたはTask操作を確認する。
- Widget Drag & Drop後、Reloadして配置が維持される。
- Calendarと普段使うInformation Widgetを1件確認する。
- Camera / Video利用時はhls.js SRI Errorが出ず、`camera-video-streaming.js?v=1.19.0`が読み込まれる。
- Account SettingsでPassword Formのusername accessibility warningが再発していない。
- ConsoleにRSS Reader本体由来の新しい赤Errorがない。
- PHP / Apache Error Logに想定外HTTP 500、`Failed opening required`、`Cannot redeclare`、`undefined function`がない。
- Smartphone実機またはDevice ModeでTab Swipe、Drawer、Calendar、主要Widget幅を軽く確認する。

Performance `[Violation]` warningやYouTube / Google Ads / Browser Extension由来の外部Errorは、RSS Reader本体の機能障害と分けて判断する。
