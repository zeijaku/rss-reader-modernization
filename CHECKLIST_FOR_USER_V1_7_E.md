# V1.7-E / R1 確認Checklist

- [ ] Footerが`RSS Reader Modernization V1.7-E / R1`
- [ ] 実DatabaseとApplicationをBackupした
- [ ] Preflightで対象DatabaseとPrefixを確認した
- [ ] Migrationの`@table_prefix`を`DB_TABLE_PREFIX`と一致させた
- [ ] `<prefix>remember_token` Tableが作成された
- [ ] Selectorが`CHAR(24) ascii_bin`
- [ ] Validator Hashが`CHAR(64) ascii_bin`
- [ ] Selector Unique Indexがある
- [ ] User＋期限Indexと期限Indexがある
- [ ] Login画面に「30日間維持」はまだ表示されない
- [ ] 通常Login、Logout、Password変更が従来どおり動作する
