# RSS Reader Modernization V1.21.0 Production Apply Note

対象Tag: `v1.21.0`
Baseline: 正式版 V1.20.1

## 適用

1. 現在のApplicationとProduction設定をBackupしてください。
2. `rss-reader-modernization-1.21.0.zip` を展開し、Directory構造を維持したままApplicationへ上書きしてください。
3. Productionの `config/local.php` と `var/` 配下のRuntime dataは置き換えないでください。
4. V1.21.0用のDB Migrationはありません。SQL実行は不要です。
5. `config/local.php` の設定追加・変更は不要です。
6. 適用後はBrowserで一度Hard Reloadしてください。

## Production確認

- DrawerがDISPLAY / FEED / PRODUCTIVITY / INFORMATION / MEDIA / GAME / SETTINGS / ACCOUNTの順で表示されること。
- 設定済みUser LinkはSmartphoneでUSER LINKSへ、PCでは従来どおりNavbarへ表示されること。
- Current項目だけが左側のBlue indicatorを持ち、Section Headerと混同しないこと。
- RSS / Search Feed / Task / Calendar / Memo / Clock / Mail / Links / Weather / Camera / Video / Gameの追加導線が動作すること。
- SmartphoneのRSS / Information Accordionの `>` が右端に寄りすぎず操作しやすいこと。
- DrawerからModalを開く際、Offcanvasが閉じてからModalが表示されること。
- SmartphoneでDrawerを下端までScroll出来ること、横Scrollが発生しないこと、Touch領域が狭くなっていないこと。
- Account SettingsとLogoutが従来どおり動作すること。

問題があればV1.20.1のBackupへRollbackしてください。
