# Version 1.4.0 Full Test Report

## Test Level

Full

正式版作成時に、Secure Baseline、M1、M2、V1.1、V1.2、V1.3、V1.4を横断する全Testを実行しました。Browser Testを含む一部工程は1 Commandの実行時間上限を超えるため、`tests/run.sh`と同じ順序を維持した区間分割で完走しています。V1.3-Cの全Theme Header Matrixは、Test内容を変えずTheme単位の一時Runnerへ分割しました。

## Result

| 対象 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| Full回帰の明示Assertion | 5,665 | 0 | 12 |
| PHP構文 | 101 | 0 | 0 |
| JavaScript構文 | 9 | 0 | 0 |
| Python構文 | 157 | 0 | 0 |
| Shell構文 | 1 | 0 | 0 |
| V1.4-B～E再確認 | 725 | 0 | 0 |

## SKIP

- PDO SQLite Driverが利用できないためSQLite統合TestをSKIP。
- SimpleXML／mbstringが利用できないため一部のLive Parser TestをSKIP。
- Chromium Runtime依存が不足している旧M2-F Browser SmokeをSKIP。
- Version 1.0、1.1、1.2、1.3の過去Release専用Gateは、現在Versionが1.4.0のためSKIP。

V1.4のGame Widget、Icon Quest、Storage、Keyboard、Touch、Theme、Release GateにはSKIPはありません。

## 確認範囲

- Authentication、Logout、Session、CSRF、Authorization
- SSRF、XSS、Validation、Secret Pattern
- RSS 2.0／RSS 1.0／Atom、Cache、ETag、Retry、stale-if-error
- Feed CRUD、Stock、新着Bell、記事概要、個別更新
- Search Feed、記事Actions、Task追加
- Clock、Memo、Task、Calendar、Account Settings
- Drawer、Header、現在地、外部Link、Hover、Focus、Keyboard
- Game Widget CRUD、Tab配置、並べ替え、複数Widget
- Icon Quest全4 Level、Enemy追跡、Treasure、Goal、Clear／Game Over
- Browser Storageの分離、Validation、Fallback、Recovery、記録削除
- 360／420／1024px、全8 Theme、Reduced Motion、ARIA
- Game Headerの共通余白と44px操作領域
- DB Schema、Migration構造、Runtime Data、Private設定
- Documentation Link、Version、Release Builder／Verifier

## 実施していない環境固有Test

- 実Hosting上のMySQL接続と実Data保持
- 外部Networkを使用する実Feed到達性
- 実Mail配送
- Hosting固有`.htaccess`の最終挙動
- BackupからのRestore drill
- 利用者の実端末に固有のPrivate Mode／Storage制限

これらは配置先環境で確認します。

## Package verification

| 対象 | PASS | FAIL |
|---|---:|---:|
| Complete ZIP Verifier | 1,389 | 0 |
| Runtime ZIP Verifier | 1,090 | 0 |
| 再展開後V1.4 Focused回帰 | 725 | 0 |
| 再展開後Complete PHP／JavaScript構文 | 110 | 0 |
| 再展開後Runtime PHP／JavaScript構文 | 65 | 0 |

確認内容：

- ZIP CRC、重複Entry、Absolute／Parent Traversal Path
- Top-level Directory、File数、内部Manifest Coverage／SHA-256
- 外部`.zip.sha256`
- `APP_VERSION`／Label／Build metadata
- `config/local.php`、実DB、Archive、Runtime Dataの非同梱
- Secret Pattern
- Complete ZIP再展開後のV1.4-B～E回帰
- Complete／Runtime再展開後のPHP／JavaScript構文
