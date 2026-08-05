# Version 1.3.0 Full Test Report

## Test Level

Full

正式版作成時に、Secure Baseline、M1、M2、V1.1、V1.2、V1.3を横断する全Testを工程単位に分割して1回実行しました。実行環境の1 Commandあたりの上限を超えるため、同じ`tests/run.sh`の順序を維持しながら複数Processへ分けています。

## Result

| 対象 | PASS | FAIL | SKIP |
|---|---:|---:|---:|
| Full回帰の明示Assertion | 5,163 | 0 | 11 |
| PHP構文 | 99 | 0 | 0 |
| JavaScript構文 | 19 | 0 | 0 |
| Python構文 | 145 | 0 | 0 |
| Shell構文 | 1 | 0 | 0 |

## SKIP

- PDO SQLite Driverが利用できないためSQLite統合TestをSKIP。
- SimpleXML／mbstringが利用できないため一部のLive Parser TestをSKIP。
- Chromium Runtime依存が不足しているためM2-F Browser SmokeをSKIP。
- Version 1.0、1.1、1.2の過去Release専用Gateは、現在Versionが1.3.0のためSKIP。

## Runtime cleanup

分割実行中、HTTP Testが生成した`var/log`のTest LogをRelease Gate前に検出しました。正式Runnerと同じRuntime cleanupを実行後、Version 1.3 Release GateとDocumentation Link Testを再実行し、PASS 248／FAIL 0となっています。Application回帰ではありません。

## 確認範囲

- Authentication、Logout、Session、CSRF、Authorization
- SSRF、XSS、Validation、Secret Pattern
- RSS 2.0／RSS 1.0／Atom、Cache、Retry、stale-if-error
- Feed CRUD、Stock、新着Bell、記事概要、個別更新
- Search Feed、記事Actions、Task追加
- Clock、Memo、Task、Calendar、Account Settings
- Drawer、Header、現在地、外部Link、Hover、Focus、Keyboard
- PC／Smartphone、8 Theme、Dark／Primary／Light
- Widget見出し、記事Title、三点リーダー、Touch操作領域
- DB Schema、Migration構造、Runtime Data、Private設定
- Documentation Link、Version、Release Builder／Verifier

## 実施していない環境固有Test

- 実Hosting上のMySQL接続と実Data保持
- 外部Networkを使用する実Feed到達性
- 実Mail配送
- Hosting固有`.htaccess`の最終挙動
- BackupからのRestore drill

これらは配置先環境で確認します。

## Package verification

| 対象 | PASS | FAIL |
|---|---:|---:|
| Complete ZIP Verifier | 1,289 | 0 |
| Runtime ZIP Verifier | 1,036 | 0 |
| 再展開後V1.3 Focused回帰 | 1,125 | 0 |
| 再展開後Complete PHP／JavaScript構文 | 118 | 0 |
| 再展開後Runtime PHP／JavaScript構文 | 63 | 0 |

確認内容：

- ZIP CRC、重複Entry、Absolute／Parent Traversal Path
- Top-level Directory、File数、内部Manifest Coverage／SHA-256
- 外部`.zip.sha256`
- `APP_VERSION`／Label／Build metadata
- `config/local.php`、実DB、Archive、Runtime Dataの非同梱
- Secret Pattern
- Complete ZIP再展開後のV1.3-B～E回帰
- Complete／Runtime再展開後のPHP／JavaScript構文
