# V1.33.1 Focused Test Report

対象: `RSS Reader Modernization 1.33.1` / Remote Files複数Upload
実行日: 2026-09-09

## 実装結果

- Remote Filesのfile pickerへ`multiple`を追加。
- 既存`remote_file_upload_api.php`へ選択順で1ファイルずつ順次POST。
- RequestごとにCSRF tokenを読み取り、Response Headerで更新。
- 1件の失敗で中断せず、成功／失敗件数と失敗ファイル名を通知。
- Upload中の二重送信を防止。
- DB Migration、公開API、Config、Secretの変更なし。

## Tests

| Test | Result |
|---|---|
| `node --check public/js/remote-files.js` | PASS |
| `python3 tests/remote_file_v1331_multi_upload_static_test.py` | PASS (13/13) |
| `node tests/remote_file_v1331_multi_upload_runtime_test.js` | PASS (9/9) |
| `python3 tests/remote_file_v129h_integration_static.py` | PASS (15/15) |
| `python3 tests/remote_file_v129i_ui_security_test.py` | PASS (19/19) |

## Runtime test coverage

- 3ファイルを選択し、first → second → thirdの順番で3 Requestが発生すること。
- 2番目だけHTTP 409にした場合も3番目まで継続すること。
- CSRF tokenが各Response後に次Requestへ引き継がれること。
- 成功2件／失敗1件、失敗ファイル名、Directory refresh 1回を確認。
- Controlsが全Request終了後に再有効化されること。

## Skip

- PHP 8.1／8.4、MariaDB/MySQL、実Remote Server、Smartphone実機Browserはこの環境では実行していないためSKIP。Release workflowと本番確認手順で実施する。
