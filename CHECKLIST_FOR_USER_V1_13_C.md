# V1.13-C ユーザー確認順序

1. V1.13-B R2環境をBackupする。
2. V1.13-C ZIPを展開し、サーバーへ上書きする。DB / SQL作業は不要。
3. 通常Dashboardを開き、RSS / Widget / Drawerが従来どおり表示されることを確認する。
4. Drawerの「表示設定」を押し、URLが `/settings#display` になることを確認する。
5. Drawerの「タブ表示変更」を押し、URLが `/settings#tabs` になることを確認する。
6. Drawerの「RSS Highlight」を押し、URLが `/settings#highlight` になることを確認する。
7. `/settings.php` を直接開き、`/settings` へ変わることを確認する。
8. Settingsの表示設定でThemeまたはNavbar Styleを1項目変更し、保存後に反映されることを確認する。必要なら元へ戻す。
9. タブ名を1つ変更し、Dashboardへ戻って表示が変わることを確認する。必要なら元へ戻す。
10. RSS Highlightに確認用Keywordを1件追加し、Dashboardの該当RSSタイトルが強調されることを確認する。その後、確認用Keywordを削除する。
11. SettingsのDrawerからAccount Settingsを開き、従来どおりModalが表示されることを確認する。認証情報を実際に変更する必要はない。
12. Stock一覧を開き、URLが引き続き `/stock` であることを確認する。
13. Stock検索・Tag・Stock解除・Stock→Taskの代表操作を1回ずつ確認する。
14. Smartphone幅でもSettings画面に大きな横スクロールや崩れがないことを確認する。
15. Browser Developer ToolsのConsole / Networkで新規JavaScript Error、404、500、Redirect Loopがないことを確認する。
