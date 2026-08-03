# V1.1-J Test Report

## V1.1-J重点Test

```text
Account Domain／API／Transaction      PASS 31
Session ID／CSRF再生成                 PASS 9
Architecture／Security／UI            PASS 86
Frontend Runtime                       PASS 45
Dashboard Render                       PASS 29
実Chromium                              PASS 34
------------------------------------------------
合計                                    PASS 234
FAIL 0
SKIP 0
```

集計用Logでは`PASS:`行が233件です。Dashboard Renderの最後の完了行は個別判定ではないため、上表の個別判定数29と差が1件あります。

## V1.1-B〜J回帰

`bash tests/run-local-v1-1-j.sh`を実行した。

```text
PASS 1506
FAIL 0
SKIP 0
```

## 表示・文書・構文

```text
M2-C Dashboard Render    PASS 19
M2-D Dashboard Render    PASS 16
SB-15 Documentation      PASS 3
Version Marker           PASS 8
PHP syntax               PASS 65 files
JavaScript syntax        PASS 14 files
Python syntax            PASS 55 files
Shell syntax             PASS 10 files
```

## 確認した主な内容

- Email IdentityのNormalize／HMAC保存／重複拒否
- 現在のPassword確認、新Password Hash、同一Password拒否
- Session User ownership、Active User、Transaction、Rollback
- MySQL Row Lock、Throttle、Generic Log
- Session ID／CSRF Token再生成
- Modal、Autocomplete、Credential非表示、Password消去
- Browserでの成功、Validation、API失敗、二重送信防止
- V1.1-B以降の回帰、M2表示回帰、構文

## 全体Runnerの制限

再構成した配布BaselineにはSecure Baseline初期の`tests/run.php`が含まれていないため、`bash tests/run.sh`はPHP構文検査後、そのFile不足で停止した。Application失敗とは扱っていない。完全なGitHub作業Folderで再実行する。

## 未確認

- 実運用MySQL／MariaDBでのAccount変更
- GitHub Actions PHP 8.1／8.4
- 複数実端末／Password Managerの全組合せ
