# V1.1-I Test Report

## 結果

```text
Calendar Domain / API             PASS 47
Architecture / Security / UI      PASS 69
Schema / Migration / CLI          PASS 32
Frontend Runtime                  PASS 32
Dashboard Render                  PASS 21
Real Chromium                     PASS 30
------------------------------------------------
V1.1-I focused total              PASS 231 / FAIL 0 / SKIP 0
V1.1-B through V1.1-I local       PASS 1182 / FAIL 0 / SKIP 0
```

## 主な確認

- exact `Y-m-d`と月・年の範囲
- うるう年、月境界、複数日予定
- owner境界、CSRF、Transaction、論理削除
- Task期限の直接参照と完了Task表示設定
- HTML風文字列を文字として表示
- Calendar Widget / 通常予定CRUD
- 前月、翌月、今月、日付からの予定追加
- Calendar上のTaskから既存Task編集Modalを利用
- Calendar Widget変更Requestが1回だけ送信される

## 未確認

実MySQL / MariaDBへのMigration、phpMyAdmin、実運用DB、全テーマ・全実端末、Backup Restore、GitHub Actionsは利用者環境で確認します。
