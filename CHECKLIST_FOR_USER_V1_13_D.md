# V1.13-D ユーザー確認順序

1. 現在のV1.13-C環境をBackupする。
2. V1.13-D ZIPを展開し、サーバーへ上書きする。DB / SQL作業は不要。
3. ログイン画面を開き、従来どおりログインできることを確認する。
4. Dashboardのタブ1～4を順に開き、Widgetの並び・幅・縦幅・タイトル・内容が従来どおり表示されることを確認する。
5. RSS Widgetを1つ選び、読込、再読込、記事Actions（Stock / URLコピー等）の代表操作を確認する。
6. DrawerからRSS / Search Feed / Clock / Memo / Task / Calendar / Links / Weather / Gameの追加Modalが開くことを確認する。実際に全て追加する必要はない。
7. 既存Widgetの編集Modalを2～3種類（例: RSS、Clock、Task）開き、現在値が従来どおり入っていることを確認する。
8. Widgetのドラッグ＆ドロップによる並べ替えを1回確認する。
9. DrawerからAccount Settingsを開き、従来どおりModalが表示されることを確認する。認証情報を変更する必要はない。
10. DrawerのSettingsリンクを開き、`/settings` が従来どおり表示されることを確認する。
11. Stock一覧を開き、`/stock` が従来どおり表示され、検索またはTag絞り込みを1回確認する。
12. 旧URL `/?tab=stock` を開き、従来どおり `/stock` へRedirectされることを確認する。
13. Smartphone幅でもDashboard / Drawer / Modalに大きな崩れや横スクロールがないことを確認する。
14. Browser Developer ToolsのConsole / Networkで、新規JavaScript Error、404、500、Redirect Loopがないことを確認する。

V1.13-Dは構造整理のみなので、見た目や操作仕様が変わっている場合は適用を止めてください。
