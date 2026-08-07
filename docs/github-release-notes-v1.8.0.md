# RSS Reader Modernization 1.8.0

Version 1.8.0ではStock一覧を、保存するだけの画面から後で探して利用する画面へ改善しました。

主な内容:

- Stock解除（既存`stock_flag`による論理削除、Ownership／CSRF維持）
- Title／URL／DomainのServer-side検索
- 新しい順／古い順／タイトル順
- 20件単位の通常Pagination
- 検索／Sort条件のPage移動時保持
- URLからDomain表示
- ランダム色4列Cardから1列Compact Listへ変更
- Stock一覧でURL Copy、X、Task追加、Stock解除Actions
- Task Widget複数時の追加先選択
- DB Table／Column／Index／Migration追加なし

更新手順と検証範囲は`RELEASE_NOTES.md`、`docs/update.md`、`CHECKLIST_FOR_USER_V1_8_RELEASE.md`を参照してください。
