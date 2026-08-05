# V1.2-C / R5 確認項目

1. 配置後にブラウザーをハードリロードする。
2. Search Feedの見出し色を`dark`にしても、タイトルが白く読める。
3. `primary`、`success`、`info`、`secondary`、`warning`、`danger`でもタイトルが白で表示される。
4. 初回読み込み完了後もタイトルが白のまま維持される。
5. Search Feedの個別更新後もタイトルが白のまま維持される。
6. 編集、再読み込み、ドラッグ＆ドロップ、概要、Stockが従来どおり動作する。
7. 背景に応じたタイトル文字色の動的切替は、今回の対象外であることを確認する。

SQL実行、DB変更、`.htaccess`調整、`config/local.php`追記、Feed Cache削除は不要。
