# Version 1.5.0 Full Test Report

## Test Level

Full

正式版作成時に、Secure Baseline、M1、M2、V1.1、V1.2、V1.3、V1.4、V1.5を横断する全Testを実行しました。1 Commandでは実行時間上限を超えるため、`tests/run.sh`の順序を維持した区間分割で完走しています。Cache Busting付きAsset URLとV1.5-C / R5の終了表示に合わせ、過去回帰Testの期待値を機能を緩めず更新しました。

## Result

| 対象 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| Full回帰の明示Assertion | 5,978 | 0 | 13 |
| PHP構文 | 101 | 0 | 0 |
| JavaScript構文 | 26 | 0 | 0 |
| Python構文 | 168 | 0 | 0 |
| Shell構文 | 12 | 0 | 0 |

## SKIP

- PDO SQLite Driverが利用できないためSQLite統合TestをSKIP。
- SimpleXML／mbstring等の実行環境依存により一部TestをSKIP。
- Version 1.0、1.1、1.2、1.3、1.4の過去Release専用Gateは、現在Versionが1.5.0のためSKIP。

V1.5のClock Timer、Recovery、複数Tab、Smartphone Feed、終了表示、Version 1.5 Release GateにはSKIPはありません。

## 確認範囲

- Authentication、Logout、Session、CSRF、Authorization
- SSRF、XSS、Validation、Secret Pattern
- RSS 2.0／RSS 1.0／Atom、Cache、ETag、Retry、stale-if-error
- Feed CRUD、Stock、新着Bell、記事概要、個別更新
- Search Feed、記事Actions、Task追加
- Clock、Timer、Memo、Task、Calendar、Account Settings
- Drawer、Header、現在地、外部Link、Hover、Focus、Keyboard
- Game Widget、Icon Quest、Browser Storage
- TimerのPreset、任意時間、復元、Recovery、複数Tab、復帰補正、終了表示
- Smartphone Feed横Overflow、三点リーダー、概要［＋］
- 360／420／1024px、全8 Theme、Reduced Motion、ARIA
- DB Schema、Runtime Data、Private設定
- Documentation Link、Version、Release Builder／Verifier

## Full回帰中に追従したTest

- `dashboard.css`／`dashboard.js`のQuery付きURLを、Asset存在・読込み順TestでPath本体として検証。
- Timer終了直後の一時的な「終了」表示と、約1.8秒後の`00:00:00`復帰を両方検証。
- V1.4 GameとV1.5 Timerの過去Testを、正式版Label`RSS Reader Modernization 1.5.0`でも実行。

Application機能をTestへ合わせて変更したものではなく、R2～R5で確定した実際の仕様へ回帰Testを追従させています。

## 実施していない環境固有Test

- 実Hosting上のMySQL接続と実Data保持
- 外部Networkを使用する実Feed到達性
- 実Mail配送
- Hosting固有`.htaccess`の最終挙動
- BackupからのRestore drill

これらは配置先環境で確認します。iPhone SafariでのSmartphone表示とTimer終了表示は利用者環境で動作確認済みです。

## Package verification

| 対象 | PASS | FAIL |
|---|---:|---:|
| Complete ZIP Verifier | 1,467 | 0 |
| Runtime ZIP Verifier | 1,132 | 0 |

確認内容：

- ZIP SHA-256 Sidecar、CRC、重複Entry、Absolute／Parent Traversal Path
- Top-level Directory、File数、内部Manifest Coverage／SHA-256
- `APP_VERSION`／Label／Build metadata
- `config/local.php`、実DB、Archive、Runtime Dataの非同梱
- Secret Pattern
- Complete／Runtimeの再展開
- 再展開後のV1.5 Focused回帰とPHP／JavaScript構文
