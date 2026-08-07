# V1.7-H / R4 確認項目

## 配置前

- [ ] V1.7-H/R3が正常に動いている
- [ ] R4ではSQLを実行していない
- [ ] `config/local.php`へ`APP_HOLIDAY_CSV_URL`／`APP_HOLIDAY_CACHE_DAYS`／`APP_HOLIDAY_TIMEOUT_MS`を追加した
- [ ] `var/cache/`へPHP Processが書込み可能

## Calendar表示

- [ ] 2026年8月11日「山の日」が赤表示になる
- [ ] 2026年9月22日「休日」が赤表示になる
- [ ] 土曜日の祝日も祝日の赤表示を優先する
- [ ] 祝日の日付Hoverで祝日名を確認出来る
- [ ] Screen Reader向け`aria-label`にも祝日名が含まれる
- [ ] 通常予定／Task期限表示が従来どおり動く
- [ ] 月移動／今日Button／予定追加が従来どおり動く

## 自動更新

- [ ] `var/cache/japanese_holidays.json`が必要時に生成される
- [ ] Cache生成後は通常のCalendar表示ごとに外部通信しない
- [ ] CSV取得失敗時もCalendar自体は表示される
- [ ] CSV取得失敗時に既存Cacheが削除されない
- [ ] URL変更時は`config/local.php`の`APP_HOLIDAY_CSV_URL`だけで変更出来る

## Regression

- [ ] RSS標準5件／縦2 10件が維持される
- [ ] Clock／Timer高さ1の操作部が切れない
- [ ] Icon Quest／Lights Out高さ1の操作部が切れない
- [ ] Widget Drag & Dropが動く
- [ ] 30日ログインが動く
